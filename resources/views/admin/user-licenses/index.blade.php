@extends('layouts.app')

@section('page-eyebrow', 'Licensing')
@section('page-title', 'User Licenses')

@section('page-actions')
    @can('create', App\Models\UserLicense::class)
        <a href="{{ route('admin.user-licenses.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Assign License</a>
    @endcan
@endsection

@section('content')
    <div class="tracker-filter-bar mb-4">
        <form method="GET" action="{{ route('admin.user-licenses.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="tracker-form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn tracker-primary-btn flex-fill">Filter</button>
                <a href="{{ route('admin.user-licenses.index') }}" class="btn tracker-outline-btn"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr>
                        <th>License #</th>
                        <th>User</th>
                        <th>Plan</th>
                        <th>Assigned Tracked User</th>
                        <th>Expiry</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($licenses as $license)
                        <tr>
                            <td>{{ $license->license_number }}</td>
                            <td>{{ $license->user?->name }}</td>
                            <td>{{ $license->plan?->name }}</td>
                            <td>{{ $license->assignedTrackedUser?->name ?? 'Available' }}</td>
                            <td>{{ $license->expiry_date?->format('d M Y') ?? 'Lifetime' }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $license->status])</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('admin.user-licenses.show', $license) }}" class="btn btn-sm tracker-outline-btn" title="View"><i class="fa-solid fa-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="tracker-empty-state"><i class="fa-solid fa-file-invoice"></i><p class="mb-0">No licenses assigned yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($licenses->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $licenses->links() }}</div>
        @endif
    </div>
@endsection
