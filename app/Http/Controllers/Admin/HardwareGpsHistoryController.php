<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GpsDevice;
use App\Models\GpsDeviceLocation;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HardwareGpsHistoryController extends Controller
{
    private const TIMEZONE = 'Asia/Kolkata';

    public function index()
    {
        return view('admin.monitoring.history');
    }

    public function targets(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'kind' => 'required|in:vehicle,device']);
        $term = trim($request->input('q', ''));
        if ($request->kind === 'vehicle') {
            $items = Vehicle::query()->when($term !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('registration_number', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%')))
                ->orderBy('registration_number')->limit(51)->get(['id', 'registration_number', 'name']);
            $data = $items->take(50)->map(fn ($v) => ['value' => 'vehicle:'.$v->id, 'label' => $v->registration_number.' · '.$v->name]);
        } else {
            $items = GpsDevice::with('vehicle')->when($term !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('identifier', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%')
                ->orWhereHas('vehicle', fn ($v) => $v->where('registration_number', 'like', '%'.$term.'%'))))
                ->orderBy('identifier')->limit(51)->get();
            $data = $items->take(50)->map(fn ($d) => ['value' => 'device:'.$d->id,
                'label' => $d->identifier.' · '.($d->vehicle?->registration_number ?? $d->name)]);
        }

        return response()->json(['data' => $data->values(), 'has_more' => $items->count() > 50]);
    }

    public function data(Request $request)
    {
        $input = $request->validate([
            'target' => ['required', 'regex:/^(vehicle|device):[1-9][0-9]*$/'],
            'preset' => 'required|in:today,yesterday,last_7_days,last_30_days,custom',
            'from' => 'required_if:preset,custom|nullable|date_format:Y-m-d\TH:i',
            'to' => 'required_if:preset,custom|nullable|date_format:Y-m-d\TH:i|after_or_equal:from',
            'report' => 'required|in:route,geofence,all',
            'records_only' => 'nullable|boolean',
            'route_page' => 'nullable|integer|min:1', 'geofence_page' => 'nullable|integer|min:1',
        ]);
        [$kind, $id] = explode(':', $input['target']);
        $target = $kind === 'vehicle' ? Vehicle::findOrFail($id) : GpsDevice::with('vehicle')->findOrFail($id);
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        [$from, $to] = match ($input['preset']) {
            'today' => [$today, $today->endOfDay()],
            'yesterday' => [$today->subDay(), $today->subDay()->endOfDay()],
            'last_7_days' => [$today->subDays(6), $today->endOfDay()],
            'last_30_days' => [$today->subDays(29), $today->endOfDay()],
            'custom' => [CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input['from'], self::TIMEZONE),
                CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input['to'], self::TIMEZONE)->endOfMinute()],
        };
        $start = $from->utc()->format('Y-m-d H:i:s');
        $end = $to->utc()->format('Y-m-d H:i:s');
        $result = ['target' => ['label' => $kind === 'vehicle' ? $target->registration_number : $target->identifier,
            'kind' => $kind], 'range' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'timezone' => self::TIMEZONE]];
        if (in_array($input['report'], ['route', 'all'], true)) {
            // Use the assignment stored on each point, not today's assignment.
            $query = GpsDeviceLocation::where($kind === 'vehicle' ? 'vehicle_id' : 'gps_device_id', $id)
                ->where('gps_fix', true)->whereBetween('recorded_at', [$start, $end]);
            $result['route'] = ['records' => (clone $query)->orderBy('recorded_at')->orderBy('id')->paginate(50, ['*'], 'route_page')];
            // PDF collection does not need to reload the 5,000-point map on every table page.
            if (! $request->boolean('records_only')) {
                $stats = (clone $query)->selectRaw('COUNT(*) as points, MAX(speed) as max_speed, MIN(recorded_at) as first_at, MAX(recorded_at) as last_at')->first();
                $points = (clone $query)->orderBy('recorded_at')->orderBy('id')->limit(5001)
                    ->get(['id', 'latitude', 'longitude', 'recorded_at', 'speed', 'heading', 'gps_device_id']);
                $result['route'] += ['summary' => $stats, 'map_points' => $points->take(5000)->values(),
                    'map_truncated' => $points->count() > 5000];
            }
        }
        if (in_array($input['report'], ['geofence', 'all'], true)) {
            $query = DB::table('vehicle_geofence_alerts as a')
                ->leftJoin('vehicle_geofence_rules as r', 'r.id', '=', 'a.rule_id')
                ->leftJoin('geofences as g', 'g.id', '=', 'r.geofence_id')
                ->where($kind === 'vehicle' ? 'a.vehicle_id' : 'a.gps_device_id', $id)
                ->where('a.started_at', '<=', $end)
                ->where(fn ($q) => $q->whereNull('a.ended_at')->orWhere('a.ended_at', '>=', $start));
            $result['geofence'] = $query->orderByDesc('a.started_at')->orderByDesc('a.id')
                ->paginate(50, ['a.*', 'g.name as geofence_name'], 'geofence_page');
        }

        return response()->json($result);
    }
}
