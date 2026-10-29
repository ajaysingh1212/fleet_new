<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

class ServeWithGps extends ServeCommand
{
    public function handle()
    {
        if ($this->option('without-gps') || ! config('gps.serve_autostart')) {
            return parent::handle();
        }

        // Windows may allow a second bind when the existing socket uses
        // SO_REUSEADDR (including test_listener.py). Do not share that port.
        $probeHost = match (config('gps.host')) {
            '0.0.0.0' => '127.0.0.1',
            '::' => '::1',
            default => config('gps.host'),
        };
        $probeHost = str_contains($probeHost, ':') ? '['.$probeHost.']' : $probeHost;
        $existing = @stream_socket_client('tcp://'.$probeHost.':'.config('gps.port'), $errno, $error, 0.3);
        if (is_resource($existing)) {
            fclose($existing);
            $this->error('GPS listener could not start: port '.config('gps.port').' is already in use. Stop test_listener.py / the existing receiver, or use --without-gps if it is managed separately.');

            return self::FAILURE;
        }

        // Match the local receiver used by tools/start-gps-realtime.ps1.
        $arguments = [PHP_BINARY, base_path('artisan'), 'gps:listen',
            '--host='.config('gps.host'), '--port='.config('gps.port'),
            '--diagnostic', '--persist', '--registered', '--format=json'];
        if ($this->option('env')) {
            $arguments[] = '--env='.$this->option('env');
        }
        $receiver = new Process($arguments, base_path());
        $receiver->setTimeout(null);
        $log = fopen(storage_path('logs/gps-serve.log'), 'ab');
        $http = null;
        $realtime = [];
        $stop = static function () use ($receiver, &$http, &$realtime): void {
            $http?->stop(1);
            $receiver->stop(1);
            foreach (array_reverse($realtime) as $process) {
                $process->stop(5);
            }
        };
        // Laravel's signal handler exits directly, bypassing finally.
        register_shutdown_function($stop);

        try {
            $receiver->start(static function ($type, $buffer) use ($log): void {
                if (is_resource($log)) {
                    fwrite($log, $buffer);
                }
            });
            $deadline = microtime(true) + 10;
            while (! str_contains($receiver->getOutput(), 'GPS TCP DIAGNOSTIC LISTENER ')) {
                if (! $receiver->isRunning() || microtime(true) >= $deadline) {
                    $this->error('GPS listener could not start on '.config('gps.host').':'.config('gps.port').'. Stop test_listener.py or any other listener on this port, then retry.');
                    $this->output->write($receiver->getOutput().$receiver->getErrorOutput());

                    return self::FAILURE;
                }
                usleep(20000);
            }
            $this->components->info('GPS TCP listener running on '.config('gps.host').':'.config('gps.port').' (logs: storage/logs/gps-serve.log).');
            if (config('gps.serve_realtime')) {
                $this->startRealtimeServices($realtime);
            }

            // Keep Laravel's HTTP reload/port retry behavior while draining the
            // receiver pipes so device traffic cannot block the GPS process.
            $environmentFile = base_path('.env'.($this->option('env') ? '.'.$this->option('env') : ''));
            $hasEnvironment = file_exists($environmentFile);
            $modified = $hasEnvironment ? filemtime($environmentFile) : 0;
            do {
                $http = $this->startProcess($hasEnvironment);
                while ($http->isRunning()) {
                    if (! $receiver->isRunning()) {
                        $this->error('GPS listener stopped unexpectedly. See storage/logs/gps-serve.log.');

                        return self::FAILURE;
                    }
                    $receiver->clearOutput();
                    $receiver->clearErrorOutput();
                    foreach ($realtime as $name => $process) {
                        if (! $process->isRunning()) {
                            throw new RuntimeException($name.' stopped. See storage/logs/gps-serve-'.$name.'.log.');
                        }
                        $process->clearOutput();
                        $process->clearErrorOutput();
                    }
                    if ($hasEnvironment) {
                        clearstatcache(false, $environmentFile);
                    }
                    if (! $this->option('no-reload') && $hasEnvironment && filemtime($environmentFile) > $modified) {
                        $modified = filemtime($environmentFile);
                        $this->components->info('Environment modified. Restarting server...');
                        $http->stop(5);
                        $this->serverRunningHasBeenDisplayed = false;
                        $http = $this->startProcess($hasEnvironment);
                    }
                    usleep(100000);
                }
                $status = $http->getExitCode();
                if (! $status || ! $this->canTryAnotherPort()) {
                    return $status;
                }
                $this->portOffset++;
            } while (true);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $stop();
            if (is_resource($log)) {
                fclose($log);
            }
        }
    }

    private function startRealtimeServices(array &$processes): void
    {
        $host = config('reverb.servers.reverb.host', '127.0.0.1');
        $port = config('reverb.servers.reverb.port', 8080);
        $probeHost = match ($host) {
            '0.0.0.0' => '127.0.0.1',
            '::' => '::1',
            default => $host,
        };
        $probeHost = str_contains($probeHost, ':') ? '['.$probeHost.']' : $probeHost;
        $socket = @stream_socket_client('tcp://'.$probeHost.':'.$port, $errno, $error, 0.3);
        if (is_resource($socket)) {
            fclose($socket);
            $this->components->info('Using existing WebSocket listener on port '.$port.'.');
        } else {
            $this->startRealtimeService($processes, 'reverb', ['reverb:start', '--host='.$host, '--port='.$port], 'server on ');
        }

        // The existing worker pool owns this lock. Reuse it without stopping it
        // when this development server exits.
        $lock = fopen(storage_path('framework/gps-workers.lock'), 'c');
        if (! $lock) {
            throw new RuntimeException('Cannot open GPS worker lock. Check storage/framework permissions.');
        }
        $available = flock($lock, LOCK_EX | LOCK_NB);
        if ($available) {
            flock($lock, LOCK_UN);
        }
        fclose($lock);
        if ($available) {
            $this->startRealtimeService($processes, 'workers', ['gps:workers'], 'GPS worker supervisor ready');
        } else {
            $this->components->info('Using existing GPS worker supervisor.');
        }
    }

    private function startRealtimeService(array &$processes, string $name, array $arguments, string $ready): void
    {
        if ($this->option('env')) {
            $arguments[] = '--env='.$this->option('env');
        }
        $process = new Process([PHP_BINARY, base_path('artisan'), ...$arguments], base_path());
        $processes[$name] = $process;
        $process->setTimeout(null)->start(static function ($type, $buffer) use ($name): void {
            file_put_contents(storage_path('logs/gps-serve-'.$name.'.log'), $buffer, FILE_APPEND | LOCK_EX);
        });
        $deadline = microtime(true) + 15;
        while (! str_contains($process->getOutput(), $ready)) {
            if (! $process->isRunning() || microtime(true) >= $deadline) {
                throw new RuntimeException('Could not start GPS '.$name.'. See storage/logs/gps-serve-'.$name.'.log. '.$process->getOutput().$process->getErrorOutput());
            }
            usleep(20000);
        }
        $this->components->info('GPS '.$name.' started.');
    }

    protected function getOptions()
    {
        return [...parent::getOptions(),
            ['without-gps', null, InputOption::VALUE_NONE, 'Start only HTTP when a separate GPS receiver is already managed'],
        ];
    }
}
