@extends('layouts.app')

@section('page-eyebrow', 'Licensing')
@section('page-title', 'License #' . $license->license_number)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.user-licenses.index') }}">User Licenses</a></li>
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-0">Overview</h3>
                </div>
                <div class="card-body">
                    <div class="tracker-mini-list">
                        <div class="tracker-mini-item"><span>User</span><strong>{{ $license->user?->name }}</strong></div>
                        <div class="tracker-mini-item"><span>Plan</span><strong>{{ $license->plan?->name }}</strong></div>
                        <div class="tracker-mini-item"><span>Status</span>@include('admin.partials.status-pill', ['status' => $license->status])</div>
                        <div class="tracker-mini-item"><span>Payment</span>@include('admin.partials.status-pill', ['status' => $license->payment_status])</div>
                        <div class="tracker-mini-item"><span>Assigned Tracked User</span><strong>{{ $license->assignedTrackedUser?->name ?? 'Not assigned' }}</strong></div>
                        <div class="tracker-mini-item"><span>Purchase Date</span><strong>{{ $license->purchase_date?->format('d M Y') }}</strong></div>
                        <div class="tracker-mini-item"><span>Expiry Date</span><strong>{{ $license->expiry_date?->format('d M Y') ?? 'Lifetime' }}</strong></div>
                        <div class="tracker-mini-item"><span>Invoice #</span><strong>{{ $license->invoice_number }}</strong></div>
                        <div class="tracker-mini-item"><span>Order #</span><strong>{{ $license->order_number }}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            @can('update', $license)
                <div class="card tracker-surface-card mb-4">
                    <div class="card-header border-0 bg-transparent">
                        <h3 class="tracker-card-title mb-0">Actions</h3>
                    </div>
                    <div class="card-body d-flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('admin.user-licenses.extend', $license) }}" class="d-flex gap-2">
                            @csrf
                            <input type="number" name="days" min="1" class="form-control" placeholder="Days" style="width: 120px;" required>
                            <button type="submit" class="btn tracker-outline-btn">Extend</button>
                        </form>

                        @if ($license->status->value !== 'cancelled')
                            <form method="POST" action="{{ route('admin.user-licenses.cancel', $license) }}" data-confirm-delete data-confirm-title="Cancel this license?">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">Cancel License</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
