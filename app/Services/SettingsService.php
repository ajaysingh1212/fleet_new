<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    public function locationSaveRadius(\App\Models\User $user): int
    {
        return max(1, (int) $this->get('system', 'location_save_radius_meters',
            $user->trackingPreference?->distance_filter_meters ?? 25));
    }

    /** @return array<string, mixed> */
    public function forGroup(string $group): array
    {
        return Setting::query()
            ->where('group', $group)
            ->get()
            ->reject(fn (Setting $setting): bool => $setting->type === 'secret')
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])
            ->all();
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return $default;
        }

        if ($setting->type === 'secret') {
            try {
                return Crypt::decryptString((string) $setting->value);
            } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                return '';
            }
        }

        return $setting->value ?? $default;
    }

    public function set(string $group, string $key, mixed $value, string $type = 'string', bool $isPublic = false, ?string $description = null): Setting
    {
        if ($type === 'secret') {
            $value = Crypt::encryptString((string) $value);
        }

        return Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'type' => $type, 'is_public' => $isPublic, 'description' => $description],
        );
    }
}
