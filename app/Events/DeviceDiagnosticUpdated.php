<?php

namespace App\Events;

use App\Models\DiagnosticLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceDiagnosticUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public DiagnosticLog $diagnosticLog) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('diagnostics.'.$this->diagnosticLog->user_id),
            new PrivateChannel('admin.diagnostics'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DeviceDiagnosticUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->diagnosticLog->uuid,
            'user_id' => $this->diagnosticLog->user_id,
            'event_type' => $this->diagnosticLog->event_type->value,
            'occurred_at' => $this->diagnosticLog->occurred_at?->toISOString(),
            'received_at' => $this->diagnosticLog->created_at?->toISOString(),
            'metadata' => [
                'network_type' => $this->diagnosticLog->network_type,
                'battery_level' => $this->diagnosticLog->battery_level,
                'reason' => $this->diagnosticLog->reason,
                'duration_seconds' => $this->diagnosticLog->duration_seconds,
            ],
        ];
    }
}
