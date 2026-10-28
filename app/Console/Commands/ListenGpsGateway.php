<?php

namespace App\Console\Commands;

use App\Services\Gps\Gateway\Connection;
use App\Services\Gps\Gateway\DeviceAllowlist;
use App\Services\Gps\Gateway\Gateway;
use App\Services\Gps\Gateway\TcpListenerLease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Throwable;

class ListenGpsGateway extends Command
{
    protected $signature = 'gps:listen {--host=} {--port=9000} {--cert=} {--key=} {--once} {--mobile} {--diagnostic} {--diagnostic-imei= : Comma-separated diagnostic IMEIs} {--format=pretty} {--diagnostic-protocol=pt06} {--persist : Store diagnostic packets locally for registered devices} {--observe-only : Disable diagnostic database storage even when enabled in local settings} {--registered : Accept all active SQL registrations with persistence}';

    protected $description = 'Run the bounded hardware TCP gateway (or legacy TG1 with --mobile / --cert)';

    private bool $stopping = false;

    public function handle(Gateway $gateway, DeviceAllowlist $allowlist): int
    {
        if ($this->option('diagnostic')) {
            if ($this->option('observe-only') && ($this->option('persist') || $this->option('registered'))) {
                $this->error('--observe-only cannot be combined with --persist or --registered.');

                return self::FAILURE;
            }
            $persist = ! $this->option('observe-only')
                && ($this->option('persist') || config('gps.diagnostic_persist', false));

            return $this->call('gps:diagnose', ['--host' => $this->option('host') ?: '0.0.0.0',
                '--port' => $this->option('port'), '--imei' => $this->option('diagnostic-imei') ?: '', '--format' => $this->option('format'),
                '--protocol' => $this->option('diagnostic-protocol'), '--persist' => (bool) $persist, '--registered' => (bool) $this->option('registered')]);
        }
        // Preserve the existing TLS invocation and all TG1 authentication semantics.
        if ($this->option('mobile') || $this->option('cert')) {
            $options = ['--host' => $this->option('host') ?: '127.0.0.1', '--port' => $this->option('port')];
            foreach (['cert', 'key', 'once'] as $key) {
                if ($this->option($key)) {
                    $options['--'.$key] = $this->option($key);
                }
            }

            return $this->call('gps:listen-mobile', $options);
        }
        $host = $this->option('host') ?: config('gps.host');
        $port = filter_var($this->option('port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (! filter_var($host, FILTER_VALIDATE_IP) || ! $port || $this->option('key')) {
            $this->error('Use a literal bind IP, port 1–65535, and --cert with --key for TG1 TLS.');

            return self::FAILURE;
        }
        if (config('queue.connections.'.config('gps.queue_connection').'.driver') !== 'redis'
            || config('cache.stores.'.config('gps.cache_store').'.driver') !== 'redis') {
            $this->error('The gateway requires Redis queue and cache drivers (defaults: gps-redis).');

            return self::FAILURE;
        }
        try {
            $allowlist->store()->put('gps.hardware.health', now()->timestamp, 15);
            Queue::connection(config('gps.queue_connection'))->size(config('gps.queue'));
        } catch (Throwable) {
            $this->error('Redis cache/queue unavailable. Check private Redis connectivity and configuration.');

            return self::FAILURE;
        }
        try {
            $listenerLease = TcpListenerLease::acquire($port);
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $address = 'tcp://'.(str_contains($host, ':') ? '['.$host.']' : $host).':'.$port;
        $context = stream_context_create(['socket' => ['tcp_nodelay' => true]]);
        $server = @stream_socket_server($address, $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if (! $server) {
            $this->error('Could not bind gateway: '.$error);

            return self::FAILURE;
        }
        stream_set_blocking($server, false);
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->stopping = true);
            pcntl_signal(SIGINT, fn () => $this->stopping = true);
        }
        $clients = [];
        $handled = false;
        $this->info('GPS hardware gateway: '.$address);
        $close = function ($id) use (&$clients, $gateway): void {
            if (! isset($clients[$id])) {
                return;
            }
            try {
                $gateway->disconnected($clients[$id]['state']);
            } catch (Throwable) {
            }
            fclose($clients[$id]['socket']);
            unset($clients[$id]);
        };
        $healthAt = 0;
        try {
            while (! $this->stopping) {
                if (time() - $healthAt >= 5) {
                    $allowlist->store()->put('gps.hardware.health', time(), 15);
                    $healthAt = time();
                }
                $read = [$server];
                $write = [];
                foreach ($clients as $client) {
                    $read[] = $client['socket'];
                    if ($client['out'] !== '') {
                        $write[] = $client['socket'];
                    }
                }
                $except = null;
                if (@stream_select($read, $write, $except, 1) === false) {
                    if ($this->stopping) {
                        break;
                    }
                    throw new \RuntimeException('socket_select_failed');
                }
                foreach ($read as $socket) {
                    if ($socket === $server) {
                        $accepted = @stream_socket_accept($server, 0, $peer);
                        if (! $accepted) {
                            continue;
                        }
                        $ip = preg_replace('/^\[|\]:[0-9]+$|:[0-9]+$/', '', $peer);
                        $sameIp = count(array_filter($clients, fn ($c) => $c['state']->peer === $ip));
                        if (count($clients) >= config('gps.max_clients') || $sameIp >= 32) {
                            fclose($accepted);

                            continue;
                        }
                        stream_set_blocking($accepted, false);
                        $clients[(int) $accepted] = ['socket' => $accepted, 'state' => new Connection($ip),
                            'out' => '', 'activity' => time(), 'started' => time(), 'window' => time(), 'bytes' => 0, 'reads' => 0];

                        continue;
                    }
                    $id = (int) $socket;
                    try {
                        $bytes = @fread($socket, 8192);
                        if ($bytes === false || ($bytes === '' && feof($socket))) {
                            $close($id);

                            continue;
                        }
                        if ($bytes === '') {
                            continue;
                        }
                        if (time() - $clients[$id]['window'] >= 60) {
                            $clients[$id]['window'] = time();
                            $clients[$id]['bytes'] = 0;
                            $clients[$id]['reads'] = 0;
                        }
                        $clients[$id]['bytes'] += strlen($bytes);
                        $clients[$id]['reads']++;
                        if ($clients[$id]['bytes'] > 1048576 || $clients[$id]['reads'] > 600) {
                            throw new InvalidArgumentException('rate_limited');
                        }
                        $clients[$id]['activity'] = time();
                        $clients[$id]['out'] .= $gateway->feed($clients[$id]['state'], $bytes);
                        if (strlen($clients[$id]['out']) > config('gps.max_buffer')) {
                            throw new InvalidArgumentException('output_limit');
                        }
                        $handled = $clients[$id]['state']->device !== null;
                    } catch (Throwable $e) {
                        $reason = $e instanceof InvalidArgumentException ? $e->getMessage() : 'service_unavailable';
                        try {
                            $gateway->diagnostic($clients[$id]['state'], $bytes ?? '', $reason);
                        } catch (Throwable) {
                        }
                        // Error text and raw payloads can contain secrets; log only a safe category.
                        $this->warn('Connection closed: '.$reason);
                        $close($id);
                    }
                }
                // Send newly queued ACKs in this pass, rather than waiting for
                // the next select timeout just because they were not pending before read.
                foreach ($clients as $client) {
                    if ($client['out'] === '') {
                        continue;
                    }
                    $socket = $client['socket'];
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
                        $close($id);
                    } else {
                        $clients[$id]['out'] = substr($clients[$id]['out'], $written);
                    }
                }
                foreach ($clients as $id => $client) {
                    if ((! $client['state']->device && time() - $client['started'] > 10)
                        || time() - $client['activity'] > config('gps.idle_seconds')) {
                        $close($id);
                    }
                }
                if ($this->option('once') && $handled && ! array_filter($clients, fn ($c) => $c['out'] !== '')) {
                    break;
                }
            }
        } catch (Throwable) {
            $this->error('Gateway stopped after a service/socket failure; process manager should restart it.');

            return self::FAILURE;
        } finally {
            foreach (array_keys($clients) as $id) {
                $close($id);
            }
            fclose($server);
            try {
                $allowlist->store()->forget('gps.hardware.health');
            } catch (Throwable) {
            }
        }

        return self::SUCCESS;
    }
}
