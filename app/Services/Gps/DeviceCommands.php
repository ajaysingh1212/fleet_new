<?php

namespace App\Services\Gps;

use App\Models\GpsDevice;
use App\Services\Gps\Gateway\Protocols\Pt06CommandEncoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeviceCommands
{
    public function connected(int $deviceId, string $session): void
    {
        Cache::store(config('gps.live_cache_store'))->put('gps.command-transport.'.$deviceId, $session, 15);
    }

    public function session(int $deviceId): ?string
    {
        return Cache::store(config('gps.live_cache_store'))->get('gps.command-transport.'.$deviceId);
    }

    public function expire(int $deviceId): void
    {
        DB::table('gps_device_commands')->where('gps_device_id', $deviceId)->where('expires_at', '<=', now())
            ->whereIn('status', ['queued', 'writing', 'sent'])->update([
                'status' => 'timed_out', 'error' => 'No confirmed reply before timeout. Execution is unknown; command will not be retried.', 'updated_at' => now(),
            ]);
    }

    public function request(GpsDevice $device, int $userId, string $text, string $requestId, bool $confirmed): array
    {
        $parsed = app(Pt06CommandCatalog::class)->validate($text);
        if ($parsed['mutating'] && ! $confirmed) {
            throw ValidationException::withMessages(['confirmed' => 'Review and confirm this device-changing command before sending.']);
        }

        return DB::transaction(function () use ($device, $userId, $requestId, $parsed) {
            $device = GpsDevice::whereKey($device->id)->lockForUpdate()->firstOrFail();
            $previous = DB::table('gps_device_commands')->where('request_id', $requestId)->first();
            if ($previous) {
                abort_unless((int) $previous->gps_device_id === $device->id && $previous->command === $parsed['command'] && (int) $previous->requested_by === $userId, 409, 'Request ID was already used.');

                return (array) $previous;
            }
            abort_unless($device->status === 'active' && $device->protocol === 'pt06', 422, 'Commands require an active PT06 device.');
            $session = $this->session($device->id);
            abort_unless($session, 409, 'No active command-capable TCP connection. Wait for the device to reconnect.');
            $this->expire($device->id);
            abort_if(DB::table('gps_device_commands')->where('gps_device_id', $device->id)->whereIn('status', ['queued', 'writing', 'sent'])->exists(), 409, 'Wait for the pending command reply or timeout before sending another.');
            if ($parsed['command'] === 'RELAY,1#') {
                $this->assertStationary($device);
            }
            $id = DB::table('gps_device_commands')->insertGetId([
                'gps_device_id' => $device->id, 'requested_by' => $userId, 'request_id' => $requestId,
                'session_id' => $session, 'command' => $parsed['command'], 'status' => 'queued',
                'expires_at' => now()->addSeconds(30), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return (array) DB::table('gps_device_commands')->find($id);
        });
    }

    public function claim(int $deviceId, string $imei, string $session, ?int $nextSerial = null): ?array
    {
        return DB::transaction(function () use ($deviceId, $imei, $session, $nextSerial) {
            $device = GpsDevice::whereKey($deviceId)->lockForUpdate()->first();
            if (! $device || $device->status !== 'active' || $device->protocol !== 'pt06' || $device->identifier !== $imei) {
                return null;
            }
            $this->expire($deviceId);
            $command = DB::table('gps_device_commands')->where('gps_device_id', $deviceId)->where('session_id', $session)
                ->where('status', 'queued')->orderBy('id')->lockForUpdate()->first();
            if (! $command) {
                return null;
            }
            if ($command->command === 'RELAY,1#') {
                try {
                    $this->assertStationary($device);
                } catch (\Throwable) {
                    DB::table('gps_device_commands')->where('id', $command->id)->update(['status' => 'failed', 'error' => 'Stationary/ignition check failed before dispatch.', 'updated_at' => now()]);

                    return null;
                }
            }
            DB::table('gps_device_commands')->where('id', $command->id)->update(['status' => 'writing', 'updated_at' => now()]);

            // PT06 describes command serial numbers as a packet sequence. Some
            // firmware rejects an otherwise valid 0x80 frame if its serial is
            // unrelated to the terminal's most recent packet.
            $serial = $nextSerial === null ? $command->id % 65536 : $nextSerial;

            return ['id' => $command->id, 'frame' => Pt06CommandEncoder::encode($command->command, $command->id, $serial)];
        });
    }

    public function sent(int $id): void
    {
        DB::table('gps_device_commands')->where('id', $id)->where('status', 'writing')->update([
            'status' => 'sent', 'sent_at' => now(), 'expires_at' => now()->addSeconds(60), 'updated_at' => now(),
        ]);
    }

    public function reply(int $deviceId, string $session, array $events): bool
    {
        return (bool) DB::table('gps_device_commands')->where('id', $events['server_flag'] ?? 0)
            ->where('gps_device_id', $deviceId)->where('session_id', $session)
            ->whereIn('status', ['writing', 'sent', 'timed_out'])->update([
                'status' => 'replied', 'response' => mb_substr($events['response_text'] ?? '', 0, 16000),
                'error' => null, 'replied_at' => now(), 'updated_at' => now(),
            ]);
    }

    public function disconnected(int $deviceId, string $session, string $reason = 'peer_closed'): void
    {
        if ($this->session($deviceId) === $session) {
            Cache::store(config('gps.live_cache_store'))->forget('gps.command-transport.'.$deviceId);
        }
        $message = match ($reason) {
            'connection_reset_by_peer' => 'TCP connection was reset by the remote peer before a reply.',
            'listener_stopped' => 'GPS listener stopped before a reply.',
            'replaced_by_new_connection' => 'Tracker reconnected before replying on its previous connection.',
            'socket_read_failed' => 'TCP read failed before a reply.',
            default => 'Device disconnected before a confirmed reply.',
        };
        DB::table('gps_device_commands')->where('gps_device_id', $deviceId)->where('session_id', $session)
            ->whereIn('status', ['queued', 'writing', 'sent'])->update([
                'status' => 'failed', 'error' => $message.' Execution is unknown; no automatic retry.', 'updated_at' => now(),
            ]);
    }

    private function assertStationary(GpsDevice $device): void
    {
        $state = app(HardwareFleet::class)->device($device->load(['vehicle', 'latestLocation', 'latestHeartbeat']));
        abort_unless($state['online'] && $state['gps_fresh'] && $state['acc'] === false
            && $state['speed'] !== null && $state['speed'] <= 1, 422, 'Power cut requires a fresh GPS fix, speed at or below 1 km/h and ignition OFF.');
    }
}
