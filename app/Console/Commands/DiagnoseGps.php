<?php

namespace App\Console\Commands;

use App\Services\Gps\Gateway\Gt06DiagnosticFrames;
use App\Services\Gps\Gateway\Protocols\Gt06;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/** Temporary, bounded packet observation. No SQL, Redis, jobs or broadcasts. */
class DiagnoseGps extends Command
{
    protected $signature = 'gps:diagnose {--host=0.0.0.0} {--port=9000} {--imei=} {--seconds=0}';

    protected $description = 'Observe live TCP connections; documented GT06 ACKs only for the explicit diagnostic IMEI; no production persistence';

    public function handle(Gt06 $protocol, Gt06DiagnosticFrames $framer): int
    {
        $host = (string) $this->option('host');
        $port = filter_var($this->option('port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $imei = (string) $this->option('imei');
        $seconds = filter_var($this->option('seconds'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 86400]]);
        if (! filter_var($host, FILTER_VALIDATE_IP) || ! $port || $seconds === false || ($imei !== '' && ! preg_match('/^[0-9]{15}$/D', $imei))) {
            $this->error('Use a literal bind IP, valid port, optional 15-digit diagnostic IMEI and duration 0–86400 seconds.');

            return self::FAILURE;
        }
        $address = 'tcp://'.(str_contains($host, ':') ? '['.$host.']' : $host).':'.$port;
        $server = @stream_socket_server($address, $errno, $error);
        if (! $server) {
            $this->error('Cannot bind '.$address.': '.$error.' Stop the other port-9000 listener first.');

            return self::FAILURE;
        }
        stream_set_blocking($server, false);
        $this->info('GPS TCP DIAGNOSTIC LISTENER '.$address);
        $this->warn('DIAGNOSTIC ONLY: no database storage or broadcasts. ACK allowlist: '.($imei ?: 'none; observation only'));
        $this->info('Live connections, bytes, login, heartbeat and location status appear below. Ctrl+C stops.');
        $clients = [];
        $totals = ['connections' => 0, 'reads' => 0, 'bytes' => 0, 'logins' => 0, 'heartbeats' => 0, 'locations' => 0, 'valid_fixes' => 0];
        $started = time();
        $lastStatus = 0;
        $lastData = null;
        $close = function (int $id, string $reason) use (&$clients): void {
            if (! isset($clients[$id])) {
                return;
            }
            $this->record('DISCONNECTED', ['peer' => $clients[$id]['peer'], 'imei' => $clients[$id]['imei'], 'reason' => $reason,
                'received_bytes' => $clients[$id]['bytes'], 'packets' => $clients[$id]['packets']]);
            fclose($clients[$id]['socket']);
            unset($clients[$id]);
        };
        try {
            while ($seconds === 0 || time() - $started < $seconds) {
                $read = [$server];
                $write = [];
                $except = null;
                foreach ($clients as $client) {
                    $read[] = $client['socket'];
                    if ($client['out'] !== '') {
                        $write[] = $client['socket'];
                    }
                }
                if (@stream_select($read, $write, $except, 1) === false) {
                    break;
                }
                foreach ($read as $socket) {
                    if ($socket === $server) {
                        $socket = @stream_socket_accept($server, 0, $peer);
                        if (! $socket) {
                            continue;
                        }
                        if (count($clients) >= 32) {
                            fclose($socket);

                            continue;
                        }
                        stream_set_blocking($socket, false);
                        $clients[(int) $socket] = ['socket' => $socket, 'peer' => $peer, 'in' => '', 'out' => '',
                            'imei' => null, 'activity' => time(), 'bytes' => 0, 'packets' => 0, 'window' => time(), 'window_bytes' => 0];
                        $totals['connections']++;
                        $this->record('CONNECTED', ['peer' => $peer, 'connected_now' => count($clients)]);

                        continue;
                    }
                    $id = (int) $socket;
                    try {
                        $bytes = @fread($socket, 8192);
                        if ($bytes === false || ($bytes === '' && feof($socket))) {
                            $close($id, 'peer_closed');

                            continue;
                        }
                        if ($bytes === '') {
                            continue;
                        }
                        $lastData = now()->timezone('Asia/Kolkata')->format('Y-m-d H:i:s T');
                        $totals['reads']++;
                        $totals['bytes'] += strlen($bytes);
                        $clients[$id]['bytes'] += strlen($bytes);
                        $clients[$id]['activity'] = time();
                        if (time() - $clients[$id]['window'] >= 60) {
                            $clients[$id]['window'] = time();
                            $clients[$id]['window_bytes'] = 0;
                        }
                        $clients[$id]['window_bytes'] += strlen($bytes);
                        if ($clients[$id]['window_bytes'] > 1048576) {
                            throw new InvalidArgumentException('rate_limit');
                        }
                        $clients[$id]['in'] .= $bytes;
                        $this->record('DATA_RECEIVED', ['peer' => $clients[$id]['peer'], 'bytes' => strlen($bytes), 'buffer_bytes' => strlen($clients[$id]['in'])]);
                        if (strlen($clients[$id]['in']) > 65536 || strlen($clients[$id]['out']) > 65536) {
                            throw new InvalidArgumentException('buffer_limit');
                        }
                        $frames = 0;
                        while (($frame = $framer->extract($clients[$id]['in'])) !== null) {
                            if (++$frames > 64) {
                                throw new InvalidArgumentException('frame_limit');
                            }
                            $clients[$id]['packets']++;
                            if (substr($frame, 0, 2) === "\x79\x79") {
                                $this->record('EXTENDED_PACKET_CAPTURED', ['peer' => $clients[$id]['peer'],
                                    'imei' => $clients[$id]['imei'], 'type' => $framer->type($frame), 'bytes' => strlen($frame),
                                    'checksum' => 'valid', 'hex' => $clients[$id]['imei'] === $imei ? strtoupper(bin2hex(substr($frame, 0, 256))) : null,
                                    'sha256' => hash('sha256', $frame), 'note' => 'framing verified; payload not decoded; no guessed ACK']);

                                continue;
                            }
                            try {
                                $packet = $protocol->decode($frame, $clients[$id]['imei'] !== null);
                            } catch (InvalidArgumentException $e) {
                                $this->record('PACKET_NOT_DECODED', ['peer' => $clients[$id]['peer'], 'type' => $framer->type($frame),
                                    'bytes' => strlen($frame), 'reason' => $e->getMessage(),
                                    'hex' => $clients[$id]['imei'] === $imei ? strtoupper(bin2hex(substr($frame, 0, 256))) : null,
                                    'sha256' => hash('sha256', $frame)]);

                                continue;
                            }
                            if ($packet->identifier !== null) {
                                $this->record('LOGIN', ['peer' => $clients[$id]['peer'], 'imei' => $packet->identifier, 'checksum' => 'valid',
                                    'diagnostic_allowed' => $imei !== '' && hash_equals($imei, $packet->identifier)]);
                                $totals['logins']++;
                                if ($imei === '' || ! hash_equals($imei, $packet->identifier)) {
                                    $close($id, 'not_diagnostic_allowlisted');
                                    break;
                                }
                                $clients[$id]['imei'] = $packet->identifier;
                            }
                            if ($packet->type === 'heartbeat') {
                                $totals['heartbeats']++;
                                $this->record('HEARTBEAT', ['imei' => $clients[$id]['imei'], ...$packet->events]);
                            }
                            foreach ($packet->locations as $location) {
                                $totals['locations']++;
                                if ($location->gpsFix) {
                                    $totals['valid_fixes']++;
                                }
                                $this->record($location->gpsFix ? 'GPS_LOCATION' : 'GPS_NO_FIX', ['imei' => $clients[$id]['imei'], ...$location->toArray()]);
                            }
                            if ($packet->ack !== '') {
                                $clients[$id]['out'] .= $packet->ack;
                                $this->record('ACK_QUEUED', ['imei' => $clients[$id]['imei'], 'type' => $packet->type,
                                    'hex' => strtoupper(bin2hex($packet->ack))]);
                            }
                        }
                    } catch (Throwable $e) {
                        $close($id, $e instanceof InvalidArgumentException ? $e->getMessage() : 'socket_error');
                    }
                }
                foreach ($write as $socket) {
                    $id = (int) $socket;
                    if (! isset($clients[$id])) {
                        continue;
                    }
                    try {
                        $written = @fwrite($socket, $clients[$id]['out']);
                    } catch (Throwable) {
                        $written = false;
                    }
                    if ($written === false) {
                        $close($id, 'write_failed');
                    } elseif ($written > 0) {
                        $clients[$id]['out'] = substr($clients[$id]['out'], $written);
                        $this->record('ACK_SENT', ['imei' => $clients[$id]['imei'], 'bytes' => $written]);
                    }
                }
                foreach ($clients as $id => $client) {
                    if (time() - $client['activity'] > 240) {
                        $close($id, 'idle_timeout');
                    }
                }
                if (time() - $lastStatus >= 15) {
                    $this->record('STATUS', ['connected_now' => count($clients), ...$totals, 'last_data' => $lastData]);
                    $lastStatus = time();
                }
            }
        } finally {
            foreach (array_keys($clients) as $id) {
                $close($id, 'listener_stopped');
            }
            fclose($server);
        }

        return self::SUCCESS;
    }

    private function record(string $event, array $fields): void
    {
        $this->output->writeln(json_encode(['time' => now()->timezone('Asia/Kolkata')->format('Y-m-d H:i:s T'),
            'event' => $event, ...$fields], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
    }
}
