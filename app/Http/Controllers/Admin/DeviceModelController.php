<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeviceModelRequest;
use App\Models\GpsDevice;
use App\Models\GpsDeviceModel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeviceModelController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', GpsDevice::class);
        $request->validate(['search' => 'nullable|string|max:100']);
        $models = GpsDeviceModel::when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('name')->paginate(12)->withQueryString();

        return view('admin.device-models.index', compact('models'));
    }

    public function create()
    {
        $this->authorize('manage', GpsDevice::class);

        return view('admin.device-models.form', ['deviceModel' => new GpsDeviceModel(['active' => true])]);
    }

    public function edit(GpsDeviceModel $deviceModel)
    {
        $this->authorize('manage', GpsDevice::class);

        return view('admin.device-models.form', compact('deviceModel'));
    }

    public function store(DeviceModelRequest $request)
    {
        GpsDeviceModel::create($request->validated());

        return redirect()->route('admin.device-models.index')->with('status', 'Device model created.');
    }

    public function update(DeviceModelRequest $request, GpsDeviceModel $deviceModel)
    {
        $data = $request->validated();
        // Catalog edits must not silently reconfigure already installed hardware.
        if (GpsDevice::where('gps_device_model_id', $deviceModel->id)->exists()
            && ($data['protocol'] !== $deviceModel->protocol || ($data['protocol_version'] ?? null) !== $deviceModel->protocol_version)) {
            throw ValidationException::withMessages(['protocol' => 'This model is installed. Create a new model for a different protocol/version.']);
        }
        $deviceModel->update($data);

        return redirect()->route('admin.device-models.index')->with('status', 'Device model updated.');
    }

    public function destroy(GpsDeviceModel $deviceModel)
    {
        $this->authorize('manage', GpsDevice::class);
        if (GpsDevice::where('gps_device_model_id', $deviceModel->id)->exists()) {
            return back()->withErrors(['model' => 'This model is installed. Mark it inactive instead of deleting it.']);
        }
        $deviceModel->delete();

        return redirect()->route('admin.device-models.index')->with('status', 'Device model deleted.');
    }
}
