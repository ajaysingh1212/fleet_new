<?php

namespace App\Http\Controllers\Admin;

use App\Events\HardwareLocationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GpsDeviceRequest;
use App\Models\GpsDevice;
use App\Models\GpsDeviceLocation;
use App\Models\Vehicle;
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
        $query = GpsDevice::with(['vehicle', 'latestLocation']);
        if ($term = $request->query('search')) {
            $query->where(fn ($q) => $q->where('identifier', 'like', '%'.$term.'%')
                ->orWhere('name', 'like', '%'.$term.'%')->orWhereHas('vehicle', fn ($q) => $q->where('registration_number', 'like', '%'.$term.'%')));
        }
        if ($request->filled('protocol')) {
            $query->where('protocol', $request->query('protocol'));
        }
        $status = $request->query('status');
        if ($status === 'online') {
            $query->where('status', 'active')->where('last_packet_at', '>=', now()->subSeconds(config('gps.idle_seconds')));
        } elseif ($status === 'offline') {
            $query->where('status', 'active')->where(fn ($q) => $q->whereNull('last_packet_at')->orWhere('last_packet_at', '<', now()->subSeconds(config('gps.idle_seconds'))));
        } elseif ($status) {
            $query->where('status', $status);
        }
        $devices = $query->orderBy('id')->paginate(25)->withQueryString();
        $online = GpsDevice::where('status', 'active')->where('last_packet_at', '>=', now()->subSeconds(config('gps.idle_seconds')))->count();
        $moving = GpsDevice::where('status', 'active')->where('last_packet_at', '>=', now()->subSeconds(config('gps.idle_seconds')))
            ->whereHas('latestLocation', fn ($q) => $q->where('speed', '>', 1.8))->count();
        $stopped = GpsDevice::where('status', 'active')->where('last_packet_at', '>=', now()->subSeconds(config('gps.idle_seconds')))
            ->whereHas('latestLocation', fn ($q) => $q->whereNotNull('speed')->where('speed', '<=', 1.8))->count();
        $stats = ['registered' => GpsDevice::count(), 'online' => $online,
            'offline' => GpsDevice::where('status', 'active')->count() - $online,
            'inactive' => GpsDevice::where('status', '!=', 'active')->count(),
            'pending identifiers' => DB::table('gps_packet_diagnostics')->where('reason', 'unauthorized_device')->whereNotNull('identifier')
                ->whereNotIn('identifier', GpsDevice::select('identifier'))->distinct()->count('identifier'),
            'moving' => $moving, 'stopped' => $stopped];
        $positions = $devices->getCollection()->filter(fn ($d) => $d->latestLocation)
            ->map(fn ($d) => HardwareLocationUpdated::payload($d, $d->latestLocation))->values();

        return view('admin.gps-devices.index', compact('devices', 'stats', 'positions'));
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
