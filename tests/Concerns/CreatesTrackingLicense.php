<?php

namespace Tests\Concerns;

use App\Models\LicensePlan;
use App\Models\User;
use App\Models\UserLicense;

trait CreatesTrackingLicense
{
    protected function trackingLicense(User $tracker, User $tracked): UserLicense
    {
        $plan = LicensePlan::firstOrCreate(['name' => 'Tracking test plan'], [
            'type' => 'daily', 'duration_in_days' => 1, 'price' => 1,
            'renewal_price' => 1, 'is_free' => false, 'status' => 'active',
        ]);

        return UserLicense::create([
            'user_id' => $tracker->id, 'assigned_tracked_user_id' => $tracked->id,
            'license_plan_id' => $plan->id, 'license_number' => 'TEST-'.str()->uuid(),
            'purchase_date' => now(), 'activation_date' => now(),
            'expiry_date' => now()->addDay(), 'status' => 'active', 'payment_status' => 'paid',
        ]);
    }
}
