<?php

namespace App\Services\Gps\Gateway;

use InvalidArgumentException;

final class ProtocolRegistry
{
    /** @var array<Protocol> */
    private array $adapters;

    public function __construct()
    {
        $this->adapters = array_map(fn ($class) => app($class), config('gps.adapters'));
    }

    public function detect(string $buffer): ?Protocol
    {
        $matches = [];
        $incomplete = false;
        foreach ($this->adapters as $adapter) {
            $match = $adapter->detect($buffer);
            if ($match === true) {
                $matches[] = $adapter;
            }
            if ($match === null) {
                $incomplete = true;
            }
        }
        if (count($matches) === 1 && ! $incomplete) {
            return $matches[0];
        }
        if ($incomplete && count($matches) <= 1) {
            return null;
        }

        throw new InvalidArgumentException('unknown_protocol');
    }
}
