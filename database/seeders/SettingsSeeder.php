<?php

namespace Database\Seeders;

use App\Services\SettingsService;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(SettingsService::class);

        foreach ([
            'name' => ['Tracker Enterprise', 'Application display name'],
            'tagline' => ['Employee Tracking SaaS', 'Short brand description shown in the workspace'],
            'support_email' => ['support@example.com', 'Support email address'],
            'support_phone' => ['+1-000-000-0000', 'Support phone number'],
        ] as $key => [$value, $description]) {
            if (! Setting::query()->where('group', 'site')->where('key', $key)->exists()) {
                $settings->set('site', $key, $value, 'string', true, $description);
            }
        }
        $settings->set('system', 'timezone', 'UTC', 'string', false, 'Default application timezone');
        $settings->set('system', 'registration', true, 'boolean', false, 'Allow public registration');
        $settings->set('system', 'maintenance_mode', false, 'boolean', false, 'Application maintenance flag');
        $settings->set('security', 'maximum_devices', 5, 'integer', false, 'Maximum concurrent user devices');
        foreach (['gateway' => ['razorpay', 'Selected payment provider'], 'environment' => ['test', 'Payment provider environment']] as $key => [$value, $description]) {
            if (! Setting::query()->where('group', 'payments')->where('key', $key)->exists()) {
                $settings->set('payments', $key, $value, 'string', false, $description);
            }
        }

        foreach (['razorpay' => ['key_id', 'key_secret'], 'cashfree' => ['app_id', 'secret_key'], 'payu' => ['merchant_key', 'salt'], 'phonepe' => ['client_id', 'client_secret', 'client_version']] as $provider => $credentials) {
            foreach (['test', 'live'] as $environment) {
                foreach ($credentials as $credential) {
                    $key = "{$provider}_{$environment}_{$credential}";
                    if (! Setting::query()->where('group', 'payments')->where('key', $key)->exists()) {
                        $settings->set('payments', $key, '', 'secret', false, ucfirst($provider).' '.ucfirst($environment).' '.str_replace('_', ' ', $credential));
                    }
                }
            }
        }

        $settings->set('security', 'password_policy', ['min' => 8, 'mixedCase' => true, 'numbers' => true, 'symbols' => false], 'json', false, 'Default password policy');
        $settings->set('appearance', 'theme', 'light', 'string', true, 'Default UI theme');
        $settings->set('appearance', 'language', 'en', 'string', true, 'Default application language');
    }
}
