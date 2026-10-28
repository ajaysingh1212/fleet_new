@extends('layouts.app')
@section('page-title', 'Device models')
@section('content')
<div class="d-flex flex-wrap gap-3 justify-content-between mb-4"><div><span class="text-primary small fw-bold">HARDWARE CATALOG</span><h2 class="fw-bold">GPS device models</h2><p class="text-muted mb-0">Reusable profiles for your connected fleet.</p></div><div class="align-self-center"><a class="btn btn-light" href="{{ route('admin.vehicles.index') }}">Vehicles</a> <a class="btn btn-primary" href="{{ route('admin.device-models.create') }}">+ Add model</a></div></div>
<form class="d-flex gap-2 mb-4"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search device models" aria-label="Search device models"><button class="btn btn-dark">Search</button></form>
<div class="row g-3">@forelse($models as $model)
<div class="col-md-6 col-xl-4"><article class="card h-100 border-0 shadow-sm rounded-4"><div class="card-body p-4">
<div class="d-flex justify-content-between"><i class="fa-solid fa-microchip fs-2 text-primary"></i><span class="badge bg-light text-dark">{{ $model->active ? 'Active' : 'Inactive' }}</span></div>
<h4 class="mt-4 fw-bold">{{ $model->name }}</h4><p class="text-muted">{{ $model->manufacturer ?: 'Manufacturer not set' }}</p>
<div class="d-flex gap-2 mb-4"><span class="badge bg-light text-dark">{{ strtoupper($model->protocol) }}</span><span class="badge bg-light text-dark">{{ $model->protocol_version ?: 'No version' }}</span><span class="badge bg-light text-dark">{{ $model->network_generation ?: 'Network not set' }}</span></div>
<div class="d-flex gap-2"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.device-models.edit', $model) }}">Edit</a><form method="post" action="{{ route('admin.device-models.destroy', $model) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Delete unused model</button></form></div>
</div></article></div>
@empty<div class="col-12"><div class="card card-body p-5 text-center"><i class="fa-solid fa-microchip fs-1 text-primary mb-3"></i><h4>Your hardware catalog starts here</h4><p class="text-muted">Add a model, then select it while registering a vehicle.</p><a href="{{ route('admin.device-models.create') }}">Create first model →</a></div></div>@endforelse</div>
<div class="mt-4">{{ $models->links() }}</div>
@endsection
