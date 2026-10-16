@extends('layouts.app')

@section('page-eyebrow', 'Communication')
@section('page-title', 'Support Tickets')

@section('content')
    <div class="tracker-filter-bar mb-4">
        <form method="GET" action="{{ route('admin.support-tickets.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="tracker-form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach (['open', 'in_progress', 'resolved', 'closed'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="tracker-form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">All Priorities</option>
                    @foreach (['low', 'medium', 'high', 'urgent'] as $priority)
                        <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ ucfirst($priority) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn tracker-primary-btn flex-fill">Filter</button>
                <a href="{{ route('admin.support-tickets.index') }}" class="btn tracker-outline-btn"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>Ticket #</th><th>Subject</th><th>User</th><th>Priority</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td>{{ $ticket->ticket_number }}</td>
                            <td>{{ $ticket->subject }}</td>
                            <td>{{ $ticket->user?->name }}</td>
                            <td>{{ ucfirst($ticket->priority) }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $ticket->status])</td>
                            <td class="text-end">
                                <a href="{{ route('admin.support-tickets.show', $ticket) }}" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="tracker-empty-state"><i class="fa-solid fa-headset"></i><p class="mb-0">No support tickets yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tickets->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $tickets->links() }}</div>
        @endif
    </div>
@endsection
