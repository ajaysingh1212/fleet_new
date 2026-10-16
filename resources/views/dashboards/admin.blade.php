@extends('layouts.app')

@section('page-eyebrow', 'Administration')
@section('page-title', 'Admin Dashboard')

@section('content')
    <div class="tracker-hero-card mb-4">
        <div class="row align-items-center g-4">
            <div class="col-xl-7">
                <div class="tracker-hero-kicker">Control Center</div>
                <h2 class="tracker-hero-title">AdminLTE style dashboard, now with an actual menu</h2>
                <p class="tracker-hero-text mb-0">Users, licenses, admin activity, and operational signals are now grouped into a more usable admin shell for {{ now()->format('F j, Y') }}.</p>
            </div>
            <div class="col-xl-5">
                <div class="tracker-hero-grid">
                    <div class="tracker-hero-metric">
                        <span>Signed in as</span>
                        <strong>{{ auth()->user()->name }}</strong>
                    </div>
                    <div class="tracker-hero-metric">
                        <span>Active licenses</span>
                        <strong>{{ $stats['activeLicenses'] }}</strong>
                    </div>
                    <div class="tracker-hero-metric">
                        <span>Today logins</span>
                        <strong>{{ $stats['todaysLogins'] }}</strong>
                    </div>
                    <div class="tracker-hero-metric">
                        <span>New users today</span>
                        <strong>{{ $stats['todaysRegistrations'] }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card tracker-filter-card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                <div>
                    <div class="tracker-section-label mb-2">Quick Filters</div>
                    <div class="tracker-filter-pills">
                        <button class="btn tracker-pill-btn">Today</button>
                        <button class="btn tracker-pill-btn">Yesterday</button>
                        <button class="btn tracker-pill-btn active">Week</button>
                        <button class="btn tracker-pill-btn">Month</button>
                        <button class="btn tracker-pill-btn">3 Month</button>
                        <button class="btn tracker-pill-btn">6 Month</button>
                        <button class="btn tracker-pill-btn">1 Year</button>
                        <button class="btn tracker-pill-btn">All</button>
                    </div>
                </div>
                <div class="tracker-filter-summary">
                    <span>Monitoring</span>
                    <strong>{{ $stats['totalUsers'] }} users across {{ $stats['licenses'] }} licenses</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-primary-subtle text-primary"><i class="fa-solid fa-users"></i></div>
                <div class="tracker-stat-value">{{ $stats['totalUsers'] }}</div>
                <div class="tracker-stat-label">Total Users</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-success-subtle text-success"><i class="fa-solid fa-user-check"></i></div>
                <div class="tracker-stat-value">{{ $stats['activeUsers'] }}</div>
                <div class="tracker-stat-label">Active Users</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-warning-subtle text-warning"><i class="fa-solid fa-id-card"></i></div>
                <div class="tracker-stat-value">{{ $stats['licenses'] }}</div>
                <div class="tracker-stat-label">Total Licenses</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-danger-subtle text-danger"><i class="fa-solid fa-clock"></i></div>
                <div class="tracker-stat-value">{{ $stats['expiredLicenses'] }}</div>
                <div class="tracker-stat-label">Expired Licenses</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-info-subtle text-info"><i class="fa-solid fa-user-tie"></i></div>
                <div class="tracker-stat-value">{{ $stats['managers'] }}</div>
                <div class="tracker-stat-label">Managers</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-secondary-subtle text-secondary"><i class="fa-solid fa-user-shield"></i></div>
                <div class="tracker-stat-value">{{ $stats['admins'] }}</div>
                <div class="tracker-stat-label">Admins</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-success-subtle text-success"><i class="fa-solid fa-arrow-right-to-bracket"></i></div>
                <div class="tracker-stat-value">{{ $stats['todaysLogins'] }}</div>
                <div class="tracker-stat-label">Today's Logins</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-primary-subtle text-primary"><i class="fa-solid fa-user-plus"></i></div>
                <div class="tracker-stat-value">{{ $stats['todaysRegistrations'] }}</div>
                <div class="tracker-stat-label">Today's Registrations</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card tracker-surface-card h-100">
                <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="tracker-card-title mb-1">Latest Activities</h3>
                        <p class="tracker-card-subtitle mb-0">Recent system and login timeline</p>
                    </div>
                    <span class="tracker-panel-chip">Live Feed</span>
                </div>
                <div class="card-body table-responsive">
                    <table class="table tracker-table align-middle" data-datatable>
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>User</th>
                                <th>IP</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stats['latestActivities'] as $activity)
                                <tr>
                                    <td><span class="tracker-badge">{{ $activity->event }}</span></td>
                                    <td>{{ $activity->user?->name ?? 'System' }}</td>
                                    <td>{{ $activity->ip_address }}</td>
                                    <td>{{ $activity->logged_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card tracker-surface-card mb-4">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-1">Platform Balance</h3>
                    <p class="tracker-card-subtitle mb-0">Quick visual read of account health</p>
                </div>
                <div class="card-body">
                    <div class="tracker-chart-placeholder">
                        <div class="tracker-chart-wave"></div>
                        <div class="tracker-chart-stats">
                            <div><span>Admins</span><strong>{{ $stats['admins'] }}</strong></div>
                            <div><span>Managers</span><strong>{{ $stats['managers'] }}</strong></div>
                            <div><span>Expired</span><strong>{{ $stats['expiredLicenses'] }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-1">Operational Snapshot</h3>
                    <p class="tracker-card-subtitle mb-0">Current platform health</p>
                </div>
                <div class="card-body">
                    <div class="tracker-mini-list">
                        <div class="tracker-mini-item"><span>Active Licenses</span><strong>{{ $stats['activeLicenses'] }}</strong></div>
                        <div class="tracker-mini-item"><span>Expired Licenses</span><strong>{{ $stats['expiredLicenses'] }}</strong></div>
                        <div class="tracker-mini-item"><span>Total Admin Users</span><strong>{{ $stats['admins'] }}</strong></div>
                        <div class="tracker-mini-item"><span>Tracked Revenue Units</span><strong>{{ $stats['revenue'] }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
