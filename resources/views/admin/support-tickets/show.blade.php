@extends('layouts.app')

@section('page-eyebrow', 'Communication')
@section('page-title', 'Ticket #' . $ticket->ticket_number)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.support-tickets.index') }}">Support Tickets</a></li>
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
                        <h3 class="tracker-card-title mb-0">Resolution</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ $ticket->resolution }}</p>
                        <span class="small text-muted">Resolved {{ $ticket->resolved_at?->diffForHumans() }}</span>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-5">
            <div class="card tracker-surface-card mb-4">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-0">Details</h3>
                </div>
                <div class="card-body">
                    <div class="tracker-mini-list">
                        <div class="tracker-mini-item"><span>Requested by</span><strong>{{ $ticket->user?->name }}</strong></div>
                        <div class="tracker-mini-item"><span>Priority</span><strong>{{ ucfirst($ticket->priority) }}</strong></div>
                        <div class="tracker-mini-item"><span>Status</span>@include('admin.partials.status-pill', ['status' => $ticket->status])</div>
                        <div class="tracker-mini-item"><span>Assigned To</span><strong>{{ $ticket->assignee?->name ?? 'Unassigned' }}</strong></div>
                        <div class="tracker-mini-item"><span>Created</span><strong>{{ $ticket->created_at?->diffForHumans() }}</strong></div>
                    </div>
                </div>
            </div>

            @can('respond', $ticket)
                <div class="card tracker-surface-card">
                    <div class="card-header border-0 bg-transparent">
                        <h3 class="tracker-card-title mb-0">Respond</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.support-tickets.respond', $ticket) }}">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="tracker-form-label" for="assigned_to">Assign To</label>
                                <select id="assigned_to" name="assigned_to" class="form-select">
                                    <option value="">Unassigned</option>
                                    @foreach ($staff as $member)
                                        <option value="{{ $member->id }}" @selected(old('assigned_to', $ticket->assigned_to) == $member->id)>{{ $member->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="tracker-form-label" for="status">Status</label>
                                <select id="status" name="status" class="form-select">
                                    @foreach (['open', 'in_progress', 'resolved', 'closed'] as $status)
                                        <option value="{{ $status }}" @selected(old('status', $ticket->status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="tracker-form-label" for="resolution">Resolution Note</label>
                                <textarea id="resolution" name="resolution" rows="4" class="form-control @error('resolution') is-invalid @enderror" required>{{ old('resolution', $ticket->resolution) }}</textarea>
                                @error('resolution')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn tracker-primary-btn w-100">Save Response</button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
