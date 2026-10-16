@extends('layouts.app')

@section('page-eyebrow', 'System')
@section('page-title', 'Edit City')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.cities.index') }}">Cities</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.cities.update', $city) }}">
                @csrf
                @method('PUT')
                @include('admin.cities._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Update City</button>
                    <a href="{{ route('admin.cities.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
