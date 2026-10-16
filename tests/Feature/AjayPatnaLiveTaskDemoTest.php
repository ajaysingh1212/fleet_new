<?php

namespace Tests\Feature;

use App\Models\FieldTask;
use App\Models\GpsLocation;
use App\Models\TrackingRelation;
use App\Models\User;
use Database\Seeders\AjayPatnaLiveTaskSeeder;
use Database\Seeders\LicensePlanSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjayPatnaLiveTaskDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_and_simulator_complete_three_stop_patna_task(): void
    {
        $this->seed([RoleAndPermissionSeeder::class, LicensePlanSeeder::class, AjayPatnaLiveTaskSeeder::class]);

        $tracker = User::where('email', 'user1@gmail.com')->firstOrFail();
        $ajay = User::where('email', 'ajay@gmail.com')->firstOrFail();
        $relation = TrackingRelation::usableForTracking()
            ->where('tracker_user_id', $tracker->id)
            ->where('tracked_user_id', $ajay->id)
            ->firstOrFail();

        $this->assertTrue($relation->userLicense->isUsable());
        $this->artisan('demo:simulate-ajay-task', ['--delay' => 0, '--steps' => 8])->assertSuccessful();

        $task = FieldTask::where('reference_code', AjayPatnaLiveTaskSeeder::TASK_REFERENCE)->with('stops')->firstOrFail();
        $this->assertSame('completed', $task->status);
        $this->assertCount(3, $task->stops);
        $this->assertSame(3, $task->stops->where('status', 'completed')->count());
        $this->assertSame(3, $task->activity()->where('event_type', 'stop_completed')->count());
        $this->assertSame(25, GpsLocation::where('user_id', $ajay->id)->where('provider', 'demo-route')->count());
    }
}
