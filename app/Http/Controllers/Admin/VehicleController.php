<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VehicleRegistrationRequest;
use App\Models\GpsDevice;
use App\Models\GpsDeviceModel;
use App\Models\Vehicle;
use App\Services\Gps\HardwareRegistrationService;
use App\Services\Gps\VehicleRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', GpsDevice::class);

        $request->validate(['search' => 'nullable|string|max:100']);

        return view('admin.vehicles.index', [
            'pendingDevices' => GpsDevice::whereNull('vehicle_id')
                ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s->where('identifier', 'like', '%'.$request->search.'%')->orWhere('name', 'like', '%'.$request->search.'%')->orWhere('model', 'like', '%'.$request->search.'%')))
                ->orderByDesc('last_packet_at')->orderBy('id')->paginate(12, ['*'], 'pending_page')->withQueryString(),
            'vehicles' => Vehicle::with('gpsDevice')->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s->where('registration_number', 'like', '%'.$request->search.'%')->orWhere('name', 'like', '%'.$request->search.'%')->orWhereHas('gpsDevice', fn ($d) => $d->where('identifier', 'like', '%'.$request->search.'%'))))->orderBy('registration_number')->paginate(12)->withQueryString()]);
    }

    public function create()
    {
        $this->authorize('manage', GpsDevice::class);

        return $this->form(new Vehicle(['status' => 'active']));
    }

    public function fromDevice(GpsDevice $gpsDevice)
    {
        $this->authorize('manage', GpsDevice::class);
        abort_unless($gpsDevice->vehicle_id === null, 409, 'This tracker is already assigned. Open its vehicle registration instead.');
        $vehicle = new Vehicle(['name' => $gpsDevice->name, 'status' => 'active']);
        $vehicle->setRelation('gpsDevice', $gpsDevice);

        return $this->form($vehicle);
    }

    public function edit(Vehicle $vehicle)
    {
        $this->authorize('manage', GpsDevice::class);

        return $this->form($vehicle);
    }

    public function store(VehicleRegistrationRequest $request)
    {
        return $this->save($request);
    }

    public function attachDevice(VehicleRegistrationRequest $request, GpsDevice $gpsDevice)
    {
        return $this->save($request);
    }

    public function update(VehicleRegistrationRequest $request, Vehicle $vehicle)
    {
        return $this->save($request, $vehicle);
    }

    private function form(Vehicle $vehicle)
    {
        $vehicle->loadMissing('gpsDevice');
        $models = GpsDeviceModel::where(fn ($q) => $q->where('active', true)->orWhere('id', $vehicle->gpsDevice?->gps_device_model_id))->orderBy('name')->get();

        return view('admin.vehicles.form', compact('vehicle', 'models'));
    }

    private function save(VehicleRegistrationRequest $request, ?Vehicle $vehicle = null)
    {
        $vehicle = app(VehicleRegistrationService::class)->save($request, $vehicle);

        $response = redirect()->route('admin.vehicles.edit', $vehicle)->with('status', 'Vehicle and registration saved. Live position appears after the tracker sends a valid GPS fix.');
        if ($vehicle->gpsDevice?->allowlistSyncPending) {
            $response->with('gps_gateway_warning', 'Registration saved. Gateway synchronization is pending.');
        }

        return $response;
    }

    public function show(Vehicle $vehicle)
    {
        $this->authorize('viewAny', GpsDevice::class);
        $vehicle->load('gpsDevice');

        return view('admin.vehicles.show', compact('vehicle'));
    }

    public function destroy(Vehicle $vehicle)
    {
        $this->authorize('manage', GpsDevice::class);
        DB::transaction(function () use ($vehicle) {
            $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicle->id);
            if ($device = $vehicle->gpsDevice) {
                app(HardwareRegistrationService::class)->save([...$device->only(['name', 'identifier', 'imei', 'manufacturer', 'model', 'gps_device_model_id', 'protocol', 'protocol_version', 'details', 'configuration']),
                    'vehicle_id' => $vehicle->id, 'status' => 'inactive'], $device);
            }
            $vehicle->delete();
        });

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle deleted from the active fleet. Device disabled; tracking history retained.');
    }

    public function document(Vehicle $vehicle, int $index)
    {
        $this->authorize('viewAny', GpsDevice::class);
        $document = $vehicle->details['documents'][$index] ?? null;
        abort_unless($document, 404);

        if (request()->boolean('preview') && in_array($document['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return Storage::disk('local')->response($document['path'], $document['name'], ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
        }

        return Storage::disk('local')->download($document['path'], $document['name'], ['X-Content-Type-Options' => 'nosniff']);
    }
}
