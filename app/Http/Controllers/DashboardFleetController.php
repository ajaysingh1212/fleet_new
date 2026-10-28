<?php

namespace App\Http\Controllers;

use App\Http\Resources\GeofenceResource;
use App\Models\Geofence;
use App\Models\GpsDevice;
use App\Models\Vehicle;
use App\Services\Gps\HardwareFleet;
use App\Services\Gps\HardwareLiveState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardFleetController extends Controller
{
    public function __invoke(Request $request, HardwareFleet $fleet)
    {
        $request->validate(['after' => 'nullable|integer|min:0', 'kind' => 'nullable|in:vehicles,devices']);
        $admin = $request->user()->hasAnyRole(['Super Admin', 'Admin']);
        $devicePage = $request->query('kind') === 'devices';
        // Ownership is checked on every reconciliation; query parameters never widen it.
        $vehicles = Vehicle::query()
            ->when($devicePage, fn ($q) => $q->whereRaw('1 = 0'))
            ->when(! $admin, fn ($q) => $q->where('owner_user_id', $request->user()->id))
            ->where('id', '>', (int) $request->query('after', 0))->orderBy('id')->limit(251)->get();
        $more = $vehicles->count() > 250;
        $vehicles = $vehicles->take(250);
        $devices = GpsDevice::with(['vehicle', 'latestLocation', 'latestHeartbeat'])
            ->whereIn('vehicle_id', $vehicles->pluck('id'))->get()->keyBy('vehicle_id');
        $positions = $vehicles->map(function ($vehicle) use ($devices, $fleet) {
            $device = $devices->get($vehicle->id);
            if (! $device) {
                return ['vehicle_id' => $vehicle->id, 'device_id' => null,
                    'registration' => $vehicle->registration_number, 'name' => $vehicle->name,
                    'state' => 'unassigned', 'online' => false, 'authorization' => $vehicle->status,
                    'latitude' => null, 'longitude' => null, 'speed' => null];
            }
            $position = $fleet->device($device);
            if (config('gps.live_first')) {
                $position = app(HardwareLiveState::class)->overlay($position);
            }

            return $position;
        })->values();
        $nextKind = 'vehicles';
        $nextAfter = $more ? $vehicles->last()->id : null;
        // Trackers without registration/vehicle records still belong in the
        // administrator's fleet. Keep their cursor separate from vehicle IDs.
        if ($devicePage && $admin) {
            $devices = GpsDevice::with(['vehicle', 'latestLocation', 'latestHeartbeat'])
                ->whereNull('vehicle_id')->where('id', '>', (int) $request->query('after', 0))
                ->orderBy('id')->limit(251)->get();
            $more = $devices->count() > 250;
            $devices = $devices->take(250);
            $positions = $devices->map(function ($device) use ($fleet) {
                $position = $fleet->device($device);

                return config('gps.live_first') ? app(HardwareLiveState::class)->overlay($position) : $position;
            })->values();
            $nextKind = 'devices';
            $nextAfter = $more ? $devices->last()->id : null;
        } elseif (! $devicePage && ! $more && $admin && GpsDevice::whereNull('vehicle_id')->exists()) {
            $more = true;
            $nextKind = 'devices';
            $nextAfter = 0;
        }
        $rules = DB::table('vehicle_geofence_rules')
            ->where(fn ($q) => $q->whereIn('vehicle_id', $vehicles->pluck('id'))->orWhereIn('gps_device_id', $devices->pluck('id')))->get();
        $fences = Geofence::with('points')->whereIn('id', $rules->pluck('geofence_id'))->get()->keyBy('id');
        $zones = $rules->map(function ($rule) use ($fences, $request) {
            $fence = $fences->get($rule->geofence_id);

            return $fence ? [...(new GeofenceResource($fence))->toArray($request),
                'rule_id' => $rule->id, 'vehicle_id' => $rule->vehicle_id, 'gps_device_id' => $rule->gps_device_id] : null;
        })->filter()->values();
        $alerts = DB::table('vehicle_geofence_alerts as a')
            ->join('vehicle_geofence_rules as r', 'r.id', '=', 'a.rule_id')
            ->join('geofences as g', 'g.id', '=', 'r.geofence_id')
            ->whereIn('a.gps_device_id', $devices->pluck('id'))->whereIn('r.id', $rules->pluck('id'))
            ->whereNull('a.ended_at')->whereNull('g.deleted_at')->where('g.status', 'active')
            ->select('a.*', 'g.name as geofence_name')->get();

        return response()->json(['vehicles' => $positions, 'zones' => $zones, 'alerts' => $alerts,
            'meta' => ['has_more' => $more, 'next_after' => $nextAfter, 'next_kind' => $nextKind]])
            ->header('Cache-Control', 'private, no-store');
    }
}
