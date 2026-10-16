@php
    $__currentUser = auth()->user();
@endphp

<aside class="app-sidebar tracker-sidebar elevation-0">
    <a href="{{ route('dashboard') }}" class="brand-link tracker-brand-link">
        <div class="tracker-sidebar-logo">
            @if ($__siteLogo)
                <img src="{{ asset('storage/'.$__siteLogo) }}" alt="{{ $__siteName }}">
            @else
                TE
            @endif
        </div>
        <div class="tracker-brand-copy">
            <div class="tracker-sidebar-title">{{ $__siteName }}</div>
            <div class="tracker-sidebar-caption">{{ $__siteSettings['tagline'] ?? 'Operational Dashboard' }}</div>
        </div>
    </a>

    <button type="button" class="tracker-sidebar-pin" data-sidebar-compact-toggle title="Toggle compact sidebar">
        <i class="fa-solid fa-angles-left"></i>
    </button>

    <div class="sidebar-wrapper">
        <div class="sidebar">
            {{-- <div class="tracker-profile-card">
                <div class="tracker-profile-avatar">
                    @if ($__currentUser->avatar)
                        <img src="{{ asset('storage/'.$__currentUser->avatar) }}" alt="{{ $__currentUser->name }}" class="w-100 h-100 rounded" style="object-fit: cover; border-radius: 16px;">
                    @else
                        {{ strtoupper(substr($__currentUser->name, 0, 1)) }}
                    @endif
                </div>
                <div>
                    <div class="tracker-profile-name">{{ $__currentUser->name }}</div>
                    <div class="tracker-profile-role">{{ $__currentUser->getRoleNames()->first() ?? 'User' }}</div>
                </div>
            </div> --}}

            {{-- <div class="tracker-sidebar-panel">
                <div class="tracker-sidebar-panel-label">Workspace</div>
                <div class="tracker-sidebar-panel-value">{{ $__currentUser->hasAnyRole(['Super Admin', 'Admin', 'Manager']) ? 'AdminLTE Style Console' : 'User Dashboard' }}</div>
            </div> --}}

            {{-- <div class="tracker-sidebar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" class="form-control border-0 shadow-none" data-sidebar-search placeholder="Search menu">
            </div> --}}

            <nav class="mt-4">
                @include('layouts.partials.sidebar-menu')
            </nav>
        </div>
    </div>
</aside>
