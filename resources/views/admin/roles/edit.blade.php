@extends('layouts.app')

@section('page-eyebrow', 'Administration')
@section('page-title', 'Edit Role')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">Roles</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                @csrf
                @method('PUT')
                @include('admin.roles._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Update Role</button>
                    <a href="{{ route('admin.roles.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
