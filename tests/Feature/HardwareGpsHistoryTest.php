<?php

namespace Tests\Feature;

use App\Models\Geofence;
use App\Models\GpsDevice;
use App\Models\GpsDeviceLocation;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class HardwareGpsHistoryTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite' || $app['db']->connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('History tests require isolated SQLite.');
        }
        return $app;
    }

    private function setupDevice(): array
    {
        $this->actingAsSuperAdmin();
        $v = Vehicle::create(['registration_number' => 'BR01TEST', 'name' => 'History car', 'status' => 'active']);
        $d = GpsDevice::create(['identifier' => '865167041931701', 'name' => 'History tracker', 'vehicle_id' => $v->id,
            'status' => 'active', 'protocol' => 'pt06', 'protocol_version' => '2022.4.27-v2']);
        return [$v, $d];
    }

    private function point(GpsDevice $d, string $time, ?int $vehicleId): void
    {
        GpsDeviceLocation::create(['gps_device_id' => $d->id, 'vehicle_id' => $vehicleId,
            'packet_hash' => hash('sha256', $d->id.$time), 'record_index' => 0, 'protocol' => 'pt06', 'protocol_version' => '2022.4.27-v2',
            'latitude' => 25.6, 'longitude' => 85.1, 'speed' => 20, 'heading' => 90, 'gps_fix' => true,
            'recorded_at' => $time, 'received_at' => $time, 'telemetry' => ['acc' => true]]);
    }

    private function report(array $params)
    {
        return $this->getJson(route('admin.monitoring.history.data', $params + ['preset' => 'custom',
            'from' => '2026-10-23T00:00', 'to' => '2026-10-23T23:59', 'report' => 'route']));
    }

    public function test_page_and_search_support_vehicle_and_unassigned_imei_without_loading_the_entire_fleet(): void
    {
        [$v, $d] = $this->setupDevice();
        $this->get(route('admin.monitoring.history'))->assertOk()->assertSee('Custom date')->assertSee('All Reports')->assertDontSee('history-user', false);
        $this->getJson(route('admin.monitoring.history.targets', ['kind' => 'vehicle', 'q' => 'BR01']))
            ->assertOk()->assertJsonPath('data.0.value', 'vehicle:'.$v->id);
        $d->update(['vehicle_id' => null]);
        $this->getJson(route('admin.monitoring.history.targets', ['kind' => 'device', 'q' => $d->identifier]))
            ->assertOk()->assertJsonPath('data.0.value', 'device:'.$d->id);
        $this->getJson(route('admin.monitoring.history.targets', ['kind' => 'device', 'q' => 'no-match']))->assertJsonCount(0, 'data');
    }

    public function test_custom_ist_dates_use_gps_time_and_keep_vehicle_assignment_history(): void
    {
        [$v, $d] = $this->setupDevice();
        $this->point($d, '2026-10-22 18:29:59', $v->id);
        $this->point($d, '2026-10-22 18:30:00', $v->id);
        $this->point($d, '2026-10-23 18:29:59', $v->id);
        $this->point($d, '2026-10-23 18:30:00', $v->id);
        $this->point($d, '2026-10-23 12:00:00', null);
        $d->update(['vehicle_id' => null]);
        $this->report(['target' => 'vehicle:'.$v->id])->assertOk()->assertJsonPath('route.records.total', 2)
            ->assertJsonPath('range.from', '2026-10-23T00:00:00+05:30')->assertJsonMissingPath('geofence');
        $this->report(['target' => 'device:'.$d->id])->assertOk()->assertJsonPath('route.records.total', 3);
    }

    public function test_geofence_report_includes_overlapping_episodes_and_all_combines_both_reports(): void
    {
        [$v, $d] = $this->setupDevice();
        $this->point($d, '2026-10-23 10:00:00', $v->id);
        $g = Geofence::create(['name' => 'Depot', 'type' => 'circle', 'category' => 'warehouse', 'status' => 'active',
            'center_lat' => 25.6, 'center_lng' => 85.1, 'radius_meters' => 100, 'created_by' => auth()->id()]);
        $rule = DB::table('vehicle_geofence_rules')->insertGetId(['geofence_id' => $g->id, 'vehicle_id' => $v->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['2026-10-22 17:00:00', '2026-10-22 19:00:00'], ['2026-10-23 10:00:00', null], ['2026-10-22 10:00:00', '2026-10-22 11:00:00']] as [$start, $end]) {
            DB::table('vehicle_geofence_alerts')->insert(['rule_id' => $rule, 'vehicle_id' => $v->id, 'gps_device_id' => $d->id,
                'type' => 'outside', 'started_at' => $start, 'ended_at' => $end, 'latitude' => 25.6, 'longitude' => 85.1]);
        }
        $this->report(['target' => 'device:'.$d->id, 'report' => 'geofence'])->assertOk()
            ->assertJsonPath('geofence.total', 2)->assertJsonMissingPath('route');
        $this->report(['target' => 'vehicle:'.$v->id, 'report' => 'all'])->assertOk()
            ->assertJsonPath('geofence.total', 2)->assertJsonPath('route.records.total', 1);
    }

    public function test_route_records_are_paginated_and_presets_use_ist_midnight(): void
    {
        [$v, $d] = $this->setupDevice();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-23T00:01:00+05:30'));
        for ($i = 0; $i < 51; $i++) $this->point($d, '2026-10-22 18:30:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), $v->id);
        $this->report(['target' => 'device:'.$d->id, 'preset' => 'today', 'route_page' => 2])->assertOk()
            ->assertJsonPath('route.records.total', 51)->assertJsonCount(1, 'route.records.data')
            ->assertJsonCount(51, 'route.map_points')->assertJsonPath('route.records.current_page', 2);
    }

    public function test_pdf_collection_can_fetch_records_without_reloading_the_route_map(): void
    {
        [$v, $d] = $this->setupDevice();
        for ($i = 0; $i < 51; $i++) {
            $this->point($d, '2026-10-23 10:00:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), $v->id);
        }
        $this->report(['target' => 'vehicle:'.$v->id, 'records_only' => true, 'route_page' => 2])
            ->assertOk()->assertJsonPath('route.records.total', 51)->assertJsonCount(1, 'route.records.data')
            ->assertJsonMissingPath('route.map_points')->assertJsonMissingPath('route.summary');
    }

    public function test_invalid_selection_and_reversed_or_missing_custom_dates_are_rejected(): void
    {
        [, $d] = $this->setupDevice();
        $this->report(['target' => 'device:'.$d->id, 'to' => '2026-10-22T00:00'])->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->report(['target' => 'device:'.$d->id, 'from' => null, 'to' => null])->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
        $this->report(['target' => 'user:1'])->assertUnprocessable()->assertJsonValidationErrors('target');
        $this->report(['target' => 'device:999999'])->assertNotFound();
    }

    public function test_hardware_history_requires_hardware_administrator_authorization(): void
    {
        $this->actingAsPlainUser();
        $this->getJson(route('admin.monitoring.history'))->assertForbidden();
        $this->getJson(route('admin.monitoring.history.targets', ['kind' => 'device']))->assertForbidden();
        $this->report(['target' => 'device:1'])->assertForbidden();
    }

    public function test_large_route_map_is_explicitly_bounded_but_all_records_remain_paginated(): void
    {
        [$v, $d] = $this->setupDevice();
        $records = [];
        for ($i = 0; $i < 5001; $i++) {
            $records[] = ['gps_device_id' => $d->id, 'vehicle_id' => $v->id, 'packet_hash' => hash('sha256', 'bulk-'.$i),
                'record_index' => 0, 'protocol' => 'pt06', 'protocol_version' => '2022.4.27-v2',
                'latitude' => 25.6, 'longitude' => 85.1, 'speed' => 20, 'gps_fix' => true,
                'recorded_at' => '2026-10-23 10:00:00', 'received_at' => '2026-10-23 10:00:00'];
        }
        foreach (array_chunk($records, 500) as $chunk) DB::table('gps_device_locations')->insert($chunk);
        $this->report(['target' => 'device:'.$d->id, 'route_page' => 101])->assertOk()
            ->assertJsonPath('route.map_truncated', true)->assertJsonCount(5000, 'route.map_points')
            ->assertJsonPath('route.records.total', 5001)->assertJsonCount(1, 'route.records.data');
    }
}
