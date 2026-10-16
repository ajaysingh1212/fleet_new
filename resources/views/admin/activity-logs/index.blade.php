@extends('layouts.app')

@section('page-eyebrow', 'Monitoring')
@section('page-title', 'Activity Logs')

@section('content')
    <div class="tracker-filter-bar mb-4">
        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="tracker-form-label">Event</label>
                <input type="text" name="event" class="form-control" value="{{ $filters['event'] ?? '' }}" placeholder="e.g. auth.login">
            </div>
            <div class="col-md-3">
                <label class="tracker-form-label">From</label>
                <input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="tracker-form-label">To</label>
                <input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn tracker-primary-btn flex-fill">Filter</button>
                <a href="{{ route('admin.activity-logs.index') }}" class="btn tracker-outline-btn"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>Event</th><th>User</th><th>IP</th><th>Device</th><th>Time</th></tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td><span class="tracker-badge">{{ $log->event }}</span></td>
                            <td>{{ $log->user?->name ?? 'System' }}</td>
                            <td>{{ $log->ip_address }}</td>
                            <td class="text-truncate" style="max-width: 220px;">{{ $log->device }}</td>
                            <td>{{ $log->logged_at?->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="tracker-empty-state"><i class="fa-solid fa-clock-rotate-left"></i><p class="mb-0">No activity recorded yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
