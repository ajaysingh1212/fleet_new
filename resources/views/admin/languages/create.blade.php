@extends('layouts.app')

@section('page-eyebrow', 'System')
@section('page-title', 'Add Language')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.languages.index') }}">Languages</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.languages.store') }}">
                @csrf
                @include('admin.languages._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Create Language</button>
                    <a href="{{ route('admin.languages.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
