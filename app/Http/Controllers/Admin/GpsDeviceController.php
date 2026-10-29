<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GpsDeviceRequest;
use App\Models\GpsDevice;
use App\Models\GpsDeviceLocation;
use App\Models\Vehicle;
use App\Services\Gps\HardwareFleet;
use App\Services\Gps\HardwareRegistrationService;
use App\Services\Gps\LiveDiagnosticStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GpsDeviceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', GpsDevice::class);
        $request->validate(['search' => 'nullable|string|max:100', 'protocol' => 'nullable|string|max:40',
            'status' => 'nullable|in:active,inactive,maintenance,blocked,online,offline']);
        $query = app(HardwareFleet::class)->query($request->only(['search', 'protocol', 'status']))
            ->with(['vehicle', 'latestLocation']);
        $devices = $query->orderBy('id')->paginate(25)->withQueryString();
        $stats = ['registered' => GpsDevice::count(), 'online' => 0, 'offline' => 0, 'inactive' => 0,
            'moving' => 0, 'stopped' => 0, 'parked' => 0,
            'pending identifiers' => DB::table('gps_packet_diagnostics')->where('reason', 'unauthorized_device')->whereNotNull('identifier')
                ->whereNotIn('identifier', GpsDevice::select('identifier'))->distinct()->count('identifier')];
        $fleetPage = app(HardwareFleet::class)->page($request->only(['search', 'protocol', 'status']));
        $positions = $fleetPage['devices'];
        $fleetMeta = $fleetPage['meta'];
        foreach ($positions as $position) {
            if ($position['device_id'] === null) continue;
            $stats[$position['authorization'] !== 'active' ? 'inactive' : ($position['online'] ? 'online' : 'offline')]++;
            if ($position['state'] === 'moving') $stats['moving']++;
            if ($position['state'] === 'parked') $stats['parked']++;
            if (in_array($position['state'], ['parked', 'idle', 'stopped'], true)) $stats['stopped']++;
        }

        return view('admin.gps-devices.index', compact('devices', 'stats', 'positions', 'fleetMeta'));
    }

    public function fleet(Request $request)
    {
        $this->authorize('viewAny', GpsDevice::class);

        $request->validate(['search' => 'nullable|string|max:100', 'protocol' => 'nullable|string|max:40',
            'status' => 'nullable|in:active,inactive,maintenance,blocked,online,offline', 'after' => 'nullable|integer|min:0']);

        return response()->json(app(HardwareFleet::class)->page($request->only(['search', 'protocol', 'status']), (int) $request->query('after', 0)))
            ->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request)
    {
        $this->authorize('manage', GpsDevice::class);
        $identifier = (string) $request->query('identifier', '');
        if (! preg_match('/^[0-9]{15}$/D', $identifier)) {
            $identifier = '';
        }

        return view('admin.gps-devices.form', ['gpsDevice' => new GpsDevice(['identifier' => $identifier, 'status' => 'inactive']), 'vehicles' => Vehicle::orderBy('registration_number')->get()]);
    }

    public function edit(GpsDevice $gpsDevice)
    {
        $this->authorize('manage', GpsDevice::class);

        return view('admin.gps-devices.form', ['gpsDevice' => $gpsDevice, 'vehicles' => Vehicle::orderBy('registration_number')->get()]);
    }

    public function store(GpsDeviceRequest $request, HardwareRegistrationService $service)
    {
        return $this->save($request, $service);
    }

    public function update(GpsDeviceRequest $request, GpsDevice $gpsDevice, HardwareRegistrationService $service)
    {
        return $this->save($request, $service, $gpsDevice);
    }

    private function save(GpsDeviceRequest $request, HardwareRegistrationService $service, ?GpsDevice $device = null)
    {
        $data = $request->safe()->except('documents');
        // Keep server-managed document references; never accept client-supplied paths.
        $details = collect($data['details'] ?? [])->only(['serial_number', 'category', 'sim_number', 'sim_iccid', 'network_provider', 'firmware_version', 'installation_date', 'notes'])->all();
        $details['documents'] = $device?->details['documents'] ?? [];
        $uploadedPaths = [];
        foreach ($request->file('documents', []) as $file) {
            $path = $file->store('gps-documents', 'local');
            abort_unless($path, 500, 'Private document storage failed.');
            $uploadedPaths[] = $path;
            $details['documents'][] = ['path' => $path, 'name' => $file->getClientOriginalName()];
        }
        $data['details'] = $details;
        $data['configuration'] = collect($data['configuration'])->only(['transport', 'server_host', 'server_port', 'reporting_interval', 'heartbeat_interval', 'timezone', 'apn'])->all();
        try {
            $saved = $service->save($data, $device);
        } catch (ValidationException $exception) {
            Storage::disk('local')->delete($uploadedPaths);
            throw $exception;
        }

        $response = redirect()->route('admin.gps-devices.show', $saved)
            ->with('status', 'Device saved. Configuration is recorded only; no commands were transmitted.');
        if ($saved->allowlistSyncPending) {
            $response->with('gps_gateway_warning', 'Device saved. GPS gateway synchronization is pending. Live tracking will use the saved changes after the gateway reconnects and synchronizes.');
        }

        return $response;
    }

    public function show(GpsDevice $gpsDevice, Request $request)
    {
        $this->authorize('viewAny', GpsDevice::class);
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $locations = GpsDeviceLocation::where('gps_device_id', $gpsDevice->id)
            ->when($request->filled('from'), fn ($q) => $q->where('recorded_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('recorded_at', '<=', $request->query('to')))
            ->orderByDesc('recorded_at')->paginate(50)->withQueryString();
        $assignments = DB::table('gps_device_assignments')->join('vehicles', 'vehicles.id', '=', 'vehicle_id')
            ->where('gps_device_id', $gpsDevice->id)->select('gps_device_assignments.*', 'vehicles.registration_number')->orderByDesc('installed_at')->get();
        $sessions = DB::table('gps_connection_sessions')->where('gps_device_id', $gpsDevice->id)->orderByDesc('connected_at')->limit(20)->get();

        return view('admin.gps-devices.show', compact('gpsDevice', 'locations', 'assignments', 'sessions'));
    }

    public function diagnostics()
    {
        $this->authorize('viewAny', GpsDevice::class);

        return view('admin.gps-devices.diagnostics');
    }

    public function diagnosticsLive(Request $request)
    {
        $this->authorize('viewAny', GpsDevice::class);
        $data = $request->validate(['device_id' => 'nullable|integer|min:1']);
        $devices = app(HardwareFleet::class)->page();
        if (! empty($data['device_id'])) {
            $devices['devices'] = array_values(array_filter($devices['devices'], fn (array $device) => $device['device_id'] === $data['device_id']));
        }
        $ids = array_values(array_filter(array_column($devices['devices'], 'device_id')));
        $sessions = $ids ? DB::table('gps_connection_sessions')->whereIn('gps_device_id', $ids)
            ->orderByDesc('connected_at')->get(['gps_device_id', 'connected_at', 'disconnected_at'])->groupBy('gps_device_id') : collect();
        foreach ($devices['devices'] as &$device) {
            $last = $device['device_id'] ? $sessions->get($device['device_id'])?->first() : null;
            $device['last_connection_at'] = $last?->connected_at;
            $device['last_disconnect_at'] = $last?->disconnected_at;
        }
        unset($device);

        $receiver = null;
        try { $receiver = Cache::store(config('gps.live_cache_store'))->get('gps.receiver.health'); } catch (\Throwable) {}
        $database = ['status' => 'connected', 'latency_ms' => null];
        try {
            $started = hrtime(true); DB::select('select 1');
            $database['latency_ms'] = round((hrtime(true) - $started) / 1000000, 2);
        } catch (\Throwable) { $database = ['status' => 'error', 'latency_ms' => null]; }
        $redis = ['status' => 'unknown', 'latency_ms' => null];
        try {
            $started = hrtime(true); $pong = Redis::connection('gps')->ping();
            $redis = ['status' => (strtoupper((string) $pong) === 'PONG' || $pong === true) ? 'connected' : 'error',
                'latency_ms' => round((hrtime(true) - $started) / 1000000, 2)];
        } catch (\Throwable) { $redis = ['status' => 'error', 'latency_ms' => null]; }
        $reverb = ['status' => 'unknown'];
        $host = config('reverb.servers.reverb.host', env('REVERB_HOST', '127.0.0.1'));
        $port = (int) config('reverb.servers.reverb.port', env('REVERB_PORT', 8080));
        try {
            $probe = @stream_socket_client('tcp://'.$host.':'.$port, $errno, $error, 0.3);
            $reverb = ['status' => is_resource($probe) ? 'reachable' : 'error'];
            if (is_resource($probe)) fclose($probe);
        } catch (\Throwable) { $reverb = ['status' => 'error']; }
        $queues = [];
        foreach ([[config('gps.live_connection'), config('gps.live_queue')], [config('gps.live_connection'), config('gps.persistence_queue')], [config('gps.broadcast_connection'), config('gps.broadcast_queue')]] as [$connection, $queue]) {
            try { $queues[] = ['name' => $queue, 'depth' => Queue::connection($connection)->size($queue), 'available' => true]; }
            catch (\Throwable) { $queues[] = ['name' => $queue, 'depth' => null, 'available' => false]; }
        }

        $failed = null;
        try { $failed = DB::table('failed_jobs')->whereIn('queue', [config('gps.live_queue'), config('gps.persistence_queue'), config('gps.broadcast_queue')])->count(); } catch (\Throwable) {}
        $pipeline = null;
        try { $pipeline = Cache::store(config('gps.live_cache_store'))->get('gps.pipeline.health'); } catch (\Throwable) {}
        return response()->json(['observed_at' => now()->utc()->toIso8601String(), 'devices' => $devices['devices'],
            'health' => ['receiver' => $receiver, 'database' => $database, 'redis' => $redis, 'reverb' => $reverb,
                'queues' => $queues, 'failed_gps_jobs' => $failed, 'pipeline' => $pipeline]])
            ->header('Cache-Control', 'private, no-store');
    }

    public function diagnosticsStream(Request $request, LiveDiagnosticStream $stream)
    {
        $this->authorize('manage', GpsDevice::class);
        $data = $request->validate(['device_id' => 'nullable|integer|min:1', 'after' => 'nullable|uuid']);
        $imei = null;
        if (! empty($data['device_id'])) {
            $imei = GpsDevice::whereKey($data['device_id'])->value('identifier');
            abort_unless($imei, 404);
        }

        return response()->json(['events' => $stream->read($imei, $data['after'] ?? null)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function document(GpsDevice $gpsDevice, int $index)
    {
        $this->authorize('viewAny', GpsDevice::class);
        $document = $gpsDevice->details['documents'][$index] ?? null;
        abort_unless($document, 404);

        return Storage::disk('local')->download($document['path'], $document['name'], ['X-Content-Type-Options' => 'nosniff']);
    }
}
