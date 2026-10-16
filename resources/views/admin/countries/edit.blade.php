@extends('layouts.app')

@section('page-eyebrow', 'System')
@section('page-title', 'Edit Country')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.countries.index') }}">Countries</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.countries.update', $country) }}">
                @csrf
                @method('PUT')
                @include('admin.countries._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Update Country</button>
                    <a href="{{ route('admin.countries.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
