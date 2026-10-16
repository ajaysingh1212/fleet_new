<?php

namespace Tests\Unit;

use App\Console\Commands\ListenMobileGps;
use ErrorException;
use Tests\TestCase;

class MobileGpsSocketReadTest extends TestCase
{
    public function test_ssl_read_warning_does_not_escape_as_an_exception(): void
    {
        $command = new class extends ListenMobileGps {
            public function read($socket): string|false { return $this->readClient($socket); }
        };
        stream_wrapper_register('gpsreadfailure', FailingGpsReadStream::class);
        $socket = fopen('gpsreadfailure://test', 'r');
        set_error_handler(static function ($severity, $message, $file, $line) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $this->assertFalse($command->read($socket));
        } finally {
            restore_error_handler();
            fclose($socket);
            stream_wrapper_unregister('gpsreadfailure');
        }
        $healthy = fopen('php://memory', 'r+');
        fwrite($healthy, 'healthy-packet');
        rewind($healthy);
        $this->assertSame('healthy-packet', $command->read($healthy));
        fclose($healthy);
    }
}

class FailingGpsReadStream
{
    public $context;
    public function stream_open($path, $mode, $options, &$openedPath): bool { return true; }
    public function stream_read($count): string|false
    {
        trigger_error('SSL: An existing connection was forcibly closed by the remote host', E_USER_WARNING);
        return false;
    }
    public function stream_eof(): bool { return false; }
    public function stream_stat(): array { return []; }
}
