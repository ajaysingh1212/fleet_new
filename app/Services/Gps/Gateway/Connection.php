<?php

namespace App\Services\Gps\Gateway;

use Illuminate\Support\Str;

final class Connection
{
    public string $buffer = '';

    public ?Protocol $protocol = null;

    public ?array $device = null;

    public ?string $identifier = null;

    public readonly string $id;

    public readonly string $connectedAt;

    public function __construct(public readonly string $peer)
    {
        $this->id = (string) Str::uuid();
        $this->connectedAt = now()->utc()->format('Y-m-d H:i:s');
    }
}
