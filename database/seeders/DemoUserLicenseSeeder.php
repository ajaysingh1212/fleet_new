<?php

namespace Database\Seeders;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Models\LicensePlan;
use App\Models\User;
use App\Models\UserLicense;
use Illuminate\Database\Seeder;

class DemoUserLicenseSeeder extends Seeder
{
    public function run(): void
    {
        $plan = LicensePlan::query()->where('name', 'Free Demo')->first();

        if (! $plan) {
            return;
        }

        User::query()
            ->role('User')
            ->where('email', 'like', 'user%@gmail.com')
            ->each(function (User $user) use ($plan): void {
                UserLicense::query()->firstOrCreate(
                    ['license_number' => 'DEMO-'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT)],
                    [
                        'user_id' => $user->id,
                        'assigned_tracked_user_id' => null,
                        'license_plan_id' => $plan->id,
                        'purchase_date' => now(),
                        'activation_date' => null,
                        'expiry_date' => null,
                        'status' => LicenseStatus::Pending,
                        'payment_status' => PaymentStatus::Paid,
                        'invoice_number' => 'DEMO-INV-'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
                        'order_number' => 'DEMO-ORD-'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
                    ],
                );
            });
    }
}
