<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class ServeWithGpsTest extends TestCase
{
    private function reservePort()
    {
        return stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    }

    private function port($socket): int
    {
        return (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    }

    private function server(int $gpsPort, int $httpPort, array $extra = []): Process
    {
        return new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1',
            '--port='.$httpPort, '--no-reload', ...$extra], base_path(), [
                'GPS_SERVE_AUTOSTART' => 'true', 'GPS_HOST' => '127.0.0.1', 'GPS_PORT' => (string) $gpsPort,
            ]);
    }

    private function waitForServer(Process $process): void
    {
        $deadline = microtime(true) + 15;
        while (! str_contains($process->getOutput(), 'Server running on')) {
            if (! $process->isRunning() || microtime(true) > $deadline) {
                $this->fail('Server did not start: '.$process->getOutput().$process->getErrorOutput());
            }
            usleep(20000);
        }
    }

    public function test_serve_opens_gps_and_http_and_releases_both_on_stop(): void
    {
        $gps = $this->reservePort();
        $http = $this->reservePort();
        $gpsPort = $this->port($gps);
        $httpPort = $this->port($http);
        fclose($gps);
        fclose($http);
        $process = $this->server($gpsPort, $httpPort);
        try {
            $process->start();
            $this->waitForServer($process);
            $this->assertStringContainsString('GPS TCP listener running', $process->getOutput());
            foreach ([$gpsPort, $httpPort] as $port) {
                $socket = stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 2);
                $this->assertIsResource($socket);
                fclose($socket);
            }
        } finally {
            $process->stop(2);
        }
        foreach ([$gpsPort, $httpPort] as $port) {
            $socket = @stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 0.2);
            if (is_resource($socket)) {
                fclose($socket);
                $this->fail('Child listener remained running on '.$port);
            }
            $this->assertFalse($socket);
        }
    }

    public function test_occupied_gps_port_is_reported_without_starting_http(): void
    {
        $gps = $this->reservePort();
        $http = $this->reservePort();
        $httpPort = $this->port($http);
        fclose($http);
        $process = $this->server($this->port($gps), $httpPort);
        try {
            $process->setTimeout(15)->run();
            $this->assertSame(1, $process->getExitCode());
            $this->assertStringContainsString('GPS listener could not start', $process->getOutput());
            $this->assertStringNotContainsString('Server running on', $process->getOutput());
            $this->assertIsResource($gps);
        } finally {
            $process->stop();
            fclose($gps);
        }
    }

    public function test_http_only_option_leaves_an_existing_gps_listener_untouched(): void
    {
        $gps = $this->reservePort();
        $http = $this->reservePort();
        $httpPort = $this->port($http);
        fclose($http);
        $process = $this->server($this->port($gps), $httpPort, ['--without-gps']);
        try {
            $process->start();
            $this->waitForServer($process);
            $this->assertStringNotContainsString('GPS TCP listener running', $process->getOutput());
        } finally {
            $process->stop(2);
            fclose($gps);
        }
    }
}
