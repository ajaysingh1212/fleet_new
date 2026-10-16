@php
    $__currentUser = auth()->user();
    $__isPrivileged = $__currentUser->hasAnyRole(['Super Admin', 'Admin', 'Manager']);
@endphp

<nav class="app-header navbar navbar-expand tracker-topbar border-0">
    <div class="container-fluid px-4">
        <ul class="navbar-nav align-items-center">
            <li class="nav-item">
                <a class="nav-link tracker-menu-toggle" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <li class="nav-item d-none d-sm-flex align-items-center">
                <div class="tracker-brand-mark">
                    @if ($__siteLogo)
                        <img src="{{ asset('storage/'.$__siteLogo) }}" alt="{{ $__siteName }}">
                    @else
                        TE
                    @endif
                </div>
                <div class="ms-3">
                    <div class="tracker-brand-title">{{ $__siteName }}</div>
                    <div class="tracker-brand-subtitle">{{ $__isPrivileged ? 'Admin Control Panel' : 'Employee Workspace' }}</div>
                </div>
            </li>
        </ul>

        @if ($__isPrivileged && Route::has('admin.search'))
            <form class="d-none d-lg-flex flex-grow-1 mx-4" style="max-width: 420px;" method="GET" action="{{ route('admin.search') }}">
                <div class="tracker-topbar-search w-100">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" name="q" class="form-control border-0 shadow-none" placeholder="Search users, licenses, settings..." value="{{ request('q') }}">
                </div>
            </form>
        @endif

        <ul class="navbar-nav ms-auto align-items-center gap-2">
            <li class="nav-item d-none d-xl-block">
                <div class="tracker-topbar-note">
                    <span class="tracker-topbar-note-label">Live Status</span>
                    <strong>{{ $__isPrivileged ? 'Admin Panel Ready' : 'Workspace Active' }}</strong>
                </div>
            </li>
            <li class="nav-item d-none d-md-block">
                <button class="btn tracker-ghost-btn" type="button" data-bs-toggle="modal" data-bs-target="#screenLockModal">
                    <i class="fa-solid fa-lock me-2"></i>Screen Lock
                </button>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link tracker-icon-btn" data-bs-toggle="dropdown" href="#">
                    <i class="fa-regular fa-bell"></i>
                    @php $__unreadCount = $__currentUser->unreadNotifications()->count(); @endphp
                    <span id="notification-bell-badge" class="badge text-bg-primary navbar-badge {{ $__unreadCount > 0 ? '' : 'd-none' }}">{{ $__unreadCount }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end tracker-dropdown">
                    <div class="dropdown-header fw-semibold d-flex align-items-center justify-content-between">
                        Notifications
                        @if (Route::has('notifications.index'))
                            <a href="{{ route('notifications.index') }}" class="small">View all</a>
                        @endif
                    </div>
                    <div id="notification-dropdown-list">
                        @forelse ($__currentUser->notifications()->latest()->take(5)->get() as $notification)
                            @include('layouts.partials.notification-item')
                        @empty
                            <div class="dropdown-item small text-muted" data-notification-empty>No notifications available.</div>
                        @endforelse
                    </div>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a href="#" class="nav-link tracker-user-toggle" data-bs-toggle="dropdown">
                    <div class="tracker-user-avatar">
                        @if ($__currentUser->avatar)
                            <img src="{{ asset('storage/'.$__currentUser->avatar) }}" alt="{{ $__currentUser->name }}" class="w-100 h-100 rounded" style="object-fit: cover; border-radius: 16px;">
                        @else
                            {{ strtoupper(substr($__currentUser->name, 0, 1)) }}
                        @endif
                    </div>
                    <div class="d-none d-md-block">
                        <div class="tracker-user-name">{{ $__currentUser->name }}</div>
                        <div class="tracker-user-role">{{ $__currentUser->getRoleNames()->first() ?? 'User' }}</div>
                    </div>
                    <i class="fa-solid fa-chevron-down small text-muted"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end tracker-dropdown p-2">
                    <li class="px-2 py-2 border-bottom">
                        <div class="fw-semibold">{{ $__currentUser->name }}</div>
                        <div class="small text-muted">{{ $__currentUser->email }}</div>
                    </li>
                    <li><a class="dropdown-item rounded-3" href="{{ route('profile.edit') }}">Profile Settings</a></li>
                    @if (Route::has('user-settings.edit'))
                        <li><a class="dropdown-item rounded-3" href="{{ route('user-settings.edit') }}">My Settings</a></li>
                    @endif
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item rounded-3 text-danger">Logout</button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<div class="modal fade" id="screenLockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content tracker-modal">
            <div class="modal-header border-0">
                <h5 class="modal-title">Screen Locked</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Your session stays signed in. Log out below if this device is shared.</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn tracker-primary-btn w-100" type="submit">Logout instead</button>
                </form>
            </div>
        </div>
    </div>
</div>
