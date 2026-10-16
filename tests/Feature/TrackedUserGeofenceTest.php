<?php

namespace Tests\Feature;

use App\Models\DeviceSession;
use App\Models\Geofence;
use App\Models\GeofenceAssignment;
use App\Models\GpsLocation;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Notifications\GeofenceComplianceNotification;
use App\Services\Geofence\GeofenceEvaluationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TrackedUserGeofenceTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesTrackingLicense;

    public function test_regular_tracker_can_create_and_assign_a_geofence_only_to_their_tracked_user(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $tracker = User::factory()->create();
        $tracker->assignRole('User');
        $tracked = User::factory()->create();
        $tracked->assignRole('User');

        TrackingRelation::create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'Friend',
            'user_license_id' => $this->trackingLicense($tracker, $tracked)->id,
            'status' => 'active',
        ]);

        $geofence = $this->actingAs($tracker)->postJson('/api/v1/geofences', [
            'name' => 'Home Zone',
            'type' => 'circle',
            'category' => 'home',
            'center_lat' => 28.6139,
            'center_lng' => 77.2090,
            'radius_meters' => 200,
        ])->assertCreated()->json('data');

        $this->actingAs($tracker)->postJson('/api/v1/geofence-assignments', [
            'geofence_uuid' => $geofence['uuid'],
            'user_id' => $tracked->id,
            'schedule_type' => 'daily',
            'alert_on_exit' => true,
            'alert_on_missed' => true,
        ])->assertCreated();

        $this->actingAs($tracker)->get(route('live-map.index'))
            ->assertOk()
            ->assertViewHas('people', fn ($people) => count($people->firstWhere('id', $tracked->id)['geofences']) === 1);

        $this->actingAs($tracked)->postJson('/api/v1/geofence-assignments', [
            'geofence_uuid' => $geofence['uuid'],
            'user_id' => $tracker->id,
            'schedule_type' => 'daily',
        ])->assertForbidden();
    }

    public function test_first_outside_point_and_continued_outside_points_alert_the_tracker(): void
    {
        Notification::fake();
        $tracker = User::factory()->create();
        $tracked = User::factory()->create();
        TrackingRelation::create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'Friend',
            'user_license_id' => $this->trackingLicense($tracker, $tracked)->id,
            'status' => 'active',
        ]);
        $geofence = Geofence::create([
            'name' => 'Home Zone',
            'type' => 'circle',
            'category' => 'home',
            'status' => 'active',
            'center_lat' => 28.6139,
            'center_lng' => 77.2090,
            'radius_meters' => 100,
            'created_by' => $tracker->id,
        ]);
        GeofenceAssignment::create([
            'geofence_id' => $geofence->id,
            'user_id' => $tracked->id,
            'assigned_by' => $tracker->id,
            'schedule_type' => 'daily',
            'alert_on_exit' => true,
            'status' => 'active',
        ]);
        $device = DeviceSession::create(['user_id' => $tracked->id, 'device_name' => 'Test Phone']);

        $first = GpsLocation::create([
            'user_id' => $tracked->id,
            'device_session_id' => $device->id,
            'source_type' => 'browser',
            'latitude' => 28.6200,
            'longitude' => 77.2200,
            'recorded_at' => now(),
        ]);
        app(GeofenceEvaluationService::class)->evaluate($first);

        $second = GpsLocation::create([
            'user_id' => $tracked->id,
            'device_session_id' => $device->id,
            'source_type' => 'browser',
            'latitude' => 28.6210,
            'longitude' => 77.2210,
            'recorded_at' => now()->addMinutes(2),
        ]);
        app(GeofenceEvaluationService::class)->evaluate($second);

        $this->assertDatabaseCount('geofence_events', 2);
        $this->assertDatabaseHas('geofence_events', ['user_id' => $tracked->id, 'type' => 'exited']);
        Notification::assertSentToTimes($tracker, GeofenceComplianceNotification::class, 2);
    }
}
