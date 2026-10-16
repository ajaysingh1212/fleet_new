@extends('layouts.app')
@section('page-title', $gpsDevice->exists ? 'Edit GPS device' : 'Register GPS device')
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $gpsDevice->exists ? route('admin.gps-devices.update', $gpsDevice) : route('admin.gps-devices.store') }}" class="card card-body">
@csrf
@if($gpsDevice->exists) @method('PUT') @endif
<h2 class="h5">Device information</h2>
<p>Leave unsupported or unknown fields blank. Activation requires an exact supported protocol version. IMEI allowlisting identifies hardware; these protocols do not provide cryptographic device authentication.</p>
<div class="row g-3">
@foreach(['name'=>'Device name', 'identifier'=>'Protocol identifier (15 digits)', 'imei'=>'IMEI (same as identifier, optional)', 'manufacturer'=>'Manufacturer', 'model'=>'Exact model number'] as $key=>$label)
<div class="col-md-6"><label class="form-label" for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" class="form-control" name="{{ $key }}" value="{{ old($key, $gpsDevice->$key) }}" @required(in_array($key, ['name','identifier']))></div>
@endforeach
<div class="col-md-6"><label class="form-label" for="protocol">GPS protocol</label><select id="protocol" name="protocol" class="form-select">@foreach(config('gps.protocols') as $name=>$info)<option value="{{ $name }}" @selected(old('protocol', $gpsDevice->protocol)===$name)>{{ $name }} — {{ $info['status'] }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label" for="version">Protocol version</label><input id="version" class="form-control" name="protocol_version" value="{{ old('protocol_version', $gpsDevice->protocol_version) }}"><small>Teltonika: 8 or 8E; X1: 1.1</small></div>
<div class="col-md-3"><label class="form-label" for="status">Status</label><select id="status" name="status" class="form-select">@foreach(['inactive','active','maintenance','blocked'] as $status)<option @selected(old('status',$gpsDevice->status)===$status)>{{ $status }}</option>@endforeach</select></div>
@foreach(['serial_number'=>'Serial number','category'=>'Category','sim_number'=>'SIM number','sim_iccid'=>'SIM ICCID','network_provider'=>'SIM network provider','firmware_version'=>'Firmware version','installation_date'=>'Installation date','notes'=>'Notes'] as $key=>$label)
<div class="col-md-6"><label class="form-label" for="detail_{{ $key }}">{{ $label }}</label><input id="detail_{{ $key }}" class="form-control" name="details[{{ $key }}]" type="{{ $key==='installation_date' ? 'date':'text' }}" value="{{ old('details.'.$key, $gpsDevice->details[$key] ?? '') }}"></div>
@endforeach
</div>
<h2 class="h5 mt-4">Vehicle assignment</h2>
<label class="form-label" for="vehicle_id">Existing vehicle</label><select class="form-select" id="vehicle_id" name="vehicle_id"><option value="">Unassigned / create below</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected((string)old('vehicle_id',$gpsDevice->vehicle_id)===(string)$vehicle->id)>{{ $vehicle->registration_number }} — {{ $vehicle->name }}</option>@endforeach</select>
<details class="mt-3"><summary>Create a new vehicle during registration</summary><p>Complete the registration and name fields to create a vehicle. Leave existing vehicle unselected.</p>
@include('admin.vehicles.fields', ['prefix'=>'new_vehicle', 'vehicle'=>new \App\Models\Vehicle, 'inline'=>true])
</details>
<h2 class="h5 mt-4">Device configuration</h2>
<p class="alert alert-info">Recorded only. No remote configuration commands are supported for these adapters. Set server/APN/intervals using the exact model's documented tools. UDP is not implemented. Unknown capabilities remain blank.</p>
<div class="row g-3">
@foreach(['server_host'=>'Server hostname/IP','server_port'=>'Server port','apn'=>'APN name','reporting_interval'=>'Reporting interval (seconds, if supported)','heartbeat_interval'=>'Heartbeat interval (seconds, if supported)','timezone'=>'Device timezone'] as $key=>$label)
<div class="col-md-6"><label class="form-label" for="config_{{ $key }}">{{ $label }}</label><input id="config_{{ $key }}" class="form-control" name="configuration[{{ $key }}]" value="{{ old('configuration.'.$key, $gpsDevice->configuration[$key] ?? match($key){'server_port'=>9000,'timezone'=>'UTC',default=>''}) }}"></div>
@endforeach
<div class="col-md-6"><label for="transport" class="form-label">Transport</label><select id="transport" class="form-select" name="configuration[transport]">@foreach(['tcp','udp'] as $transport)<option @selected(old('configuration.transport',$gpsDevice->configuration['transport'] ?? 'tcp')===$transport)>{{ $transport }}</option>@endforeach</select></div>
<div class="col-md-6"><label for="documents" class="form-label">Installation documents (private; PDF/JPEG/PNG, max 5 × 5 MB per submission)</label><input id="documents" type="file" name="documents[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png"></div>
</div>
<div class="mt-4"><button class="btn btn-primary">Save device</button> <a href="{{ route('admin.gps-devices.index') }}" class="btn btn-secondary">Cancel</a></div>
</form>
@endsection
