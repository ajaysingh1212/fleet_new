@extends('layouts.app')
@section('page-title', $deviceModel->exists ? 'Edit device model' : 'New device model')
@section('content')
<div class="mx-auto" style="max-width:960px">
<a href="{{ route('admin.device-models.index') }}">← Device models</a>
<div class="card border-0 shadow-sm rounded-4 mt-3"><div class="card-body p-4 p-md-5">
<span class="text-primary small fw-bold">HARDWARE CATALOG</span><h2 class="fw-bold mt-2">{{ $deviceModel->exists ? $deviceModel->name : 'Add a GPS device model' }}</h2>
<p class="text-muted">Create reusable model profiles for vehicle registration. Use the protocol and version documented for the hardware.</p>
<form method="post" action="{{ $deviceModel->exists ? route('admin.device-models.update', $deviceModel) : route('admin.device-models.store') }}">
@csrf @if($deviceModel->exists) @method('PUT') @endif
<div class="row g-4">
<x-fleet-field field="name" label="Model name" :value="$deviceModel->name" required />
<x-fleet-field field="manufacturer" label="Manufacturer" :value="$deviceModel->manufacturer" />
<x-fleet-field field="protocol" label="GPS protocol" :value="$deviceModel->protocol" :options="collect([...array_keys(config('gps.protocols')), 'gt05', 'tk103'])->mapWithKeys(fn($p) => [$p => strtoupper($p).(empty(config('gps.protocols.'.$p.'.versions')) ? ' · catalog only' : '')])->all()" required />
<x-fleet-field field="protocol_version" label="Protocol version" :value="$deviceModel->protocol_version" />
<x-fleet-field field="network_generation" label="Device network" :value="$deviceModel->network_generation" :options="array_combine(config('fleet.networks'), config('fleet.networks'))" />
<x-fleet-field field="active" label="Catalog status" :value="(int)$deviceModel->active" :options="[1 => 'Active · available for registration', 0 => 'Inactive']" required />
<div class="col-12"><label class="form-label" for="notes">Notes / specifications</label><textarea id="notes" class="form-control" name="notes" rows="3">{{ old('notes', $deviceModel->notes) }}</textarea></div>
<div class="col-12"><div class="bg-light rounded-3 p-3 small"><strong>Supported versions</strong>@foreach(config('gps.protocols') as $protocol => $info) @if($info['versions'])<div>{{ strtoupper($protocol) }}: {{ implode(', ', $info['versions']) }}</div>@endif @endforeach<div class="text-muted mt-2">Catalog-only protocols can be recorded with an inactive device. Activation requires an implemented version.</div></div></div>
<div class="col-12"><button class="btn btn-primary px-4">Save model</button> <a class="btn btn-light" href="{{ route('admin.device-models.index') }}">Cancel</a></div>
</div></form></div></div></div>
@endsection
