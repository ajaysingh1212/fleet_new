@extends('layouts.app')

@section('page-eyebrow', 'Workspace')
@section('page-title', 'User Dashboard')

@section('content')
    <div class="tracker-hero-card tracker-hero-card-user mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="tracker-hero-kicker">Personal workspace</div>
                <h2 class="tracker-hero-title">Welcome back, {{ auth()->user()->name }}</h2>
                <p class="tracker-hero-text mb-0">Track your license availability, device footprint, notifications, and account activity from one workspace.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="d-flex flex-wrap justify-content-lg-end gap-2 mt-3 mt-lg-0">
                    <a href="{{ route('my-licenses.plans') }}" class="btn tracker-hero-action"><i class="fa-solid fa-cart-shopping me-2"></i>Buy License</a>
                    <a href="{{ route('license-transfers.index') }}" class="btn tracker-hero-action"><i class="fa-solid fa-right-left me-2"></i>Transfer</a>
                </div>
            </div>
        </div>
    </div>

    @if ($activeLicense?->is_free_claim)
        <div class="alert alert-info tracker-alert d-flex align-items-center gap-3">
            <i class="fa-solid fa-flask fs-4"></i>
            <div><strong>Free demo license</strong> · {{ $activeLicense->plan?->duration_in_days }} day(s), expires {{ $activeLicense->expiry_date?->format('d M Y H:i') ?? 'when activated' }}.</div>
        </div>
    @endif

    @foreach ($demoTrackingLicenses as $demoRelation)
        <div class="alert alert-info tracker-alert d-flex align-items-center gap-3">
            <i class="fa-solid fa-flask fs-4"></i>
            <div><strong>Free demo license</strong> · {{ $demoRelation->trackerUser?->name }} is tracking your account. Expires {{ $demoRelation->userLicense?->expiry_date?->format('d M Y H:i') ?? 'soon' }}.</div>
        </div>
    @endforeach

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-primary-subtle text-primary"><i class="fa-solid fa-id-badge"></i></div>
                <div class="tracker-stat-value">{{ $activeLicense?->plan?->name ?? 'N/A' }}</div>
                <div class="tracker-stat-label">Current License</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-success-subtle text-success"><i class="fa-solid fa-link"></i></div>
                <div class="tracker-stat-value">{{ $stats['availableLicenses'] }}</div>
                <div class="tracker-stat-label">Available Licenses</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-warning-subtle text-warning"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="tracker-stat-value">{{ $stats['trackedUsers'] }}</div>
                <div class="tracker-stat-label">Tracked Users</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="tracker-stat-card">
                <div class="tracker-stat-icon bg-danger-subtle text-danger"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="tracker-stat-value">{{ auth()->user()->status->label() }}</div>
                <div class="tracker-stat-label">Account Status</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card tracker-surface-card mb-4">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-1">Recent Notifications</h3>
                    <p class="tracker-card-subtitle mb-0">Latest alerts tied to your account</p>
                </div>
                <div class="card-body">
                    @forelse ($stats['notifications'] as $notification)
                        <div class="tracker-notice-item">
                            <div class="tracker-notice-icon"><i class="fa-regular fa-bell"></i></div>
                            <div>{{ $notification->data['message'] ?? 'Notification' }}</div>
                        </div>
                    @empty
                        <div class="text-muted">No recent notifications.</div>
                    @endforelse
                </div>
            </div>

            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-1">Login History</h3>
                    <p class="tracker-card-subtitle mb-0">Recent authentication attempts</p>
                </div>
                <div class="card-body table-responsive">
                    <table class="table tracker-table align-middle" data-datatable>
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>IP</th>
                                <th>Attempted At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stats['loginHistory'] as $entry)
                                <tr>
                                    <td>{{ $entry->email }}</td>
                                    <td>{{ $entry->ip_address }}</td>
                                    <td>{{ $entry->attempted_at?->diffForHumans() }}</td>
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
                    <h3 class="tracker-card-title mb-1">Quick Actions</h3>
                    <p class="tracker-card-subtitle mb-0">Fast account operations</p>
                </div>
                <div class="card-body d-grid gap-3">
                    <a href="{{ route('profile.edit') }}" class="btn tracker-primary-btn">Update Profile</a>
                    <button class="btn tracker-outline-btn" type="button" data-bs-toggle="modal" data-bs-target="#supportModal">Contact Support</button>
                </div>
            </div>

            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-1">Support Snapshot</h3>
                    <p class="tracker-card-subtitle mb-0">Workspace status overview</p>
                </div>
                <div class="card-body">
                    <div class="tracker-mini-list">
                        <div class="tracker-mini-item"><span>Open Tickets</span><strong>{{ $stats['supportTickets'] }}</strong></div>
                        <div class="tracker-mini-item"><span>Saved Devices</span><strong>{{ $stats['devices'] }}</strong></div>
                        <div class="tracker-mini-item"><span>License Status</span><strong>{{ $activeLicense?->status?->value ?? 'none' }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="supportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Support Center</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-muted">
                    Need help? Open a support ticket and our team will get back to you.
                    @if (Route::has('support.create'))
                        <a href="{{ route('support.create') }}" class="btn tracker-primary-btn w-100 mt-3">Open a Ticket</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
