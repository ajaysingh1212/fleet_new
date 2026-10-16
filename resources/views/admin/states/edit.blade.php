@extends('layouts.app')

@section('page-eyebrow', 'System')
@section('page-title', 'Edit State')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.states.index') }}">States</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.states.update', $state) }}">
                @csrf
                @method('PUT')
                @include('admin.states._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Update State</button>
                    <a href="{{ route('admin.states.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
