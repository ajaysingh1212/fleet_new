<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;

class WriteAuditLogJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $userId,
        public string $event,
        public string $auditableType,
        public int $auditableId,
        public ?array $oldValues,
        public ?array $newValues,
    ) {}

    public function handle(): void
    {
        AuditLog::create([
            'user_id' => $this->userId,
            'event' => $this->event,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'old_values' => Arr::except($this->oldValues ?? [], ['password', 'remember_token']),
            'new_values' => Arr::except($this->newValues ?? [], ['password', 'remember_token']),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'device' => substr((string) request()?->userAgent(), 0, 255),
            'country' => null,
            'created_at' => now(),
        ]);
    }
}
