<?php

namespace Tests\Feature\Api\V1;

use App\DTO\LocationUpdateData;
use App\Enums\SourceType;
use App\Enums\TrackingSessionStatus;
use App\Jobs\StoreGpsLocationJob;
use App\Models\DeviceSession;
use App\Models\DeviceStatus;
use App\Models\TrackingSession;
use App\Models\User;
use App\Services\Gps\CoordinateOptimizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GpsLocationTest extends TestCase
{
    use RefreshDatabase;

    private function packet(array $overrides = []): array
    {
        return array_merge([
            'device_id' => 'device-xyz',
            'source_type' => SourceType::Android->value,
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'accuracy' => 10.0,
            'speed' => 0.0,
            'bearing' => 0.0,
            'battery_level' => 80,
            'network_type' => 'wifi',
            'is_mock' => false,
            'recorded_at' => now()->toIso8601String(),
        ], $overrides);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/gps/locations', $this->packet())->assertStatus(401);
    }

    public function test_first_packet_from_a_device_is_accepted_and_queued(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/gps/locations', $this->packet());

        $response->assertStatus(202)->assertJson(['accepted' => true]);

        Queue::assertPushed(StoreGpsLocationJob::class, function (StoreGpsLocationJob $job) {
            return $job->connection === config('queue.default');
        });
    }

    public function test_processing_the_job_persists_the_location_and_updates_device_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/gps/locations', $this->packet());
        $response->assertStatus(202);

        $trackingSessionUuid = $response->json('tracking_session_id');
        $trackingSession = TrackingSession::where('uuid', $trackingSessionUuid)->firstOrFail();

        $dto = LocationUpdateData::fromArray($this->packet());

        (new StoreGpsLocationJob(
            $user->id,
            $trackingSession->device_session_id,
            $trackingSession->id,
            $dto,
        ))->handle(app(CoordinateOptimizerService::class));

        $this->assertDatabaseHas('gps_locations', [
            'user_id' => $user->id,
            'tracking_session_id' => $trackingSession->id,
        ]);

        $status = DeviceStatus::where('device_session_id', $trackingSession->device_session_id)->first();
        $this->assertNotNull($status);
        $this->assertTrue($status->is_online);
        $this->assertNotNull($status->last_location_id);
    }

    public function test_an_immediate_near_duplicate_packet_is_throttled(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $dto = LocationUpdateData::fromArray($this->packet());

        $deviceSession = DeviceSession::firstOrCreate(
            ['user_id' => $user->id, 'session_id' => 'device-xyz'],
            ['platform' => 'android'],
        );

        $trackingSession = TrackingSession::create([
            'user_id' => $user->id,
            'device_session_id' => $deviceSession->id,
            'source_type' => SourceType::Android,
            'started_at' => now(),
            'status' => TrackingSessionStatus::Active,
        ]);

        (new StoreGpsLocationJob($user->id, $deviceSession->id, $trackingSession->id, $dto))
            ->handle(app(CoordinateOptimizerService::class));

        // Same spot, one second later — should be rejected as throttled.
        $response = $this->postJson('/api/v1/gps/locations', $this->packet([
            'recorded_at' => now()->addSecond()->toIso8601String(),
        ]));

        $response->assertStatus(200)->assertJson(['accepted' => false, 'reason' => 'throttled']);
    }

    public function test_batch_sync_creates_an_offline_sync_log_with_correct_counts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $base = now()->subMinutes(5);

        $response = $this->postJson('/api/v1/gps/locations/batch', [
            'locations' => [
                $this->packet(['recorded_at' => $base->copy()->toIso8601String()]),
                $this->packet(['recorded_at' => $base->copy()->addMinute()->toIso8601String(), 'latitude' => 28.62, 'longitude' => 77.22]),
                $this->packet(['recorded_at' => $base->copy()->addMinutes(2)->toIso8601String(), 'latitude' => 28.62, 'longitude' => 77.22]),
            ],
        ]);

        $response->assertStatus(202);
        $this->assertSame(2, $response->json('accepted_count'));
        $this->assertSame(1, $response->json('rejected_count'));

        $this->assertDatabaseHas('offline_sync_logs', [
            'uuid' => $response->json('sync_log_uuid'),
            'user_id' => $user->id,
            'batch_size' => 3,
            'accepted_count' => 2,
            'rejected_count' => 1,
        ]);
    }
}
