@extends('layouts.app')
@section('page-title', 'Vehicles')
@push('styles') @vite('resources/css/vehicle-registration.css') @endpush
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><span class="registration-eyebrow">YOUR CONNECTED FLEET</span><h2 class="fw-bold mt-2">Every vehicle. One place.</h2><p class="text-muted mb-0">{{ $vehicles->total() }} registered vehicles &middot; {{ $pendingDevices->total() }} awaiting vehicle number</p></div><div><a class="btn btn-light" href="{{ route('admin.device-models.index') }}">Device models</a> <a class="btn btn-primary px-4" href="{{ route('admin.vehicles.create') }}">+ Register vehicle</a></div></div>
<form class="d-flex gap-2 mb-4" method="get"><input name="search" aria-label="Search vehicles" class="form-control" value="{{ request('search') }}" placeholder="Search registration, vehicle name or IMEI"><button class="btn btn-dark px-4">Search</button><a class="btn btn-outline-primary text-nowrap" href="{{ route('admin.gps-devices.index') }}">Live map</a></form>
@include('admin.vehicles.pending-devices')
<h4 class="fw-bold mb-3">Registered vehicles <span class="badge bg-light text-dark">{{ $vehicles->total() }}</span></h4>
<div class="row g-4">@forelse($vehicles as $vehicle)
@php
 $device = $vehicle->gpsDevice;
 $icon = config('fleet.icons.'.($vehicle->details['icon'] ?? $vehicle->details['type'] ?? 'car').'.icon','fa-car-side');
 $photoIndex = collect($vehicle->details['documents'] ?? [])->search(fn($d) => ($d['kind'] ?? null) === 'vehicle' && str_starts_with($d['mime'] ?? '', 'image/'));
@endphp
<div class="col-md-6 col-xl-4"><article class="card vehicle-card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
@if($photoIndex !== false)<img class="vehicle-card-photo" src="{{ route('admin.vehicles.document',[$vehicle,$photoIndex,'preview'=>1]) }}" alt="{{ $vehicle->name }}" loading="lazy">@else<div class="vehicle-card-placeholder"><i class="fa-solid {{ $icon }}"></i></div>@endif
<div class="card-body p-4"><div class="d-flex justify-content-between align-items-center"><span class="text-primary"><i class="fa-solid {{ $icon }} me-2"></i>{{ config('fleet.icons.'.($vehicle->details['icon'] ?? 'car').'.label','Vehicle') }}</span><span class="badge bg-light text-dark">{{ ucfirst($vehicle->status) }}</span></div>
<h4 class="fw-bold mt-3 mb-1">{{ $vehicle->registration_number }}</h4><p class="text-muted">{{ $vehicle->name }}</p>
@if($device)<div class="mb-3"><span class="badge {{ $device->is_online ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $device->is_online ? 'Online' : 'Offline' }}</span> @if(!$device->latest_location_id)<span class="small text-muted ms-1">Awaiting first GPS fix</span>@endif</div>@endif
<dl><dt>IMEI</dt><dd>{{ $device?->identifier ?: 'Not installed' }}</dd><dt>Device model</dt><dd>{{ $device?->model ?: '-' }}</dd><dt>Device status</dt><dd>{{ ucfirst($device?->status ?? 'Unassigned') }}</dd><dt>SIM provider</dt><dd>{{ $device?->details['network_provider'] ?? '-' }}</dd><dt>SIM number</dt><dd>{{ $device?->details['sim_number'] ?? '-' }}</dd><dt>Driver</dt><dd>{{ $vehicle->details['driver_name'] ?? 'Not assigned' }}</dd></dl>
<div class="d-flex flex-wrap gap-2 border-top pt-3"><a class="btn btn-sm btn-primary" href="{{ route('admin.vehicles.show',$vehicle) }}">View details</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.vehicles.edit',$vehicle) }}">Edit</a><a class="btn btn-sm btn-light" href="{{ route('admin.gps-devices.index') }}">Map</a></div>
</div></article></div>
@empty<div class="col-12"><div class="card card-body p-5 text-center"><i class="fa-solid fa-car-side fs-1 text-primary mb-3"></i><h4>{{ request('search') ? 'No matching vehicles' : 'Build your connected fleet' }}</h4><p class="text-muted">Register a vehicle and its GPS device together.</p><a href="{{ route('admin.vehicles.create') }}">Register vehicle &rarr;</a></div></div>@endforelse</div><div class="mt-4">{{ $vehicles->links() }}</div>
@endsection
