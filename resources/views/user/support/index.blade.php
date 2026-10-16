@extends('layouts.app')

@section('page-eyebrow', 'Workspace')
@section('page-title', 'Support')

@section('page-actions')
    <a href="{{ route('support.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>New Ticket</a>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>Ticket #</th><th>Subject</th><th>Priority</th><th>Status</th><th>Created</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td>{{ $ticket->ticket_number }}</td>
                            <td>{{ $ticket->subject }}</td>
                            <td>{{ ucfirst($ticket->priority) }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $ticket->status])</td>
                            <td>{{ $ticket->created_at?->diffForHumans() }}</td>
                            <td class="text-end">
                                <a href="{{ route('support.show', $ticket) }}" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="tracker-empty-state"><i class="fa-solid fa-headset"></i><p class="mb-0">You haven't raised any support tickets yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tickets->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $tickets->links() }}</div>
        @endif
    </div>
@endsection
