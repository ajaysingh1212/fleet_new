@extends('layouts.app')

@section('page-eyebrow', 'Monitoring')
@section('page-title', 'Audit Logs')

@section('content')
    <div class="tracker-filter-bar mb-4">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="tracker-form-label">Event</label>
                <select name="event" class="form-select">
                    <option value="">All Events</option>
                    @foreach (['created', 'updated', 'deleted', 'restored'] as $event)
                        <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ ucfirst($event) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="tracker-form-label">Model</label>
                <input type="text" name="auditable_type" class="form-control" value="{{ $filters['auditable_type'] ?? '' }}" placeholder="e.g. User">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn tracker-primary-btn flex-fill">Filter</button>
                <a href="{{ route('admin.audit-logs.index') }}" class="btn tracker-outline-btn"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>Event</th><th>Model</th><th>User</th><th>IP</th><th>Time</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td><span class="tracker-badge">{{ $log->event }}</span></td>
                            <td>{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td>
                            <td>{{ $log->user?->name ?? 'System' }}</td>
                            <td>{{ $log->ip_address }}</td>
                            <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.audit-logs.show', $log) }}" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="tracker-empty-state"><i class="fa-solid fa-list-check"></i><p class="mb-0">No audit trail recorded yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
