<?php

namespace Tests\Feature;

use App\Models\DeviceSession;
use App\Models\GpsLocation;
use App\Models\LicensePlan;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Models\UserLicense;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class RouteHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $viewer = User::factory()->create();
        $tracked = User::factory()->create();
        $plan = LicensePlan::create(['name' => 'Route test', 'type' => 'daily', 'duration_in_days' => 1,
            'price' => 1, 'renewal_price' => 1, 'is_free' => false, 'status' => 'active']);
        $license = UserLicense::create(['user_id' => $viewer->id, 'assigned_tracked_user_id' => $tracked->id,
            'license_plan_id' => $plan->id, 'license_number' => 'ROUTE-TEST', 'purchase_date' => now(),
            'status' => 'active', 'payment_status' => 'paid', 'expiry_date' => now()->addDay()]);
        $relation = TrackingRelation::create(['tracker_user_id' => $viewer->id,
            'tracked_user_id' => $tracked->id, 'user_license_id' => $license->id,
            'relationship_name' => 'Friend', 'status' => 'active']);
        $device = DeviceSession::create(['user_id' => $tracked->id, 'session_id' => 'route-test', 'is_current' => true]);
        return [$viewer, $tracked, $device, $license, $relation];
    }

    private function point(User $user, DeviceSession $device, string $time): void
    {
        GpsLocation::create(['user_id' => $user->id, 'device_session_id' => $device->id,
            'source_type' => 'android', 'latitude' => 25.5941, 'longitude' => 85.1376, 'recorded_at' => $time]);
    }

    public function test_same_origin_8001_is_stateful_but_external_origin_is_not(): void
    {
        foreach (['127.0.0.1', 'localhost', '192.168.1.60'] as $host) {
            $request = Request::create('http://'.$host.':8001/api/v1/analytics', 'GET', [], [], [],
                ['HTTP_ORIGIN' => 'http://'.$host.':8001']);
            $this->assertTrue(EnsureFrontendRequestsAreStateful::fromFrontend($request));
        }
        $external = Request::create('http://127.0.0.1:8001/api/v1/analytics', 'GET', [], [], [],
            ['HTTP_ORIGIN' => 'https://untrusted.example']);
        $this->assertFalse(EnsureFrontendRequestsAreStateful::fromFrontend($external));
    }

    public function test_history_is_chronological_and_uses_indian_calendar_days(): void
    {
        [$viewer, $tracked, $device] = $this->fixture();
        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:00:00Z'));
        $this->point($tracked, $device, '2026-10-03T10:00:00Z');
        $this->point($tracked, $device, '2026-10-02T18:30:00Z');
        $this->point($tracked, $device, '2026-10-02T18:29:59Z');
        $this->actingAs($viewer)->getJson('/live-map/route-history/'.$tracked->id.'?from=2026-10-03&to=2026-10-03')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.recorded_at', '2026-10-02T18:30:00+00:00');
    }

    public function test_reports_accept_browser_login_cookie_on_port_8001_without_a_bearer_token(): void
    {
        [$viewer, $tracked] = $this->fixture();
        $this->post('http://127.0.0.1:8001/login', ['email' => $viewer->email, 'password' => 'password'])
            ->assertRedirect();
        $sessionId = session()->getId();
        foreach (['reports', 'analytics', 'attendance', 'timeline', 'heatmap'] as $endpoint) {
            auth()->forgetGuards();
            $this->withCookie(config('session.cookie'), $sessionId)
                ->getJson('http://127.0.0.1:8001/api/v1/'.$endpoint.'?user_id='.$tracked->id.'&preset=today&type=employee_daily',
                    ['Referer' => 'http://127.0.0.1:8001/live-map'])->assertOk();
        }
    }

    public function test_unauthenticated_or_unrelated_users_cannot_read_or_download(): void
    {
        [$viewer, $tracked] = $this->fixture();
        foreach (['', '/download'] as $suffix) {
            $url = '/live-map/route-history/'.$tracked->id.$suffix;
            $this->getJson($url)->assertUnauthorized();
            $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
            auth()->forgetGuards();
        }
    }

    public function test_expired_license_and_inactive_relation_revoke_history_access(): void
    {
        [$viewer, $tracked, $device, $license, $relation] = $this->fixture();
        $license->update(['expiry_date' => now()->subMinute()]);
        $this->actingAs($viewer)->getJson('/live-map/route-history/'.$tracked->id)->assertForbidden();
        $this->getJson('/live-map/route-history/'.$tracked->id.'/download')->assertForbidden();
        $license->update(['expiry_date' => now()->addDay()]);
        $relation->update(['status' => 'inactive']);
        $this->getJson('/live-map/route-history/'.$tracked->id)->assertForbidden();
    }

    public function test_dates_older_than_30_days_future_and_reversed_ranges_are_rejected(): void
    {
        [$viewer, $tracked] = $this->fixture();
        $this->travelTo(CarbonImmutable::parse('2026-10-03T12:00:00Z'));
        $this->actingAs($viewer);
        foreach (['from=2026-09-03', 'to=2026-10-04', 'from=2026-10-03&to=2026-10-02', 'from=not-a-date'] as $query) {
            $this->getJson('/live-map/route-history/'.$tracked->id.'?'.$query)->assertUnprocessable();
        }
    }

    public function test_csv_and_pagination_include_all_points_not_just_the_first_page(): void
    {
        [$viewer, $tracked, $device] = $this->fixture();
        $today = CarbonImmutable::now('Asia/Kolkata')->startOfDay()->utc();
        for ($index = 0; $index < 501; $index++) $this->point($tracked, $device, $today->addSeconds($index)->toIso8601String());
        $this->actingAs($viewer)->getJson('/live-map/route-history/'.$tracked->id)
            ->assertOk()->assertJsonCount(500, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/live-map/route-history/'.$tracked->id.'?page=2')->assertOk()->assertJsonCount(1, 'data');
        $csv = $this->get('/live-map/route-history/'.$tracked->id.'/download');
        $csv->assertOk()->assertDownload();
        $this->assertCount(502, explode("\n", trim($csv->streamedContent())));
    }
}
