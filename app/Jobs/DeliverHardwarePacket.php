<?php

namespace App\Jobs;

use App\Events\HardwarePacketLive;
use App\Models\GpsDevice;
use App\Services\Gps\HardwareFleet;
use App\Services\Gps\HardwareLiveState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

/** Durable input -> live projection/broadcast attempt -> independent SQL history queue. */
class DeliverHardwarePacket implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public string $receivedPreciselyAt;

    public function __construct(public ProcessHardwarePacket $packet, public string $persistenceConnection, public bool $publish = true)
    {
        $this->receivedPreciselyAt = now()->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    public function backoff(): array
    {
        return [1, 5, 15, 30];
    }

    public function handle(HardwareLiveState $live, HardwareFleet $fleet): void
    {
        $packet = $this->packet;
        $device = GpsDevice::with('vehicle')->find($packet->deviceId);
        // Recheck administrative authorization before exposing anything live.
        if (! $device || $device->status !== 'active' || $device->identifier !== $packet->identifier
            || $device->vehicle_id !== $packet->vehicleId || $device->protocol !== $packet->protocol
            || ($packet->version !== 'login' && $device->protocol_version !== $packet->version)) {
            return;
        }
        try {
            $payload = $live->project($live->baseline($device, $fleet), $packet, $this->receivedPreciselyAt);
            if ($this->publish) HardwarePacketLive::dispatch($payload);
        } catch (Throwable) {
            // A closed browser / unavailable Reverb must not stop GPS history.
            // Reconnect snapshots use the live cache while SQL catches up.
            Log::warning('gps_live_broadcast_failed', ['device_id' => $device->id]);
        }
        $packet->broadcast = false;
        // A queue failure retries this durable input job. Existing SQL dedupe
        // makes a repeated delivery safe, including crashes between queues.
        Queue::connection($this->persistenceConnection)->push($packet, '', config('gps.persistence_queue'));
    }
}
