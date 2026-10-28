<?php

namespace App\Services\Gps\Gateway;

use App\Jobs\CloseHardwareSession;
use App\Jobs\DeliverHardwarePacket;
use App\Jobs\ProcessHardwarePacket;
use App\Jobs\StoreHardwareDiagnostic;
use App\Services\Gps\HardwareConnectionState;
use App\Services\Gps\HardwareTelemetry;
use Carbon\CarbonImmutable;
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
            $receivedAt = $connection->receivedAt ?? HardwareTelemetry::stamp();
            $traceId = bin2hex(random_bytes(8));
            HardwareTelemetry::phase('packet_received', ['trace_id' => $traceId, 'received_at' => $receivedAt,
                'session_id' => $connection->id, 'bytes' => strlen($frame)]);
            if (++$frames > 64) {
                throw new InvalidArgumentException('frame_rate_limit');
            }
            $binaryLogin = in_array(substr($frame, 0, 2), ["\x78\x78", "\x79\x79"], true)
                && ord($frame[substr($frame, 0, 2) === "\x79\x79" ? 4 : 3]) === 1;
            $packet = $connection->protocol->decode($frame, $connection->device !== null && ! $binaryLogin);
            if ($packet->identifier !== null) {
                if ($connection->identifier !== null && $packet->identifier !== $connection->identifier) {
                    throw new InvalidArgumentException('device_identity_changed');
                }
                $connection->identifier = $packet->identifier;
            }
            $device = $connection->identifier ? $this->allowlist->find($connection->identifier) : null;
            // IMEI-only GT06/PT06 login is identical. Only an allowlisted
            // registration may select a different compatible adapter profile.
            if ($device && $device['active'] && $packet->type === 'login' && $connection->device === null
                && $device['protocol'] !== $connection->protocol->name()) {
                $registered = $this->registry->named($device['protocol']);
                if ($registered) {
                    $verified = $registered->decode($frame, false);
                    if ($verified->type !== 'login' || $verified->identifier !== $connection->identifier) {
                        throw new InvalidArgumentException('unauthorized_device');
                    }
                    $connection->protocol = $registered;
                    $packet = $verified;
                }
            }
            if (! $device || ! $device['active'] || $device['protocol'] !== $connection->protocol->name()
                || ($packet->version !== 'login' && $packet->version !== $device['version'])) {
                throw new InvalidArgumentException('unauthorized_device');
            }
            $connection->device = $device;
            // Redis enqueue completes before acknowledging. On overload/failure close
            // without ACK, letting the tracker retry. No SQL in the socket loop.
            $queue = Queue::connection(config('gps.queue_connection'));
            $queueName = config('gps.live_first') ? config('gps.live_queue') : config('gps.queue');
            if ($queue->size($queueName) >= config('gps.max_queue')) {
                throw new InvalidArgumentException('queue_full');
            }
            $job = new ProcessHardwarePacket($device['id'], $connection->identifier, $connection->id,
                $connection->connectedAt, $connection->peer, $connection->protocol->name(), $packet->version,
                $packet->type, hash('sha256', $frame), array_map(fn ($location) => $location->toArray(), $packet->locations),
                $packet->events, CarbonImmutable::parse($receivedAt)->utc()->format('Y-m-d H:i:s'), $device['vehicle_id'], true, $receivedAt, $traceId);
            $queue->push(config('gps.live_first') ? new DeliverHardwarePacket($job, config('gps.queue_connection')) : $job, '', $queueName);
            app(HardwareConnectionState::class)->touch($device['id'], $connection->id);
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
        $queueName = config('gps.live_first') ? config('gps.persistence_queue') : config('gps.queue');
        if ($queue->size($queueName) >= config('gps.max_queue')) {
            return;
        }
        $queue->push(new StoreHardwareDiagnostic([
            'reason' => $reason, 'protocol' => $connection->protocol?->name(), 'identifier' => $connection->identifier,
            'remote_ip' => $connection->peer, 'packet_hash' => hash('sha256', $bytes),
            'byte_count' => strlen($bytes), 'received_at' => now()->utc()->format('Y-m-d H:i:s'),
        ]), '', $queueName);
    }

    public function disconnected(Connection $connection): void
    {
        if (! $connection->device) {
            return;
        }
        app(HardwareConnectionState::class)->close($connection->device['id'], $connection->id);
        $queue = Queue::connection(config('gps.queue_connection'));
        $queueName = config('gps.live_first') ? config('gps.persistence_queue') : config('gps.queue');
        if ($queue->size($queueName) >= config('gps.max_queue')) {
            return;
        }
        $queue->push(new CloseHardwareSession([
            'id' => $connection->id, 'gps_device_id' => $connection->device['id'],
            'protocol' => $connection->protocol->name(), 'remote_ip' => $connection->peer,
            'connected_at' => $connection->connectedAt, 'last_packet_at' => $connection->connectedAt,
            'disconnected_at' => now()->utc()->format('Y-m-d H:i:s'), 'authenticated' => true,
        ]), '', $queueName);
    }
}
