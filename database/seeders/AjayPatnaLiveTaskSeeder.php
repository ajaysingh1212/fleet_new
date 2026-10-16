<?php

namespace Database\Seeders;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Enums\ThemeMode;
use App\Enums\UserStatus;
use App\Models\FieldTask;
use App\Models\FieldTaskActivity;
use App\Models\LicensePlan;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Models\UserLicense;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AjayPatnaLiveTaskSeeder extends Seeder
{
    public const TASK_REFERENCE = 'DEMO-PATNA-AJAY-3STOP';

    public function run(): void
    {
        DB::transaction(function (): void {
            $tracker = User::query()->updateOrCreate(
                ['email' => 'user1@gmail.com'],
                [
                    'employee_id' => 'EMP-00003', 'name' => 'User 1', 'phone' => '9100000001',
                    'password' => Hash::make('Password@123'), 'status' => UserStatus::Active,
                    'theme' => ThemeMode::Light, 'timezone' => 'Asia/Kolkata', 'email_verified_at' => now(),
                ],
            );
            $tracker->syncRoles(['User']);

            $ajay = User::query()->updateOrCreate(
                ['email' => 'ajay@gmail.com'],
                [
                    'employee_id' => 'EMP-AJAY-01', 'name' => 'Ajay Kumar', 'phone' => '919876543210',
                    'password' => Hash::make('Password@123'), 'status' => UserStatus::Active,
                    'theme' => ThemeMode::Light, 'timezone' => 'Asia/Kolkata', 'email_verified_at' => now(),
                    'department' => 'Field Operations', 'designation' => 'Field Executive', 'address' => 'Patna, Bihar',
                ],
            );
            $ajay->syncRoles(['User']);

            $plan = LicensePlan::query()->where('name', 'Monthly Starter')->firstOrFail();
            $license = UserLicense::withTrashed()->where('license_number', 'SIM-PATNA-AJAY-001')->first();
            $licenseData = [
                    'user_id' => $tracker->id, 'assigned_tracked_user_id' => $ajay->id,
                    'license_plan_id' => $plan->id, 'purchase_date' => now(), 'activation_date' => now(),
                    'expiry_date' => now()->addDays(30), 'status' => LicenseStatus::Active,
                    'payment_status' => PaymentStatus::Paid, 'invoice_number' => 'SIM-INV-PATNA-001',
                    'order_number' => 'SIM-ORD-PATNA-001',
            ];
            if ($license) {
                $license->restore();
                $license->update($licenseData);
            } else {
                $license = UserLicense::create($licenseData + ['license_number' => 'SIM-PATNA-AJAY-001']);
            }

            $relation = TrackingRelation::withTrashed()->where('tracker_user_id', $tracker->id)
                ->where('tracked_user_id', $ajay->id)->first();
            if ($relation) {
                $relation->restore();
                $relation->update(['user_license_id' => $license->id, 'relationship_name' => 'Patna Field Executive', 'status' => UserStatus::Active]);
            } else {
                $relation = TrackingRelation::create([
                    'tracker_user_id' => $tracker->id, 'tracked_user_id' => $ajay->id,
                    'user_license_id' => $license->id, 'relationship_name' => 'Patna Field Executive',
                    'status' => UserStatus::Active, 'created_by' => $tracker->id,
                ]);
            }

            FieldTask::withTrashed()->where('reference_code', self::TASK_REFERENCE)->forceDelete();
            $task = FieldTask::create([
                'creator_id' => $tracker->id, 'assignee_id' => $ajay->id,
                'tracking_relation_id' => $relation->id, 'title' => 'Patna three-point field visit',
                'description' => 'Visit all three Patna checkpoints in sequence and complete the field verification.',
                'priority' => 'high', 'schedule_type' => 'once', 'starts_at' => now()->subMinute(),
                'due_at' => now()->addHours(2), 'status' => 'assigned', 'arrival_radius_meters' => 100,
                'customer_name' => 'Patna Field Operations', 'reference_code' => self::TASK_REFERENCE,
                'instructions' => 'Follow the numbered route. Each checkpoint completes automatically inside 100 meters.',
                'tags' => ['demo', 'patna', 'live-route'],
            ]);

            $stops = [
                ['title' => 'Patna Junction', 'address' => 'Patna Junction Railway Station, Fraser Road Area', 'latitude' => 25.6027220, 'longitude' => 85.1375270],
                ['title' => 'Gandhi Maidan', 'address' => 'Gandhi Maidan, Patna', 'latitude' => 25.6175120, 'longitude' => 85.1455100],
                ['title' => 'Bihar Museum', 'address' => 'Bihar Museum, Bailey Road, Patna', 'latitude' => 25.6066570, 'longitude' => 85.1218320],
            ];

            foreach ($stops as $index => $stop) {
                $task->stops()->create($stop + [
                    'sequence' => $index + 1, 'description' => 'Complete verification and record arrival.',
                    'expected_at' => now()->addMinutes(($index + 1) * 20), 'radius_meters' => 100, 'status' => 'pending',
                ]);
            }

            FieldTaskActivity::create([
                'field_task_id' => $task->id, 'user_id' => $tracker->id,
                'event_type' => 'created', 'metadata' => ['source' => 'AjayPatnaLiveTaskSeeder'], 'occurred_at' => now(),
            ]);

            $this->command?->info('Patna live demo ready: user1@gmail.com tracks ajay@gmail.com with a valid license and 3-stop task.');
        });
    }
}
