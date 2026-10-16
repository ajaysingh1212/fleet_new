<?php

namespace Database\Seeders;

use App\Enums\LicenseType;
use App\Enums\UserStatus;
use App\Models\LicensePlan;
use Illuminate\Database\Seeder;

class LicensePlanSeeder extends Seeder
{
    public function run(): void
    {
        LicensePlan::query()->where('name', 'Free Trial')->update(['status' => UserStatus::Inactive]);

        $plans = [
            ['name' => 'Free Demo', 'type' => LicenseType::Daily, 'duration_in_days' => 1, 'price' => 0, 'renewal_price' => 0, 'is_free' => true, 'display_order' => 0],
            ['name' => 'Daily Access', 'type' => LicenseType::Daily, 'duration_in_days' => 1, 'price' => 9.00, 'renewal_price' => 7.00, 'is_free' => false, 'display_order' => 1],
            ['name' => 'Weekly Access', 'type' => LicenseType::Weekly, 'duration_in_days' => 7, 'price' => 49.00, 'renewal_price' => 39.00, 'is_free' => false, 'display_order' => 2],
            ['name' => 'Monthly Starter', 'type' => LicenseType::Monthly, 'duration_in_days' => 30, 'price' => 29.00, 'renewal_price' => 19.00, 'is_free' => false, 'display_order' => 3],
            ['name' => 'Quarterly Growth', 'type' => LicenseType::Quarterly, 'duration_in_days' => 90, 'price' => 79.00, 'renewal_price' => 59.00, 'is_free' => false, 'display_order' => 4],
            ['name' => 'Half Yearly Business', 'type' => LicenseType::HalfYearly, 'duration_in_days' => 180, 'price' => 149.00, 'renewal_price' => 109.00, 'is_free' => false, 'display_order' => 5],
            ['name' => 'Yearly Enterprise', 'type' => LicenseType::Yearly, 'duration_in_days' => 365, 'price' => 249.00, 'renewal_price' => 199.00, 'is_free' => false, 'display_order' => 6],
            ['name' => 'Lifetime Corporate', 'type' => LicenseType::Lifetime, 'duration_in_days' => null, 'price' => 999.00, 'renewal_price' => 499.00, 'is_free' => false, 'display_order' => 7],
        ];

        foreach ($plans as $plan) {
            LicensePlan::query()->updateOrCreate(
                ['name' => $plan['name']],
                $plan + [
                    'status' => UserStatus::Active,
                    'description' => $plan['name'].' plan for Tracker Enterprise customers.',
                ],
            );
        }
    }
}
