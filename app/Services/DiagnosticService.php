<?php

namespace App\Services;

use App\Enums\DiagnosticEventType;
use App\Events\DeviceDiagnosticUpdated;
use App\Models\DeviceSession;
use App\Models\DiagnosticLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DiagnosticService
{
    public function record(User $user, array $data): DiagnosticLog
    {
        $eventType = DiagnosticEventType::from($data['event_type']);
        $occurredAt = isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now();

        $deviceSessionId = request()?->hasSession()
            ? DeviceSession::query()
                ->where('user_id', $user->id)
                ->where('session_id', request()->session()->getId())
                ->value('id')
            : null;

        $signature = [
            'event_type' => $eventType->value,
            'network_type' => $data['network_type'] ?? null,
            'battery_level' => $data['battery_level'] ?? null,
            'reason' => $data['reason'] ?? null,
        ];

        if (Cache::get($this->dedupeKey($user)) === $signature) {
            return DiagnosticLog::query()
                ->where('user_id', $user->id)
                ->latest('occurred_at')
                ->firstOrFail();
        }

        $log = DiagnosticLog::create([
            'user_id' => $user->id,
            'device_session_id' => $deviceSessionId,
            'event_type' => $eventType,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'reason' => $data['reason'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'network_type' => $data['network_type'] ?? null,
            'battery_level' => $data['battery_level'] ?? null,
            'occurred_at' => $occurredAt,
        ]);

        Cache::put($this->dedupeKey($user), $signature, now()->addMinutes(5));
        Cache::put($this->stateKey($user), [
            'event_type' => $log->event_type->value,
            'network_type' => $log->network_type,
            'battery_level' => $log->battery_level,
            'reason' => $log->reason,
            'occurred_at' => $log->occurred_at?->toISOString(),
            'received_at' => $log->created_at?->toISOString(),
        ], now()->addDay());

        DeviceDiagnosticUpdated::dispatch($log);

        return $log;
    }

    public function currentState(User $user): array
    {
        return Cache::get($this->stateKey($user), []);
    }

    private function stateKey(User $user): string
    {
        return "user:{$user->id}:diagnostics";
    }

    private function dedupeKey(User $user): string
    {
        return "user:{$user->id}:diagnostics:last-signature";
    }
}
