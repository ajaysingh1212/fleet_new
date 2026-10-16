@extends('layouts.app')
@section('page-title', 'Vehicles')
@section('content')
<div class="card card-body"><div><a class="btn btn-primary" href="{{ route('admin.vehicles.create') }}">Register vehicle</a> <a class="btn btn-secondary" href="{{ route('admin.gps-devices.index') }}">GPS devices</a></div>
<table class="table mt-3"><thead><tr><th>Registration</th><th>Name</th><th>Status</th><th>Actions</th></tr></thead><tbody>@foreach($vehicles as $vehicle)<tr><td>{{ $vehicle->registration_number }}</td><td>{{ $vehicle->name }}</td><td>{{ $vehicle->status }}</td><td><a href="{{ route('admin.vehicles.edit',$vehicle) }}">Edit</a></td></tr>@endforeach</tbody></table>{{ $vehicles->links() }}</div>
@endsection
