<?php

namespace App\Console\Commands;

use App\Services\Gps\HexPacketCodec;
use App\Services\Gps\MobilePacketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Throwable;
use Symfony\Component\Console\Output\OutputInterface;

class ListenMobileGps extends Command
{
    protected $signature = 'gps:listen {--host=127.0.0.1} {--port=9000} {--cert=} {--key=} {--once}';

    protected $description = 'Receive authenticated TG1 mobile GPS hex frames over TCP/TLS';

    public function handle(HexPacketCodec $codec, MobilePacketService $packets): int
    {
        $host = (string) $this->option('host');
        $port = filter_var($this->option('port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (! $port || ! filter_var($host, FILTER_VALIDATE_IP)) {
            $this->error('Use a literal bind IP and a port between 1 and 65535.');
            return self::FAILURE;
        }
        $cert = $this->option('cert');
        if (! $cert && ! in_array($host, ['127.0.0.1', '::1'], true)) {
            $this->error('Public listeners require --cert and --key. Plain TCP is allowed only on loopback behind a TLS proxy.');
            return self::FAILURE;
        }
        if ($cert && (! is_readable($cert) || ! is_readable((string) $this->option('key')))) {
            $this->error('TLS certificate/key are not readable.');
            return self::FAILURE;
        }
        $context = stream_context_create($cert ? ['ssl' => [
            'local_cert' => $cert, 'local_pk' => $this->option('key'),
            'verify_peer' => false,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER,
        ]] : []);
        // Explicitly upgrade accepted sockets before decoding application data.
        $address = 'tcp://'.(str_contains($host, ':') ? '['.$host.']' : $host).':'.$port;
        $server = @stream_socket_server($address, $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if (! $server) {
            $this->error('Listener could not bind: '.$error);
            return self::FAILURE;
        }
        stream_set_blocking($server, false);
        $this->info('Mobile GPS listener: '.($cert ? str_replace('tcp://', 'tls://', $address) : $address));
        $clients = [];
        $handled = false;
        try {
            while (true) {
                $read = [$server];
                $write = [];
                foreach ($clients as $client) {
                    $read[] = $client['socket'];
                    if ($client['out'] !== '') $write[] = $client['socket'];
                }
                $except = null;
                if (@stream_select($read, $write, $except, 1) === false) break;
                foreach ($read as $socket) {
                    if ($socket === $server) {
                        $connection = @stream_socket_accept($server, 1, $peer);
                        if (! $connection) continue;
                        if (count($clients) >= 64) {
                            fclose($connection);
                            continue;
                        }
                        stream_set_blocking($connection, false);
                        $clients[(int) $connection] = ['socket' => $connection, 'in' => '', 'out' => '',
                            'tls_ready' => ! $cert, 'connected_at' => time(),
                            'activity' => time(), 'peer' => preg_replace('/:[0-9]+$/', '', $peer)];
                        $this->logPacket(['event' => 'CONNECTED', 'peer' => $peer]);
                        continue;
                    }
                    $id = (int) $socket;
                    if (! $clients[$id]['tls_ready']) {
                        $crypto = @stream_socket_enable_crypto($socket, true,
                            STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER);
                        if ($crypto === false) {
                            $this->logPacket(['event' => 'TLS_REJECTED', 'peer' => $clients[$id]['peer']]);
                            fclose($socket);
                            unset($clients[$id]);
                            continue;
                        }
                        if ($crypto === 0) continue;
                        $clients[$id]['tls_ready'] = true;
                        $this->logPacket(['event' => 'TLS_ESTABLISHED', 'peer' => $clients[$id]['peer']]);
                    }
                    $bytes = $this->readClient($socket);
                    if ($bytes === false || ($bytes === '' && feof($socket))) {
                        $this->logPacket(['event' => 'DISCONNECTED', 'peer' => $clients[$id]['peer'],
                            'reason' => $bytes === false ? 'read_failed' : 'peer_closed']);
                        fclose($socket);
                        unset($clients[$id]);
                        continue;
                    }
                    if ($bytes === '') continue;
                    $clients[$id]['activity'] = time();
                    $clients[$id]['in'] .= $bytes;
                    while (($end = strpos($clients[$id]['in'], chr(10))) !== false) {
                        $frame = substr($clients[$id]['in'], 0, $end);
                        $clients[$id]['in'] = substr($clients[$id]['in'], $end + 1);
                        try {
                            $this->logPacket(['event' => 'RECEIVED', 'peer' => $clients[$id]['peer'],
                                'hex_characters' => strlen($frame)]);
                            $limitKey = 'gps.socket.'.$clients[$id]['peer'];
                            if (RateLimiter::tooManyAttempts($limitKey, 120)) {
                                throw new InvalidArgumentException('rate_limited');
                            }
                            RateLimiter::hit($limitKey, 60);
                            $decoded = $codec->decode($frame);
                            $reply = $packets->handle($decoded['code'], $decoded['payload']);
                            $this->logAcceptedPacket($clients[$id]['peer'], $decoded['code'], $decoded['payload'], $reply);
                            $clients[$id]['out'] .= $codec->encode(0x80, $reply);
                        } catch (Throwable $exception) {
                            $reason = match (true) {
                                $exception instanceof ValidationException => 'invalid_fields',
                                $exception instanceof JsonException => 'invalid_json',
                                $exception instanceof InvalidArgumentException => $exception->getMessage(),
                                default => 'server_error',
                            };
                            $details = $exception instanceof ValidationException
                                ? ['field_errors' => array_keys($exception->errors())] : [];
                            $clients[$id]['out'] .= $codec->encode(0x81, ['ok' => false, 'reason' => $reason, ...$details]);
                            $this->logPacket(['event' => 'REJECTED', 'peer' => $clients[$id]['peer'], 'reason' => $reason]);
                            // Never log raw frames: they contain bearer credentials.
                            if ($reason === 'server_error') $this->warn('Packet failed; retry with the same packet_id.');
                        }
                        $handled = true;
                        if ($this->option('once')) break;
                    }
                    if (strlen($clients[$id]['in']) > HexPacketCodec::MAX_HEX_LENGTH
                        || strlen($clients[$id]['out']) > 65536) {
                        fclose($socket);
                        unset($clients[$id]);
                    }
                }
                foreach ($write as $socket) {
                    $id = (int) $socket;
                    if (! isset($clients[$id])) continue;
                    $written = @fwrite($socket, $clients[$id]['out']);
                    if ($written === false) {
                        fclose($socket);
                        unset($clients[$id]);
                    } else {
                        $clients[$id]['out'] = substr($clients[$id]['out'], $written);
                    }
                }
                foreach ($clients as $id => $client) {
                    if ((! $client['tls_ready'] && time() - $client['connected_at'] > 10)
                        || time() - $client['activity'] > 75) {
                        fclose($client['socket']);
                        unset($clients[$id]);
                    }
                }
                if ($this->option('once') && $handled && ! array_filter($clients, fn ($client) => $client['out'] !== '')) break;
            }
        } finally {
            foreach ($clients as $client) fclose($client['socket']);
            fclose($server);
        }

        return self::SUCCESS;
    }

    protected function readClient($socket): string|false
    {
        // A remote TLS reset is a client disconnect, not a listener-wide failure.
        try {
            return @fread($socket, 8192);
        } catch (\ErrorException) {
            return false;
        }
    }

    protected function logAcceptedPacket(string $peer, int $code, array $payload, array $reply): void
    {
        $event = match (true) {
            (bool) ($reply['duplicate'] ?? false) => 'DUPLICATE',
            $code === 2 && ($reply['saved'] ?? false) => 'SAVED',
            $code === 2 && ($reply['live_cached'] ?? false) => 'LIVE_CACHED',
            $code === 2 => 'LOCATION_NOT_SAVED',
            $code === 1 => 'HANDSHAKE',
            $code === 3 => 'HEARTBEAT',
            default => 'LOGOUT',
        };
        $fields = ['user_id', 'imei', 'license_number', 'packet_id', 'recorded_at'];
        if ($code === 2) {
            $fields = [...$fields, 'source_type', 'latitude', 'longitude', 'accuracy',
                'speed', 'bearing', 'heading', 'altitude', 'battery_level', 'network_type',
                'signal_strength', 'provider', 'is_mock'];
        } else {
            $fields = [...$fields, 'battery_level', 'network_type', 'is_gps_enabled'];
        }
        $this->logPacket([
            'event' => $event, 'peer' => $peer, 'code' => sprintf('%02X', $code),
            ...array_intersect_key($payload, array_flip($fields)),
            'saved' => $reply['saved'] ?? false, 'live_cached' => $reply['live_cached'] ?? false,
            'reason' => $reply['reason'] ?? null,
            'distance_filter_meters' => $reply['distance_filter_meters'] ?? null,
        ]);
    }

    protected function logPacket(array $fields): void
    {
        // Structured allowlisted logs never contain tokens or raw credential frames.
        $this->output->writeln(json_encode([
            'time_utc' => now()->utc()->toIso8601String(), ...$fields,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
    }
}
