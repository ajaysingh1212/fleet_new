@extends('layouts.app')

@section('page-eyebrow', 'Workspace')
@section('page-title', 'My Licenses')

@section('page-actions')
    <a href="{{ route('my-licenses.plans') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Buy License</a>
@endsection

@section('content')
    @if ($activeLicense)
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="tracker-stat-card">
                    <div class="tracker-stat-icon bg-primary-subtle text-primary"><i class="fa-solid fa-id-badge"></i></div>
                    <div class="tracker-stat-value">{{ $activeLicense->plan?->name }}</div>
                    <div class="tracker-stat-label">Current Plan</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="tracker-stat-card">
                    <div class="tracker-stat-icon bg-success-subtle text-success"><i class="fa-solid fa-id-card"></i></div>
                    <div class="tracker-stat-value">{{ $availableLicenses }}</div>
                    <div class="tracker-stat-label">Available Licenses</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="tracker-stat-card">
                    <div class="tracker-stat-icon bg-warning-subtle text-warning"><i class="fa-solid fa-users"></i></div>
                    <div class="tracker-stat-value">{{ $trackedUsers }}</div>
                    <div class="tracker-stat-label">Tracked Users</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="tracker-stat-card">
                    <div class="tracker-stat-icon bg-info-subtle text-info"><i class="fa-solid fa-calendar"></i></div>
                    <div class="tracker-stat-value">{{ $activeLicense->expiry_date?->format('d M Y') ?? 'Lifetime' }}</div>
                    <div class="tracker-stat-label">Expiry Date</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card tracker-surface-card">
        <div class="card-header border-0 bg-transparent">
            <h3 class="tracker-card-title mb-1">License History</h3>
            <p class="tracker-card-subtitle mb-0">All plans assigned to your account</p>
        </div>
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>License #</th><th>Plan</th><th>Assigned Person</th><th>Purchased</th><th>Expiry</th><th>Payment</th><th>Status</th><th>Renewal Price</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($licenses as $license)
                        <tr>
                            <td>{{ $license->license_number }}</td>
                            <td>{{ $license->plan?->name }}</td>
                            <td>{{ $license->usage_type === 'self' ? 'You (personal use)' : ($license->assignedTrackedUser?->name ?? 'Available') }}</td>
                            <td>{{ $license->purchase_date?->format('d M Y') }}</td>
                            <td>{{ $license->expiry_date?->format('d M Y') ?? 'Starts on first use' }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $license->payment_status])</td>
                            <td>@include('admin.partials.status-pill', ['status' => $license->status === \App\Enums\LicenseStatus::Active && $license->isExpired() ? \App\Enums\LicenseStatus::Expired : $license->status])</td>
                            <td>
                                @if ($license->plan && $license->plan->type->value !== 'lifetime' && ! $license->plan->is_free)
                                    ₹{{ number_format($license->plan->renewal_price, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($license->is_free_claim)
                                    <span class="badge text-bg-info">Free demo · {{ $license->plan?->duration_in_days }} days</span>
                                @endif
                                @if ($license->status->value === 'pending' && $license->payment_status->value === 'paid' && ! $license->assigned_tracked_user_id)
                                    <form method="POST" action="{{ route('my-licenses.use-for-self', $license) }}" class="mb-2">
                                        @csrf
                                        <button type="submit" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-user-check me-1"></i>Use for me</button>
                                    </form>
                                @endif
                                @if ($license->isExpired() && $license->plan?->is_free)
                                    <a href="{{ route('my-licenses.plans') }}" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-rotate"></i> Renew with paid plan</a>
                                @endif
                                @if ($license->assigned_tracked_user_id && $license->plan && $license->plan->type->value !== 'lifetime' && ! $license->plan->is_free && $license->status->value !== 'cancelled')
                                    <form method="POST" action="{{ route('my-licenses.renew', $license) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm tracker-outline-btn" title="Renew for ₹{{ number_format($license->plan->renewal_price, 2) }}"><i class="fa-solid fa-rotate"></i> Renew</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="tracker-empty-state"><i class="fa-solid fa-id-card"></i><p class="mb-0">No licenses assigned to your account yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($licenses->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $licenses->links() }}</div>
        @endif
    </div>

    <div class="card tracker-surface-card mt-4">
        <div class="card-header border-0 bg-transparent"><h3 class="tracker-card-title mb-0">Recent Payments</h3></div>
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead><tr><th>Type</th><th>Plan</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ ucfirst($transaction->type) }}</td>
                            <td>{{ $transaction->plan?->name }}</td>
                            <td>₹{{ number_format($transaction->amount, 2) }}</td>
                            <td>{{ ucfirst($transaction->gateway) }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $transaction->status])</td>
                            <td>{{ $transaction->created_at?->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">No payment transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
