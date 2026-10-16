<?php

namespace App\Services\Gps;

use App\DTO\LocationUpdateData;
use App\Enums\UserStatus;
use App\Http\Requests\Api\V1\LocationUpdateRequest;
use App\Models\DeviceSession;
use App\Models\DeviceStatus;
use App\Models\User;
use App\Models\UserLicense;
use App\Services\SettingsService;
use App\Services\UserPresenceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;

class MobilePacketService
{
    public function handle(int $code, array $payload): array
    {
        if (! in_array($code, [1, 2, 3, 4], true)) {
            throw new InvalidArgumentException('unknown_code');
        }
        $rules = [
            'imei' => ['required', 'string', 'max:191',
                'regex:/^(?:[0-9]{15}|[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12})$/'],
            'license_number' => ['required', 'string', 'max:191'],
            'user_id' => ['required_with:device_key', 'nullable', 'integer', 'min:1'],
            'device_key' => ['required_without:token', Rule::prohibitedIf(! empty($payload['token'])), 'nullable', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'token' => ['required_without:device_key', 'nullable', 'string', 'max:256'],
            'packet_id' => ['required', 'uuid'],
            'recorded_at' => ['required', 'date', 'after_or_equal:'.now()->subMinutes(5)->toIso8601String(),
                'before_or_equal:'.now()->addSeconds(30)->toIso8601String()],
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
            'network_type' => ['nullable', 'string', 'max:20'],
            'is_gps_enabled' => ['nullable', 'boolean'],
        ];
        if ($code === 2) {
            $rules = [...$rules, ...LocationUpdateRequest::packetRules()];
            $rules['recorded_at'] = ['required', 'date',
                'after_or_equal:'.now()->subMinutes(5)->toIso8601String(),
                'before_or_equal:'.now()->addSeconds(30)->toIso8601String()];
        }
        $data = Validator::make($payload, $rules)->validate();
        $registered = isset($data['device_key']);
        $token = $registered ? null : PersonalAccessToken::findToken($data['token']);
        $user = $registered ? User::find((int) $data['user_id']) : $token?->tokenable;
        $expiration = config('sanctum.expiration');
        if (! $user instanceof User || $user->status !== UserStatus::Active
            || (isset($data['user_id']) && (int) $data['user_id'] !== (int) $user->id)
            || (! $registered && ($token->name !== $data['imei'] || ! $token->can('gps:write')
                || ($token->expires_at && $token->expires_at->isPast())
                || ($expiration && $token->created_at->lte(now()->subMinutes($expiration)))))) {
            throw new InvalidArgumentException('unauthorized');
        }
        $query = DeviceSession::where('user_id', $user->id)->where('session_id', $data['imei']);
        if (! $registered) $query->where('is_current', true)->whereNull('logged_out_at');
        $device = $query->first();
        if (! $device || ($registered && (! $device->tracking_registered_at || $device->tracking_revoked_at))) {
            throw new InvalidArgumentException('device_revoked');
        }
        if ($registered && (! $device->tracking_key_hash
            || ! hash_equals($device->tracking_key_hash, hash('sha256', $data['device_key'])))) {
            throw new InvalidArgumentException('unauthorized');
        }
        $license = UserLicense::usable()->forTrackedUser($user->id)
            ->where('license_number', $data['license_number'])->first();
        if (! $license || ($registered && (int) $device->tracking_license_id !== (int) $license->id)) {
            throw new InvalidArgumentException('invalid_license');
        }
        $key = 'gps.packet.'.hash('sha256', $user->id.'|'.$data['imei'].'|'.$data['packet_id']);

        return Cache::lock('gps.mobile.'.$device->id, 15)->block(5, function () use ($code, $data, $device, $user, $token, $key, $registered) {
            if ($registered) {
                $device->refresh();
                if ($device->tracking_revoked_at || ! $device->tracking_key_hash
                    || ! hash_equals($device->tracking_key_hash, hash('sha256', $data['device_key']))) {
                    throw new InvalidArgumentException('device_revoked');
                }
            }
            $digest = hash('sha256', serialize([$code, $data]));
            $previous = Cache::get($key);
            if ($previous) {
                if ($previous['digest'] !== $digest) {
                    throw new InvalidArgumentException('packet_id_conflict');
                }

                return [...$previous['reply'], 'duplicate' => true];
            }
            $reply = [
                'packet_id' => $data['packet_id'], 'ok' => true,
                'code' => sprintf('%02X', $code),
                'user_id' => $user->id,
                'server_time' => now()->toIso8601String(),
                'distance_filter_meters' => app(SettingsService::class)->locationSaveRadius($user),
            ];
            $activity = ['last_activity_at' => now()];
            if ($registered && $code !== 4) {
                $activity = [...$activity, 'is_current' => true, 'logged_out_at' => null];
            }
            $device->update($activity);
            $token?->forceFill(['last_used_at' => now()])->saveQuietly();
            if ($code === 2) {
                $data['device_id'] = $data['imei'];
                $result = app(GpsIngestionService::class)->ingest($user, LocationUpdateData::fromArray($data), async: false);
                $reply['saved'] = $result->accepted;
                $reply['reason'] = $result->reason;
                $reply['tracking_session_id'] = $result->trackingSessionUuid;
                $live = app(LiveLocationService::class)->latest($user->id);
                $reply['live_cached'] = $live !== null
                    && $live->recorded_at->getTimestamp() === \Carbon\CarbonImmutable::parse($data['recorded_at'])->getTimestamp()
                    && abs((float) $live->latitude - (float) $data['latitude']) < 0.0000001
                    && abs((float) $live->longitude - (float) $data['longitude']) < 0.0000001;
            } elseif ($code === 4) {
                $device->update(['is_current' => false, 'logged_out_at' => now()]);
                DeviceStatus::where('device_session_id', $device->id)->update(['is_online' => false]);
                $token?->delete();
            } else {
                $status = [
                    'is_online' => true, 'is_internet_enabled' => true, 'last_ping_at' => now(),
                ];
                foreach (['battery_level', 'network_type', 'is_gps_enabled'] as $field) {
                    if (array_key_exists($field, $data)) $status[$field] = $data[$field];
                }
                DeviceStatus::updateOrCreate(['device_session_id' => $device->id], $status);
            }
            app(UserPresenceService::class)->broadcastChange($user->id);
            Cache::put($key, ['digest' => $digest, 'reply' => $reply], now()->addDay());

            return $reply;
        });
    }
}
