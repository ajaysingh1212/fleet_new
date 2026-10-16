<?php

use App\Models\LicensePlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $freeDemoPlan = LicensePlan::query()->where('name', 'Free Demo')->first();

        if (! $freeDemoPlan) {
            return;
        }

        DB::table('user_licenses')
            ->where('license_number', 'like', 'DEMO-%')
            ->orderBy('id')
            ->each(function ($license) use ($freeDemoPlan): void {
                $activationDate = $license->activation_date
                    ? Carbon::parse($license->activation_date)
                    : null;

                DB::table('user_licenses')
                    ->where('id', $license->id)
                    ->update([
                        'license_plan_id' => $freeDemoPlan->id,
                        'expiry_date' => $activationDate?->copy()->addDay(),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        //
    }
};
