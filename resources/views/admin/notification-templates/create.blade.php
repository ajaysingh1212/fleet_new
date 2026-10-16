@extends('layouts.app')

@section('page-eyebrow', 'Communication')
@section('page-title', 'Add Notification Template')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.notification-templates.index') }}">Notification Templates</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.notification-templates.store') }}">
                @csrf
                @include('admin.notification-templates._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Create Template</button>
                    <a href="{{ route('admin.notification-templates.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
