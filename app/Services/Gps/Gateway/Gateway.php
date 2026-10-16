<?php

namespace App\Services\Gps\Gateway;

use App\Jobs\CloseHardwareSession;
use App\Jobs\ProcessHardwarePacket;
use App\Jobs\StoreHardwareDiagnostic;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;

final class Gateway
{
    public function __construct(private ProtocolRegistry $registry, private DeviceAllowlist $allowlist) {}

    public function feed(Connection $connection, string $bytes): string
    {
        $connection->buffer .= $bytes;
        if (strlen($connection->buffer) > config('gps.max_buffer')) {
            throw new InvalidArgumentException('buffer_limit');
        }
        $connection->protocol ??= $this->registry->detect($connection->buffer);
        if (! $connection->protocol) {
            return '';
        }
        $out = '';
        $frames = 0;
        while (($frame = $connection->protocol->extract($connection->buffer, $connection->device !== null)) !== null) {
            if (++$frames > 64) {
                throw new InvalidArgumentException('frame_rate_limit');
            }
            $packet = $connection->protocol->decode($frame, $connection->device !== null);
            if ($packet->identifier !== null) {
                $connection->identifier = $packet->identifier;
            }
            $device = $connection->identifier ? $this->allowlist->find($connection->identifier) : null;
            if (! $device || ! $device['active'] || $device['protocol'] !== $connection->protocol->name()
                || ($packet->version !== 'login' && $packet->version !== $device['version'])) {
                throw new InvalidArgumentException('unauthorized_device');
            }
            $connection->device = $device;
            // Redis enqueue completes before acknowledging. On overload/failure close
            // without ACK, letting the tracker retry. No SQL in the socket loop.
            $queue = Queue::connection(config('gps.queue_connection'));
            if ($queue->size(config('gps.queue')) >= config('gps.max_queue')) {
                throw new InvalidArgumentException('queue_full');
            }
            $job = new ProcessHardwarePacket($device['id'], $connection->identifier, $connection->id,
                $connection->connectedAt, $connection->peer, $connection->protocol->name(), $packet->version,
                $packet->type, hash('sha256', $frame), array_map(fn ($location) => $location->toArray(), $packet->locations),
                $packet->events, now()->utc()->format('Y-m-d H:i:s'), $device['vehicle_id']);
            $queue->push($job, '', config('gps.queue'));
            $out .= $packet->ack;
        }

        return $out;
    }

    public function diagnostic(Connection $connection, string $bytes, string $reason): void
    {
        if (! config('gps.pending_diagnostics')) {
            return;
        }
        $cache = $this->allowlist->store();
        if (! $cache->add('gps.diag.'.hash('sha256', $connection->peer), 1, 60)) {
            return;
        }
        $queue = Queue::connection(config('gps.queue_connection'));
        if ($queue->size(config('gps.queue')) >= config('gps.max_queue')) {
            return;
        }
        $queue->push(new StoreHardwareDiagnostic([
            'reason' => $reason, 'protocol' => $connection->protocol?->name(), 'identifier' => $connection->identifier,
            'remote_ip' => $connection->peer, 'packet_hash' => hash('sha256', $bytes),
            'byte_count' => strlen($bytes), 'received_at' => now()->utc()->format('Y-m-d H:i:s'),
        ]), '', config('gps.queue'));
    }

    public function disconnected(Connection $connection): void
    {
        if (! $connection->device) {
            return;
        }
        $queue = Queue::connection(config('gps.queue_connection'));
        if ($queue->size(config('gps.queue')) >= config('gps.max_queue')) {
            return;
        }
        $queue->push(new CloseHardwareSession([
            'id' => $connection->id, 'gps_device_id' => $connection->device['id'],
            'protocol' => $connection->protocol->name(), 'remote_ip' => $connection->peer,
            'connected_at' => $connection->connectedAt, 'last_packet_at' => $connection->connectedAt,
            'disconnected_at' => now()->utc()->format('Y-m-d H:i:s'), 'authenticated' => true,
        ]), '', config('gps.queue'));
    }
}
