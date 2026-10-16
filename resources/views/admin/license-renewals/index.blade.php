@extends('layouts.app')

@section('page-eyebrow', 'Licensing')
@section('page-title', 'License Renewals')

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead><tr><th>User</th><th>License</th><th>Plan</th><th>Amount</th><th>Gateway</th><th>Order</th><th>Payment</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($renewals as $renewal)
                        <tr>
                            <td>{{ $renewal->user?->name }}</td>
                            <td>{{ $renewal->license?->license_number }}</td>
                            <td>{{ $renewal->plan?->name }}</td>
                            <td><i class="fa-solid fa-indian-rupee-sign me-1" aria-hidden="true"></i>{{ number_format($renewal->amount, 2) }}</td>
                            <td>{{ ucfirst($renewal->gateway) }} · {{ ucfirst($renewal->environment) }}</td>
                            <td>{{ $renewal->provider_order_id }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $renewal->status])</td>
                            <td>{{ $renewal->created_at?->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="tracker-empty-state"><i class="fa-solid fa-rotate"></i><p class="mb-0">No renewal transactions yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($renewals->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $renewals->links() }}</div>
        @endif
    </div>
@endsection