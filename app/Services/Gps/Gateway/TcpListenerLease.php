<?php

namespace App\Services\Gps\Gateway;

use RuntimeException;

/** Windows can otherwise allow two PHP stream servers to share one port. */
final class TcpListenerLease
{
    private function __construct(private $handle) {}

    public static function acquire(int $port): self
    {
        $handle = @fopen(storage_path('framework/gps-port-'.$port.'.lock'), 'c');
        if (! $handle) {
            throw new RuntimeException('Cannot create the GPS listener lock. Check storage/framework permissions.');
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('A Laravel GPS listener is already running on port '.$port.'. Use its terminal or stop it with Ctrl+C before starting another.');
        }

        return new self($handle);
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
    }
}
