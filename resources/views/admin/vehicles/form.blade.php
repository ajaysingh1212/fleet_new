@extends('layouts.app')
@section('page-title', $vehicle->exists ? 'Edit vehicle registration' : 'Vehicle & device registration')
@push('styles') @vite('resources/css/vehicle-registration.css') @endpush
@section('content')
@php($device = $vehicle->gpsDevice)
<div class="registration-shell">
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><span class="registration-eyebrow">FLEET ONBOARDING</span><h2 class="fw-bold mt-2">{{ $vehicle->exists ? $vehicle->registration_number : 'One registration. Ready to track.' }}</h2><p class="text-muted mb-0">Vehicle, GPS hardware, SIM and documents &mdash; together.</p></div><div class="align-self-center"><a class="btn btn-light" href="{{ route('admin.vehicles.index') }}">All vehicles</a> <a class="btn btn-outline-primary" href="{{ route('admin.device-models.index') }}">Device models</a></div></div>
<form id="vehicle-registration" method="POST" enctype="multipart/form-data" action="{{ $vehicle->exists ? route('admin.vehicles.update', $vehicle) : ($device?->exists ? route('admin.vehicles.attach-device', $device) : route('admin.vehicles.store')) }}">
@csrf @if($vehicle->exists) @method('PUT') @endif
<div class="registration-layout">
<aside class="registration-rail"><span class="small opacity-75">{{ $vehicle->exists ? 'UPDATE YOUR FLEET' : 'NEW REGISTRATION' }}</span><h3 class="mt-3 mb-4">Every detail.<br>One connected vehicle.</h3>
<nav aria-label="Registration steps">@foreach(['Vehicle', 'GPS device', 'SIM & connectivity', 'Operations', 'Photos & documents'] as $i => $step)<button type="button" class="registration-step" data-step="{{ $i }}"><span>{{ $i + 1 }}</span><span>{{ $step }}</span><i class="fa-solid fa-check ms-auto registration-check" aria-hidden="true"></i></button>@endforeach</nav>
<div class="registration-rail-note"><i class="fa-solid fa-satellite-dish mb-2"></i><p class="small mb-0">A saved, active tracker appears on the map after its first valid GPS fix reaches your server.</p></div></aside>
<div class="registration-main">
<section data-panel="0"><div class="registration-section-title"><span>01 / VEHICLE</span><h3>Meet your vehicle</h3><p>Registration, ownership and the icon you will see on the map.</p></div><div class="row g-4">
@if(!$vehicle->exists && $device?->exists)<div class="col-12"><div class="alert alert-info mb-0"><strong>Add vehicle number for IMEI {{ $device->identifier }}</strong><div>Existing tracker and its history will be linked to this registration. No duplicate device will be created.</div><div class="small mt-2">Model: {{ $device->model ?: 'Unconfirmed' }} · {{ strtoupper($device->protocol) }} {{ $device->protocol_version }}</div></div></div>@endif
<x-fleet-field field="registration_number" label="Registration number" :value="$vehicle->registration_number" required />
<x-fleet-field field="name" label="Vehicle name" :value="$vehicle->name" required />
<x-fleet-field field="status" label="Vehicle status" :value="$vehicle->status" :options="['active'=>'Active','inactive'=>'Inactive','maintenance'=>'Maintenance']" required />
<x-fleet-field field="owner_user_id" label="Dashboard owner" :value="$vehicle->owner_user_id" :options="\App\Models\User::orderBy('name')->get()->mapWithKeys(fn($u)=>[$u->id=>$u->name.' ('.$u->email.')'])->all()" hint="Selected account can view this vehicle; leave blank for admin only." />
<div class="col-12"><label class="form-label fw-semibold">Vehicle type & map icon</label><div class="vehicle-icon-grid">@foreach(config('fleet.icons') as $key => $info)<label class="vehicle-icon-option"><input type="radio" name="details[icon]" value="{{ $key }}" @checked(old('details.icon', $vehicle->details['icon'] ?? $vehicle->details['type'] ?? 'car') === $key)><span><i class="fa-solid {{ $info['icon'] }}"></i><small>{{ $info['label'] }}</small></span></label>@endforeach</div></div>
@foreach(['manufacturer'=>'Vehicle manufacturer', 'model'=>'Vehicle model', 'year'=>'Manufacturing year', 'color'=>'Color', 'fuel_type'=>'Fuel type', 'vin'=>'VIN / chassis number', 'engine_number'=>'Engine number'] as $key => $label)<x-fleet-field :field="'details.'.$key" :label="$label" :value="$vehicle->details[$key] ?? null" />@endforeach
</div></section>
<section data-panel="1"><div class="registration-section-title"><span>02 / HARDWARE</span><h3>Connect your GPS device</h3><p>Select a saved model and enter the device's unique IMEI.</p></div>
<input type="hidden" name="device_enabled" value="0"><label class="registration-toggle mb-4"><input type="checkbox" name="device_enabled" id="device-enabled" value="1" @checked(old('device_enabled', $device || !$vehicle->exists))><span>{{ $device ? 'Update installed GPS device' : 'Register a GPS device with this vehicle' }}</span></label>
@if($device)<p class="small text-muted">Installed device #{{ $device->id }}. Unchecking keeps its current registration unchanged.</p>@endif
<div id="device-fields"><div class="row g-4">
@if($models->isEmpty())<div class="col-12"><div class="alert alert-info">Create a device model first, then return here to register your GPS device.</div></div>@endif
<div class="col-md-6" @error('device.device_model_id') data-invalid="true" @enderror><label class="form-label fw-semibold" for="device_device_model_id">GPS device model</label><select class="form-select" name="device[device_model_id]" id="device_device_model_id"><option value="">{{ $device ? 'Keep installed profile' : 'Select device model' }}</option>@foreach($models as $model)<option value="{{ $model->id }}" @selected((string)old('device.device_model_id', $device?->gps_device_model_id) === (string)$model->id)>{{ $model->name }} &middot; {{ strtoupper($model->protocol) }} &middot; {{ $model->network_generation }}</option>@endforeach</select></div>
<div class="col-md-6 d-flex align-items-end"><a class="btn btn-outline-primary" href="{{ route('admin.device-models.create') }}">+ Create device model</a></div>
@if($device && !$device->gps_device_model_id)<div class="col-12"><div class="alert alert-info mb-0">Current profile: {{ $device->model ?: 'Not recorded' }} &middot; {{ strtoupper($device->protocol) }} {{ $device->protocol_version }}. Select a catalog model to change it.</div></div>@endif
<div class="col-12"><div class="registration-profile" id="device-model-summary">Select a model to see protocol, version and network generation.</div></div>
<x-fleet-field field="device.identifier" label="IMEI (15 digits)" :value="$device?->identifier" />
<x-fleet-field field="device.status" label="Device status" :value="$device?->status ?? 'active'" :options="['active'=>'Active','inactive'=>'Inactive','maintenance'=>'Maintenance','blocked'=>'Blocked']" />
@foreach(['serial_number'=>'Product serial number','vts'=>'VTS ID / reference','firmware_version'=>'Firmware version'] as $key => $label)<x-fleet-field :field="'device.'.$key" :label="$label" :value="$device?->details[$key] ?? null" />@endforeach
</div></div></section>
<section data-panel="2"><div class="registration-section-title"><span>03 / CONNECTIVITY</span><h3>A SIM that keeps it connected</h3><p>Store the installed SIM and reporting settings.</p></div><div class="row g-4">
<x-fleet-field field="device.sim_number" label="SIM mobile number" :value="$device?->details['sim_number'] ?? null" />
<x-fleet-field field="device.network_provider" label="SIM provider" :value="$device?->details['network_provider'] ?? null" :options="array_combine(config('fleet.providers'),config('fleet.providers'))" />
<x-fleet-field field="device.sim_generation" label="SIM network generation" :value="$device?->details['sim_generation'] ?? null" :options="array_combine(config('fleet.networks'),config('fleet.networks'))" />
<x-fleet-field field="device.sim_iccid" label="SIM ICCID" :value="$device?->details['sim_iccid'] ?? null" />
<x-fleet-field field="device.apn" label="APN" :value="$device?->configuration['apn'] ?? null" />
<x-fleet-field field="device.reporting_interval" label="GPS reporting interval (seconds)" type="number" :value="$device?->configuration['reporting_interval'] ?? null" />
<x-fleet-field field="device.heartbeat_interval" label="Heartbeat interval (seconds)" type="number" :value="$device?->configuration['heartbeat_interval'] ?? null" />
<div class="col-12"><div class="alert alert-light border small">Receiver: TCP port 9000 &middot; device timezone UTC. Configure your tracker with your reachable GPS server address. Saving records these values; it does not send remote commands.</div></div>
</div></section>
<section data-panel="3"><div class="registration-section-title"><span>04 / OPERATIONS</span><h3>Ready for the road</h3><p>Odometer baseline, installation, driver and fleet details.</p></div><div class="row g-4">
<x-fleet-field field="details.initial_odometer" label="Odometer reading (metres)" type="number" :value="$vehicle->details['initial_odometer'] ?? null" hint="125,000 metres = 125 km. Baseline; reported device readings remain separate." />
<x-fleet-field field="details.odometer_source" label="Odometer source" :value="$vehicle->details['odometer_source'] ?? 'gps'" :options="['gps'=>'GPS distance','device'=>'Device / CAN report','manual'=>'Manual baseline']" />
<x-fleet-field field="details.engine_hours" label="Engine hours baseline" type="number" :value="$vehicle->details['engine_hours'] ?? null" />
<x-fleet-field field="details.installation_date" label="Installation date" type="date" :value="$vehicle->details['installation_date'] ?? null" />
@foreach(['owner_name'=>'Owner name','owner_phone'=>'Owner phone','driver_name'=>'Driver name','driver_phone'=>'Driver phone','driver_licence'=>'Driver licence','fleet'=>'Fleet / group','branch'=>'Branch','company'=>'Company'] as $key => $label)<x-fleet-field :field="'details.'.$key" :label="$label" :value="$vehicle->details[$key] ?? null" />@endforeach
<div class="col-12"><label for="registration-notes" class="form-label fw-semibold">Notes</label><textarea class="form-control" rows="3" name="details[notes]" id="registration-notes">{{ old('details.notes', $vehicle->details['notes'] ?? '') }}</textarea></div>
</div></section>
<section data-panel="4"><div class="registration-section-title"><span>05 / ATTACHMENTS</span><h3>Keep every record together</h3><p>Private photos and documents for authorized fleet administrators.</p></div><div class="row g-4">
@foreach(['vehicle_images'=>'Vehicle photos','documents'=>'Registration / insurance / documents','other_images'=>'Device & other photos'] as $field => $label)<div class="col-12"><label class="registration-upload" for="{{ $field }}"><i class="fa-solid {{ $field === 'documents' ? 'fa-file-lines' : 'fa-camera' }}"></i><span class="fw-semibold">{{ $label }}</span><small>Up to 5 files, 5 MB each &middot; {{ $field === 'documents' ? 'PDF, JPG, PNG' : 'JPG, PNG, WebP' }}</small><input type="file" id="{{ $field }}" name="{{ $field }}[]" multiple accept="{{ $field === 'documents' ? '.pdf,.jpg,.jpeg,.png' : '.jpg,.jpeg,.png,.webp' }}"></label><div class="registration-previews" data-previews="{{ $field }}"></div></div>@endforeach
@if($vehicle->exists)<div class="col-12"><h5>Saved attachments</h5><div class="registration-saved">@foreach($vehicle->details['documents'] ?? [] as $index => $document)<article>@if(in_array($document['mime'] ?? '', ['image/jpeg','image/png','image/webp']))<img src="{{ route('admin.vehicles.document', [$vehicle, $index, 'preview'=>1]) }}" alt="{{ $document['name'] }}" loading="lazy">@else<i class="fa-solid fa-file-pdf fs-2 text-primary"></i>@endif<a href="{{ route('admin.vehicles.document', [$vehicle, $index]) }}">{{ $document['name'] }}</a><label class="small text-danger"><input type="checkbox" name="remove_documents[]" value="{{ $index }}"> Remove on save</label></article>@endforeach</div></div>@endif
<div class="col-12"><div class="registration-profile"><strong>Registration ready</strong><p class="mb-0 small mt-1">Save to add the vehicle to your fleet. GPS position, speed and movement appear when the configured tracker sends a valid fix.</p></div></div>
</div></section>
<footer class="registration-footer"><button type="button" id="registration-back" class="btn btn-light">&larr; Back</button><span class="small text-muted" id="registration-progress">Step 1 of 5</span><button type="button" id="registration-next" class="btn btn-primary px-4">Continue &rarr;</button><button type="submit" id="registration-save" class="btn btn-primary px-4">{{ $vehicle->exists ? 'Save changes' : 'Register vehicle & device' }}</button></footer>
</div></div></form></div>
@endsection
@push('scripts')
@php($modelProfiles = $models->map(fn($m)=>$m->only(['id','name','manufacturer','protocol','protocol_version','network_generation'])))
<script type="application/json" id="registration-models">@json($modelProfiles)</script>
@vite('resources/js/vehicle-registration.js')
@endpush
