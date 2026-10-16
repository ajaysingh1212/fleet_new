<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Opt-in real Redis + TCP transport test. No SQL or production device writes. */
class HardwareGpsNetworkTest extends TestCase
{
    public function test_fragmented_login_coalesced_packets_reconnect_and_process_restart(): void
    {
        if (getenv('GPS_NETWORK_TESTS') !== '1') {
            $this->markTestSkipped('Set GPS_NETWORK_TESTS=1 with a local test Redis instance.');
        }
        $prefix = 'gps-network-test-'.bin2hex(random_bytes(8)).':';
        config(['database.redis.client' => 'predis', 'database.redis.options.prefix' => $prefix,
            'cache.prefix' => $prefix, 'gps.cache_store' => 'redis', 'gps.queue_connection' => 'redis']);
        Redis::purge('default');
        Redis::purge('cache');
        Cache::purge('redis');
        Cache::store('redis')->put('gps.hardware.auth.356307042441013', [
            'id' => 1, 'active' => true, 'protocol' => 'teltonika', 'version' => '8', 'vehicle_id' => null,
        ], 60);
        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $port = (int) substr(strrchr(stream_socket_get_name($reservation, false), ':'), 1);
        fclose($reservation);
        $fixture = hex2bin(trim(file_get_contents(base_path('tests/Fixtures/gps/teltonika-codec8-official.hex'))));
        $process = null;
        $socket = null;
        try {
            for ($restart = 0; $restart < 2; $restart++) {
                $process = new Process([PHP_BINARY, 'artisan', 'gps:listen', '--host=127.0.0.1', '--port='.$port], base_path(), [
                    'APP_ENV' => 'testing', 'REDIS_CLIENT' => 'predis', 'REDIS_PREFIX' => $prefix, 'CACHE_PREFIX' => $prefix,
                    'GPS_CACHE_STORE' => 'redis', 'GPS_QUEUE_CONNECTION' => 'redis',
                ]);
                $process->start();
                $deadline = microtime(true) + 10;
                while (! str_contains($process->getOutput(), 'GPS hardware gateway:')) {
                    if (! $process->isRunning() || microtime(true) > $deadline) {
                        $this->fail('Gateway startup failed: '.$process->getOutput());
                    }
                    usleep(20000);
                }
                for ($connection = 0; $connection < 2; $connection++) {
                    $socket = stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 3);
                    $this->assertIsResource($socket);
                    stream_set_timeout($socket, 5);
                    fwrite($socket, "\x00");
                    usleep(20000);
                    fwrite($socket, "\x0f356307042441013".$fixture.$fixture);
                    $reply = '';
                    while (strlen($reply) < 9 && ! feof($socket)) {
                        $chunk = fread($socket, 9 - strlen($reply));
                        if ($chunk === false || $chunk === '') {
                            break;
                        }
                        $reply .= $chunk;
                    }
                    $this->assertSame("\x01".pack('NN', 1, 1), $reply);
                    fclose($socket);
                    $socket = null;
                }
                $process->stop(3);
                $process = null;
            }
            $this->assertGreaterThanOrEqual(12, Queue::connection('redis')->size(config('gps.queue')));
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
            if ($process?->isRunning()) {
                $process->stop();
            }
            Cache::store('redis')->forget('gps.hardware.auth.356307042441013');
            Cache::store('redis')->forget('gps.hardware.health');
            // This queue uses an isolated, random Redis prefix unique to this test.
            Queue::connection('redis')->clear(config('gps.queue'));
        }
    }
}
