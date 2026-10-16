@extends('layouts.app')

@section('page-eyebrow', 'Workspace')
@section('page-title', 'Ticket #' . $ticket->ticket_number)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('support.index') }}">Support</a></li>
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card tracker-surface-card mb-4">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-0">{{ $ticket->subject }}</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">{{ $ticket->message }}</p>
                </div>
            </div>

            @if ($ticket->resolution)
                <div class="card tracker-surface-card">
                    <div class="card-header border-0 bg-transparent">
                        <h3 class="tracker-card-title mb-0">Team Response</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ $ticket->resolution }}</p>
                        <span class="small text-muted">Responded {{ $ticket->resolved_at?->diffForHumans() }}</span>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-5">
            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-0">Ticket Details</h3>
                </div>
                <div class="card-body">
                    <div class="tracker-mini-list">
                        <div class="tracker-mini-item"><span>Priority</span><strong>{{ ucfirst($ticket->priority) }}</strong></div>
                        <div class="tracker-mini-item"><span>Status</span>@include('admin.partials.status-pill', ['status' => $ticket->status])</div>
                        <div class="tracker-mini-item"><span>Created</span><strong>{{ $ticket->created_at?->diffForHumans() }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
