<?php

namespace App\Services\Gps;

use App\Enums\UserStatus;
use App\Models\DeviceSession;
use App\Models\User;
use App\Models\UserLicense;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class GpsDeviceRegistrationService
{
    public function register(int $userId, string $licenseNumber, string $imei, bool $rotate = false): array
    {
        Validator::make(['imei' => $imei], ['imei' => [
            'required', 'string', 'regex:/^(?:[0-9]{15}|[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12})$/',
        ]])->validate();
        $user = User::find($userId);
        if (! $user || $user->status !== UserStatus::Active) {
            throw new InvalidArgumentException('User must exist and be active.');
        }
        $license = UserLicense::usable()->forTrackedUser($userId)->where('license_number', $licenseNumber)->first();
        if (! $license) {
            throw new InvalidArgumentException('User does not have this valid assigned/owned license.');
        }

        return Cache::lock('gps.register.'.hash('sha256', $userId.'|'.$imei), 15)->block(5,
            fn () => DB::transaction(function () use ($userId, $license, $imei, $rotate) {
                $device = DeviceSession::firstOrNew(['user_id' => $userId, 'session_id' => $imei]);
                if ($device->tracking_registered_at && ! $rotate) {
                    throw new InvalidArgumentException('Device already registered. Use --rotate to replace its key.');
                }
                $key = bin2hex(random_bytes(32));
                if (! $device->exists) {
                    $device->is_current = false;
                    $device->device_name = 'Registered GPS device';
                    $device->platform = 'api';
                }
                $device->fill([
                    'tracking_license_id' => $license->id,
                    'tracking_key_hash' => hash('sha256', $key),
                    'tracking_registered_at' => now(),
                    'tracking_revoked_at' => null,
                ])->save();

                return ['device' => $device, 'device_key' => $key];
            }));
    }
}
