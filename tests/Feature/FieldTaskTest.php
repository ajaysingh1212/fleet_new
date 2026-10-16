<?php

namespace Tests\Feature;

use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\PaymentStatus;
use App\Enums\SourceType;
use App\Enums\UserStatus;
use App\Events\FieldTaskUpdated;
use App\Models\DeviceSession;
use App\Models\FieldTask;
use App\Models\GpsLocation;
use App\Models\LicensePlan;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Models\UserLicense;
use App\Services\FieldTaskProgressService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FieldTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_permission_allows_super_admin_admin_and_user_roles(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        foreach (['Super Admin', 'Admin', 'User'] as $role) {
            $actor = User::factory()->create();
            $actor->assignRole($role);

            $this->actingAs($actor)->get(route('tasks.index'))->assertOk();
            $this->assertTrue($actor->can('manage tasks'));
        }
    }

    public function test_tracker_can_create_ordered_task_for_licensed_tracked_user(): void
    {
        [$tracker, $tracked] = $this->usersWithRelation();
        $response = $this->actingAs($tracker)->post(route('tasks.store'), $this->payload($tracked));

        $task = FieldTask::first();
        $response->assertRedirect(route('tasks.show', $task));
        $this->assertSame($tracked->id, $task->assignee_id);
        $this->assertSame(['First stop', 'Second stop'], $task->stops()->pluck('title')->all());
        $this->actingAs($tracker)->get(route('tasks.create'))->assertOk()->assertSee('Route stops');
        $this->actingAs($tracked)->get(route('tasks.show', $task))->assertOk()->assertSee('Route plan');
    }

    public function test_unlicensed_user_cannot_receive_task(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $tracker = User::factory()->create(); $tracker->assignRole('User');
        $stranger = User::factory()->create();

        $this->actingAs($tracker)->post(route('tasks.store'), $this->payload($stranger))->assertNotFound();
        $this->assertDatabaseCount('field_tasks', 0);
    }

    public function test_gps_arrival_completes_stops_in_sequence_and_broadcasts(): void
    {
        Event::fake([FieldTaskUpdated::class]);
        [$tracker, $tracked, $relation] = $this->usersWithRelation();
        $task = FieldTask::create([
            'creator_id'=>$tracker->id, 'assignee_id'=>$tracked->id, 'tracking_relation_id'=>$relation->id,
            'title'=>'Route', 'priority'=>'normal', 'schedule_type'=>'once', 'starts_at'=>now()->subMinute(),
            'due_at'=>now()->addHour(), 'status'=>'assigned', 'arrival_radius_meters'=>100,
        ]);
        $first = $task->stops()->create(['sequence'=>1,'title'=>'First','latitude'=>28.6139000,'longitude'=>77.2090000,'radius_meters'=>100,'status'=>'pending']);
        $task->stops()->create(['sequence'=>2,'title'=>'Second','latitude'=>28.6200000,'longitude'=>77.2100000,'radius_meters'=>100,'status'=>'pending']);
        $device = DeviceSession::create(['user_id'=>$tracked->id,'session_id'=>'task-test','device_name'=>'Test','is_current'=>true,'last_login_at'=>now(),'last_activity_at'=>now()]);
        $location = GpsLocation::create(['user_id'=>$tracked->id,'device_session_id'=>$device->id,'source_type'=>SourceType::Browser,'latitude'=>28.6139,'longitude'=>77.2090,'recorded_at'=>now()]);

        app(FieldTaskProgressService::class)->process($location);

        $this->assertSame('completed', $first->refresh()->status);
        $this->assertSame('in_progress', $task->refresh()->status);
        $this->assertSame('pending', $task->stops()->where('sequence', 2)->value('status'));
        Event::assertDispatched(FieldTaskUpdated::class);
    }

    private function usersWithRelation(): array
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $tracker=User::factory()->create(); $tracker->assignRole('User'); $tracked=User::factory()->create(); $tracked->assignRole('User');
        $plan=LicensePlan::create(['name'=>'Task Test','type'=>LicenseType::Daily,'duration_in_days'=>1,'price'=>1,'renewal_price'=>1,'is_free'=>false,'status'=>UserStatus::Active]);
        $license=UserLicense::create(['user_id'=>$tracker->id,'assigned_tracked_user_id'=>$tracked->id,'license_plan_id'=>$plan->id,'license_number'=>'TASK-'.str()->uuid(),'purchase_date'=>now(),'activation_date'=>now(),'expiry_date'=>now()->addDay(),'status'=>LicenseStatus::Active,'payment_status'=>PaymentStatus::Paid]);
        $relation=TrackingRelation::create(['tracker_user_id'=>$tracker->id,'tracked_user_id'=>$tracked->id,'user_license_id'=>$license->id,'relationship_name'=>'Employee','status'=>UserStatus::Active]);
        return [$tracker,$tracked,$relation];
    }

    private function payload(User $tracked): array
    {
        return ['assignee_id'=>$tracked->id,'title'=>'Client visits','priority'=>'high','schedule_type'=>'daily','starts_at'=>now()->addMinute()->format('Y-m-d H:i:s'),'due_at'=>now()->addHours(2)->format('Y-m-d H:i:s'),'arrival_radius_meters'=>100,'stops'=>[
            ['title'=>'First stop','latitude'=>28.6139,'longitude'=>77.2090,'radius_meters'=>100],
            ['title'=>'Second stop','latitude'=>28.6200,'longitude'=>77.2100,'radius_meters'=>100],
        ]];
    }
}
