@extends('layouts.app')
@section('page-title', $vehicle->exists ? 'Edit vehicle' : 'Register vehicle')
@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $vehicle->exists ? route('admin.vehicles.update',$vehicle) : route('admin.vehicles.store') }}" class="card card-body">
@csrf @if($vehicle->exists) @method('PUT') @endif
@include('admin.vehicles.fields', ['prefix'=>''])
<label class="form-label mt-3" for="documents">Private documents (PDF/JPEG/PNG, max 5 × 5 MB per submission)</label><input class="form-control" id="documents" type="file" name="documents[]" multiple>
@foreach($vehicle->details['documents'] ?? [] as $index=>$document)<a class="mt-2" href="{{ route('admin.vehicles.document',[$vehicle,$index]) }}">{{ $document['name'] }}</a>@endforeach
<div class="mt-3"><button class="btn btn-primary">Save vehicle</button></div>
</form>
@endsection
