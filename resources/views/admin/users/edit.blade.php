@extends('layouts.app')

@section('page-eyebrow', 'Administration')
@section('page-title', 'Edit User')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Users</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')
                @include('admin.users._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Update User</button>
                    <a href="{{ route('admin.users.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
