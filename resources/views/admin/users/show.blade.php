@extends('layouts.app')

@section('page-eyebrow', 'Administration')
@section('page-title', $user->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Users</a></li>
@endsection

@section('page-actions')
    @can('update', $user)
        <a href="{{ route('admin.users.edit', $user) }}" class="btn tracker-primary-btn"><i class="fa-solid fa-pen me-2"></i>Edit</a>
    @endcan
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card tracker-surface-card mb-4">
                <div class="card-body text-center">
                    <div class="tracker-profile-avatar-lg mx-auto mb-3">
                        @if ($user->avatar)
                            <img src="{{ asset('storage/'.$user->avatar) }}" alt="{{ $user->name }}">
                        @else
                            <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <h3 class="tracker-card-title mb-1">{{ $user->name }}</h3>
                    <p class="tracker-card-subtitle mb-2">{{ $user->email }}</p>
                    @include('admin.partials.status-pill', ['status' => $user->status])
                </div>
            </div>

            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-0">Account Details</h3>
                </div>
                <div class="card-body">
                    <div class="tracker-mini-list">
                        <div class="tracker-mini-item"><span>Total Licenses</span><strong>{{ $licenseCount }}</strong></div>
                        <div class="tracker-mini-item"><span>Unused Licenses</span><strong>{{ $availableLicenseCount }}</strong></div>
                        <div class="tracker-mini-item"><span>Employee ID</span><strong>{{ $user->employee_id }}</strong></div>
                        <div class="tracker-mini-item"><span>Roles</span><strong>{{ $user->getRoleNames()->implode(', ') ?: '—' }}</strong></div>
                        <div class="tracker-mini-item"><span>Department</span><strong>{{ $user->department ?? '—' }}</strong></div>
                        <div class="tracker-mini-item"><span>Designation</span><strong>{{ $user->designation ?? '—' }}</strong></div>
                        <div class="tracker-mini-item"><span>Company</span><strong>{{ $user->company ?? '—' }}</strong></div>
                        <div class="tracker-mini-item"><span>Phone</span><strong>{{ $user->phone ?? '—' }}</strong></div>
                        <div class="tracker-mini-item"><span>Location</span><strong>{{ collect([$user->city?->name, $user->state?->name, $user->country?->name])->filter()->implode(', ') ?: '—' }}</strong></div>
                        <div class="tracker-mini-item"><span>Last Login</span><strong>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</strong></div>
                        <div class="tracker-mini-item"><span>Last Login IP</span><strong>{{ $user->last_login_ip ?? '—' }}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card tracker-surface-card mb-4">
                <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
                    <h3 class="tracker-card-title mb-0">Recent Licenses</h3>
                    @if (Route::has('admin.user-licenses.index'))
                        <a href="{{ route('admin.user-licenses.index', ['user' => $user->id]) }}" class="small">View all</a>
                    @endif
                </div>
                <div class="card-body table-responsive">
                    <table class="table tracker-table align-middle">
                        <thead>
                            <tr><th>License #</th><th>Plan</th><th>Status</th><th>Expiry</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($licenses as $license)
                                <tr>
                                    <td>{{ $license->license_number }}</td>
                                    <td>{{ $license->plan?->name }}</td>
                                    <td>@include('admin.partials.status-pill', ['status' => $license->status])</td>
                                    <td>{{ $license->expiry_date?->format('d M Y') ?? 'Lifetime' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No licenses assigned yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card tracker-surface-card mb-4">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-0">Recent Devices</h3>
                </div>
                <div class="card-body table-responsive">
                    <table class="table tracker-table align-middle">
                        <thead>
                            <tr><th>Device</th><th>IP</th><th>Last Activity</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($devices as $device)
                                <tr>
                                    <td>{{ $device->device_name ?? 'Unknown device' }}</td>
                                    <td>{{ $device->ip_address }}</td>
                                    <td>{{ $device->last_activity_at?->diffForHumans() }}</td>
                                    <td>{{ $device->is_current && ! $device->logged_out_at ? 'Active' : 'Signed out' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No device sessions recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-0">Recent Activity</h3>
                </div>
                <div class="card-body table-responsive">
                    <table class="table tracker-table align-middle">
                        <thead>
                            <tr><th>Event</th><th>IP</th><th>Time</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($activities as $activity)
                                <tr>
                                    <td><span class="tracker-badge">{{ $activity->event }}</span></td>
                                    <td>{{ $activity->ip_address }}</td>
                                    <td>{{ $activity->logged_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">No activity recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
