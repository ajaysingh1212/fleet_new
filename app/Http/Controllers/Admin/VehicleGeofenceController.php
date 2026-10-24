<?php

namespace App\Http\Controllers\Admin;

use App\Events\VehicleGeofenceAlertChanged;
use App\Events\VehicleGeofenceRulesChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGeofenceRequest;
use App\Http\Resources\GeofenceResource;
use App\Models\Geofence;
use App\Models\GpsDevice;
use App\Models\Vehicle;
use App\Services\Geofence\GeofenceManagementService;
use App\Services\Geofence\VehicleGeofenceMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleGeofenceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['vehicle_ids' => 'nullable|array|max:250', 'vehicle_ids.*' => 'integer', 'device_ids' => 'nullable|array|max:250', 'device_ids.*' => 'integer', 'per_page' => 'nullable|integer|min:1|max:250', 'category' => 'nullable|string', 'status' => 'nullable|string']);
        $rules = DB::table('vehicle_geofence_rules as r')
            ->join('geofences as g', 'g.id', '=', 'r.geofence_id')->whereNull('g.deleted_at')
            ->leftJoin('gps_devices as d', 'd.id', '=', 'r.gps_device_id')
            ->leftJoin('vehicles as v', 'v.id', '=', DB::raw('COALESCE(r.vehicle_id, d.vehicle_id)'))
            ->when($request->has('vehicle_ids') || $request->has('device_ids'), fn ($q) => $q->where(fn ($scope) => $scope
                ->whereIn('r.vehicle_id', $request->input('vehicle_ids', []))->orWhereIn('r.gps_device_id', $request->input('device_ids', []))))
            ->when($request->filled('category'), fn ($q) => $q->where('g.category', $request->category))
            ->when($request->filled('status'), fn ($q) => $q->where('g.status', $request->status))
            ->select('r.*', 'v.registration_number')->selectRaw('COALESCE(d.identifier, (SELECT identifier FROM gps_devices WHERE vehicle_id = r.vehicle_id ORDER BY id LIMIT 1)) as imei')->orderByDesc('r.id')->paginate((int) $request->input('per_page', 30));
        $fences = Geofence::with('points')->whereIn('id', $rules->getCollection()->pluck('geofence_id'))->get()->keyBy('id');
        $data = $rules->getCollection()->map(function ($rule) use ($request, $fences) {
            $fence = $fences->get($rule->geofence_id);

            return $fence ? [...(new GeofenceResource($fence))->toArray($request), 'rule_id' => $rule->id, 'vehicle_id' => $rule->vehicle_id, 'registration' => $rule->registration_number, 'gps_device_id' => $rule->gps_device_id, 'imei' => $rule->imei] : null;
        })->filter()->values();

        return response()->json(['data' => $data, 'last_page' => $rules->lastPage()]);
    }

    public function vehicles(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);

        return Vehicle::when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('registration_number', 'like', '%'.$request->q.'%')->orWhere('name', 'like', '%'.$request->q.'%')))
            ->select('id', 'registration_number', 'name')->orderBy('registration_number')->paginate(30);
    }

    public function targets(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);

        return GpsDevice::query()->leftJoin('vehicles as v', 'v.id', '=', 'gps_devices.vehicle_id')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s
                ->where('gps_devices.identifier', 'like', '%'.$request->q.'%')
                ->orWhere('v.registration_number', 'like', '%'.$request->q.'%')
                ->orWhere('gps_devices.name', 'like', '%'.$request->q.'%')))
            ->select('gps_devices.id', 'gps_devices.identifier as imei', 'gps_devices.vehicle_id', 'v.registration_number')
            ->orderBy('gps_devices.id')->paginate(30);
    }

    private function target(Request $request): array
    {
        $data = $request->validate([
            'vehicle_id' => 'nullable|required_without:gps_device_id|prohibits:gps_device_id|integer|exists:vehicles,id',
            'gps_device_id' => 'nullable|required_without:vehicle_id|prohibits:vehicle_id|integer|exists:gps_devices,id',
        ]);

        return ['vehicle_id' => $data['vehicle_id'] ?? null, 'gps_device_id' => $data['gps_device_id'] ?? null];
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorize('manage', GpsDevice::class);
        DB::transaction(function () use ($id) {
            $rule = DB::table('vehicle_geofence_rules')->where('id', $id)->lockForUpdate()->first();
            abort_unless($rule, 404);
            $fence = Geofence::findOrFail($rule->geofence_id);
            $this->authorize('delete', $fence);
            $fence->delete();
            DB::afterCommit(fn () => VehicleGeofenceRulesChanged::dispatch());
            $this->closeAlerts($id);
            DB::table('vehicle_geofence_states')->where('rule_id', $id)->delete();
        });

        return response()->json(['deleted' => true]);
    }

    public function store(StoreGeofenceRequest $request, GeofenceManagementService $service)
    {
        $this->authorize('manage', GpsDevice::class);
        $this->authorize('create', Geofence::class);
        $target = $this->target($request);
        $id = DB::transaction(function () use ($request, $service, $target) {
            $fence = $service->create($request->validated(), $request->user());

            DB::afterCommit(fn () => VehicleGeofenceRulesChanged::dispatch());

            return DB::table('vehicle_geofence_rules')->insertGetId(['geofence_id' => $fence->id, ...$target, 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['id' => $id], 201);
    }

    public function update(StoreGeofenceRequest $request, int $id, GeofenceManagementService $service)
    {
        $this->authorize('manage', GpsDevice::class);
        $target = $this->target($request);
        DB::transaction(function () use ($request, $id, $service, $target) {
            $rule = DB::table('vehicle_geofence_rules')->where('id', $id)->lockForUpdate()->first();
            abort_unless($rule, 404);
            $fence = Geofence::findOrFail($rule->geofence_id);
            $this->authorize('update', $fence);
            $service->update($fence, $request->validated(), $request->user());
            $this->closeAlerts($id);
            DB::table('vehicle_geofence_states')->where('rule_id', $id)->delete();
            DB::table('vehicle_geofence_rules')->where('id', $id)->update([...$target, 'updated_at' => now()]);
            DB::afterCommit(fn () => VehicleGeofenceRulesChanged::dispatch());
        });

        return response()->json(['saved' => true]);
    }

    private function closeAlerts(int $id): void
    {
        $devices = DB::table('vehicle_geofence_alerts')->where('rule_id', $id)->whereNull('ended_at')->distinct()->pluck('gps_device_id');
        DB::table('vehicle_geofence_alerts')->where('rule_id', $id)->whereNull('ended_at')->update(['ended_at' => now()]);
        foreach ($devices as $deviceId) {
            DB::afterCommit(function () use ($deviceId) {
                $device = GpsDevice::with('vehicle')->find($deviceId);
                if (! $device) {
                    return;
                }
                VehicleGeofenceAlertChanged::dispatch(['device_id' => $device->id, 'vehicle_id' => $device->vehicle_id,
                    'registration' => $device->vehicle?->registration_number, 'imei' => $device->identifier, 'active_alerts' => app(VehicleGeofenceMonitor::class)->alerts($device->id),
                    'occurred_at' => now()->utc()->toIso8601String(), 'observed_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z')]);
            });
        }
    }

    public function status(Request $request, int $id)
    {
        $this->authorize('manage', GpsDevice::class);
        $request->validate(['active' => 'required_without:status|boolean', 'status' => 'required_without:active|in:active,inactive,archived']);
        DB::transaction(function () use ($request, $id) {
            $rule = DB::table('vehicle_geofence_rules')->where('id', $id)->lockForUpdate()->first();
            abort_unless($rule, 404);
            $fence = Geofence::findOrFail($rule->geofence_id);
            $this->authorize('update', $fence);
            $fence->update(['status' => $request->input('status', $request->boolean('active') ? 'active' : 'inactive'), 'updated_by' => $request->user()->id]);
            $this->closeAlerts($id);
            DB::table('vehicle_geofence_states')->where('rule_id', $id)->delete();
            DB::table('vehicle_geofence_rules')->where('id', $id)->update(['updated_at' => now()]);
            DB::afterCommit(fn () => VehicleGeofenceRulesChanged::dispatch());
        });

        return response()->json(['saved' => true]);
    }

    public function alerts(Request $request)
    {
        $request->validate(['vehicle_id' => 'nullable|integer', 'active' => 'nullable|boolean', 'device_ids' => 'nullable|array|max:250', 'device_ids.*' => 'integer', 'per_page' => 'nullable|integer|min:1|max:250']);

        return DB::table('vehicle_geofence_alerts as a')->leftJoin('vehicles as v', 'v.id', '=', 'a.vehicle_id')->join('gps_devices as d', 'd.id', '=', 'a.gps_device_id')
            ->join('vehicle_geofence_rules as r', 'r.id', '=', 'a.rule_id')->join('geofences as g', 'g.id', '=', 'r.geofence_id')
            ->when($request->boolean('active'), fn ($q) => $q->whereNull('g.deleted_at')->whereNull('a.ended_at')->where('g.status', 'active'))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('a.vehicle_id', $request->vehicle_id))
            ->when($request->has('device_ids'), fn ($q) => $q->whereIn('a.gps_device_id', $request->input('device_ids')))
            ->select('a.*', 'v.registration_number', 'd.identifier as imei', 'g.name as geofence_name')->orderByDesc('a.started_at')->paginate((int) $request->input('per_page', 50));
    }
}
