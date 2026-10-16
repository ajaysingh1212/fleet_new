@extends('layouts.app')

@section('page-eyebrow', 'Workspace')
@section('page-title', 'My Tracking')

@section('page-actions')
    <a href="{{ route('my-tracking.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Add Person</a>
@endsection

@section('content')
    @if ($incomingRequests->isNotEmpty())
        <div class="card tracker-surface-card mb-4">
            <div class="card-header border-0 bg-transparent">
                <h3 class="tracker-card-title mb-1">Incoming Tracking Requests</h3>
                <p class="tracker-card-subtitle mb-0">Approve a request only when you want this user to see your live location, reports, and private chat.</p>
            </div>
            <div class="card-body table-responsive">
                <table class="table tracker-table align-middle">
                    <thead><tr><th>Requested By</th><th>Relationship</th><th>License</th><th>Actions</th></tr></thead>
                    <tbody>
                        @foreach ($incomingRequests as $request)
                            <tr>
                                <td>{{ $request->trackerUser?->name }}<div class="text-muted small">{{ $request->trackerUser?->email }}</div></td>
                                <td>{{ $request->relationship_name }}</td>
                                <td>{{ $request->userLicense?->license_number }}</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="{{ route('my-tracking.requests.accept', $request) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm tracker-primary-btn">Accept</button>
                                        </form>
                                        <form method="POST" action="{{ route('my-tracking.requests.reject', $request) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($sentRequests->isNotEmpty())
        <div class="card tracker-surface-card mb-4">
            <div class="card-header border-0 bg-transparent">
                <h3 class="tracker-card-title mb-1">Sent Requests</h3>
                <p class="tracker-card-subtitle mb-0">These users will appear on your live map after they accept.</p>
            </div>
            <div class="card-body table-responsive">
                <table class="table tracker-table align-middle">
                    <thead><tr><th>Person</th><th>Relationship</th><th>License</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        @foreach ($sentRequests as $request)
                            <tr>
                                <td>{{ $request->trackedUser?->name }}<div class="text-muted small">{{ $request->trackedUser?->email }}</div></td>
                                <td>{{ $request->relationship_name }}</td>
                                <td>{{ $request->userLicense?->license_number }}</td>
                                <td>@include('admin.partials.status-pill', ['status' => $request->status])</td>
                                <td>
                                    <form method="POST" action="{{ route('my-tracking.destroy', $request) }}" data-confirm-delete data-confirm-title="Cancel this tracking request?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead><tr><th>Person</th><th>Relationship</th><th>License</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($relations as $relation)
                        <tr>
                            <td>{{ $relation->trackedUser?->name }}</td>
                            <td>{{ $relation->relationship_name }}</td>
                            <td>{{ $relation->userLicense?->license_number }}</td>
                            <td>{{ $relation->userLicense?->expiry_date?->format('d M Y') ?? 'Lifetime' }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $relation->status])</td>
                            <td>
                                <form method="POST" action="{{ route('my-tracking.destroy', $relation) }}" data-confirm-delete data-confirm-title="Remove this person?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove relation"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="tracker-empty-state"><i class="fa-solid fa-users"></i><p class="mb-0">No tracked people yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($relations->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $relations->links() }}</div>
        @endif
    </div>
@endsection
