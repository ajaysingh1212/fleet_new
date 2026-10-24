<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VehicleRequest;
use App\Models\GpsDevice;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Storage;

class VehicleController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $this->authorize('viewAny', GpsDevice::class);

        $request->validate(['search'=>'nullable|string|max:100']);
        return view('admin.vehicles.index', ['vehicles' => Vehicle::when($request->filled('search'),fn($q)=>$q->where(fn($s)=>$s->where('registration_number','like','%'.$request->search.'%')->orWhere('name','like','%'.$request->search.'%')))->orderBy('registration_number')->paginate(12)->withQueryString()]);
    }

    public function create()
    {
        $this->authorize('manage', GpsDevice::class);

        return view('admin.vehicles.form', ['vehicle' => new Vehicle(['status' => 'active'])]);
    }

    public function edit(Vehicle $vehicle)
    {
        $this->authorize('manage', GpsDevice::class);

        return view('admin.vehicles.form', compact('vehicle'));
    }

    public function store(VehicleRequest $request)
    {
        return $this->save($request);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle)
    {
        return $this->save($request, $vehicle);
    }

    private function save(VehicleRequest $request, ?Vehicle $vehicle = null)
    {
        $data = $request->safe()->except('documents');
        $data['details'] = collect($data['details'] ?? [])->only(array_map(fn ($key) => substr($key, 8), array_keys(VehicleRequest::detailRules())))->all();
        $data['details']['documents'] = $vehicle?->details['documents'] ?? [];
        foreach ($request->file('documents', []) as $file) {
            $path = $file->store('gps-documents', 'local');
            abort_unless($path, 500, 'Private document storage failed.');
            $data['details']['documents'][] = ['path' => $path, 'name' => $file->getClientOriginalName()];
        }
        $vehicle ??= new Vehicle;
        $vehicle->fill($data)->save();

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle saved.');
    }

    public function document(Vehicle $vehicle, int $index)
    {
        $this->authorize('viewAny', GpsDevice::class);
        $document = $vehicle->details['documents'][$index] ?? null;
        abort_unless($document, 404);

        return Storage::disk('local')->download($document['path'], $document['name'], ['X-Content-Type-Options' => 'nosniff']);
    }
}
