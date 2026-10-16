@extends('layouts.app')

@section('page-eyebrow', 'Administration')
@section('page-title', 'Search Results')

@section('content')
    <div class="card tracker-surface-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.search') }}" class="d-flex gap-2">
                <input type="search" name="q" class="form-control" value="{{ $term }}" placeholder="Search users, licenses, settings, activity logs...">
                <button type="submit" class="btn tracker-primary-btn">Search</button>
            </form>
        </div>
    </div>

    @if ($term === '')
        <div class="tracker-empty-state"><i class="fa-solid fa-magnifying-glass"></i><p class="mb-0">Type a search term above to get started.</p></div>
    @elseif (collect($results)->every(fn ($set) => $set->isEmpty()))
        <div class="tracker-empty-state"><i class="fa-solid fa-magnifying-glass"></i><p class="mb-0">No results found for "{{ $term }}".</p></div>
    @else
        @foreach ($results as $section => $items)
            @continue($items->isEmpty())
            <div class="card tracker-surface-card mb-4">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-0">{{ $section }}</h3>
                </div>
                <div class="card-body">
                    <div class="tracker-mini-list">
                        @foreach ($items as $item)
                            <div class="tracker-mini-item">
                                <span>
                                    @if ($section === 'Users')
                                        {{ $item->name }} <span class="text-muted small">({{ $item->email }})</span>
                                    @elseif ($section === 'License Plans')
                                        {{ $item->name }}
                                    @elseif ($section === 'User Licenses')
                                        {{ $item->license_number }} <span class="text-muted small">({{ $item->user?->name }})</span>
                                    @elseif ($section === 'Settings')
                                        {{ $item->group }}.{{ $item->key }}
                                    @elseif ($section === 'Activity Logs')
                                        {{ $item->event }} <span class="text-muted small">({{ $item->logged_at?->diffForHumans() }})</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
