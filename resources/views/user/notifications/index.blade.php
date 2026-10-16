@extends('layouts.app')

@section('page-eyebrow', 'Workspace')
@section('page-title', 'Notifications')

@section('page-actions')
    <button type="button" class="btn tracker-outline-btn" id="enable-desktop-notifications-btn">
        <i class="fa-regular fa-bell me-2"></i>Enable Desktop Notifications
    </button>
    <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button type="submit" class="btn tracker-outline-btn"><i class="fa-solid fa-check-double me-2"></i>Mark All Read</button>
    </form>
@endsection

@php
    $__tabs = [
        ['label' => 'All', 'category' => null, 'status' => null],
        ['label' => 'Unread', 'category' => null, 'status' => 'unread'],
        ['label' => 'Read', 'category' => null, 'status' => 'read'],
        ['label' => 'Mentions', 'category' => 'mention', 'status' => null],
        ['label' => 'Calls', 'category' => 'call', 'status' => null],
        ['label' => 'Tracking', 'category' => 'tracking', 'status' => null],
        ['label' => 'Diagnostics', 'category' => 'diagnostic', 'status' => null],
        ['label' => 'System', 'category' => 'system', 'status' => null],
    ];
@endphp

@section('content')
    <ul class="nav tracker-nav-tabs mb-3">
        @foreach ($__tabs as $tab)
            <li class="nav-item">
                <a
                    href="{{ route('notifications.index', array_filter(['category' => $tab['category'], 'status' => $tab['status']])) }}"
                    class="nav-link {{ request('category') === $tab['category'] && request('status') === $tab['status'] ? 'active' : '' }}"
                >{{ $tab['label'] }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card tracker-surface-card">
        <div class="card-body" id="notification-index-list">
            @forelse ($notifications as $notification)
                @include('layouts.partials.notification-item')
            @empty
                <div class="tracker-empty-state" data-notification-empty><i class="fa-regular fa-bell"></i><p class="mb-0">No notifications here.</p></div>
            @endforelse
        </div>
        @if ($notifications->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection
