@php($inline = $inline ?? false)
<div class="row g-3">
<div class="col-md-6">
    <label class="form-label" for="{{ $prefix }}_owner_user_id">Dashboard owner account</label>
    <select class="form-select" id="{{ $prefix }}_owner_user_id" name="{{ $prefix ? $prefix.'[owner_user_id]' : 'owner_user_id' }}">
        <option value="">Admin only / no owner assigned</option>
        @foreach(\App\Models\User::orderBy('name')->get(['id', 'name', 'email']) as $owner)
            <option value="{{ $owner->id }}" @selected((string)old(($prefix ? $prefix.'.' : '').'owner_user_id', $vehicle->owner_user_id) === (string)$owner->id)>{{ $owner->name }} ({{ $owner->email }})</option>
        @endforeach
    </select>
    <small class="text-muted">This account can view this vehicle on its dashboard.</small>
</div>
@foreach(['registration_number'=>'Vehicle registration number','name'=>'Vehicle name','status'=>'Vehicle status (active/inactive/maintenance)'] as $key=>$label)
<div class="col-md-6"><label class="form-label" for="{{ $prefix }}_{{ $key }}">{{ $label }}</label><input class="form-control" id="{{ $prefix }}_{{ $key }}" name="{{ $prefix ? $prefix.'['.$key.']' : $key }}" value="{{ old(($prefix ? $prefix.'.':'').$key, $vehicle->$key ?? ($key==='status'?'active':'')) }}" @required(!$inline)></div>
@endforeach
@foreach(['type'=>'Vehicle type','manufacturer'=>'Make/manufacturer','model'=>'Model','year'=>'Manufacturing year','color'=>'Color','fuel_type'=>'Fuel type','vin'=>'VIN/chassis number','engine_number'=>'Engine number','owner_name'=>'Owner name','owner_phone'=>'Owner phone','driver_name'=>'Assigned driver','driver_phone'=>'Driver phone','driver_licence'=>'Driver licence','fleet'=>'Vehicle group/fleet','branch'=>'Branch','company'=>'Company','installation_date'=>'Installation date','initial_odometer'=>'Initial odometer (metres)','notes'=>'Notes'] as $key=>$label)
<div class="col-md-6"><label class="form-label" for="{{ $prefix }}_v_{{ $key }}">{{ $label }}</label><input class="form-control" id="{{ $prefix }}_v_{{ $key }}" name="{{ $prefix ? $prefix.'[details]['.$key.']' : 'details['.$key.']' }}" type="{{ $key==='installation_date'?'date':'text' }}" value="{{ old(($prefix?$prefix.'.':'').'details.'.$key, $vehicle->details[$key] ?? '') }}"></div>
@endforeach
</div>
