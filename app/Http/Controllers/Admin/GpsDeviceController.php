<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GpsDeviceRequest;
use App\Models\GpsDevice;
use App\Models\GpsDeviceLocation;
use App\Models\Vehicle;
use App\Services\Gps\HardwareFleet;
use App\Services\Gps\HardwareRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return view('admin.gps-devices.diagnostics', ['diagnostics' => DB::table('gps_packet_diagnostics')->orderByDesc('id')->paginate(50)]);
    }

    public function document(GpsDevice $gpsDevice, int $index)
    {
        $this->authorize('viewAny', GpsDevice::class);
        $document = $gpsDevice->details['documents'][$index] ?? null;
        abort_unless($document, 404);

        return Storage::disk('local')->download($document['path'], $document['name'], ['X-Content-Type-Options' => 'nosniff']);
    }
}
