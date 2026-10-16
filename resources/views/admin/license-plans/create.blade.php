@extends('layouts.app')

@section('page-eyebrow', 'Licensing')
@section('page-title', 'Add License Plan')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.license-plans.index') }}">License Plans</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.license-plans.store') }}">
                @csrf
                @include('admin.license-plans._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Create Plan</button>
                    <a href="{{ route('admin.license-plans.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
