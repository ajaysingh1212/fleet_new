@extends('layouts.app')

@section('page-eyebrow', 'Monitoring')
@section('page-title', 'Audit Log Detail')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.audit-logs.index') }}">Audit Logs</a></li>
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
                        <div class="tracker-mini-item"><span>Event</span><strong>{{ $log->event }}</strong></div>
                        <div class="tracker-mini-item"><span>Model</span><strong>{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</strong></div>
                        <div class="tracker-mini-item"><span>User</span><strong>{{ $log->user?->name ?? 'System' }}</strong></div>
                        <div class="tracker-mini-item"><span>IP Address</span><strong>{{ $log->ip_address }}</strong></div>
                        <div class="tracker-mini-item"><span>Time</span><strong>{{ $log->created_at?->format('d M Y H:i:s') }}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-0">Field Changes</h3>
                </div>
                <div class="card-body table-responsive">
                    <table class="table tracker-table align-middle">
                        <thead>
                            <tr><th>Field</th><th>Old Value</th><th>New Value</th></tr>
                        </thead>
                        <tbody>
                            @php
                                $fields = collect(array_keys((array) $log->old_values))->merge(array_keys((array) $log->new_values))->unique();
                            @endphp
                            @forelse ($fields as $field)
                                <tr>
                                    <td class="fw-bold">{{ $field }}</td>
                                    <td class="text-danger">{{ is_array($log->old_values[$field] ?? null) ? json_encode($log->old_values[$field]) : ($log->old_values[$field] ?? '—') }}</td>
                                    <td class="text-success">{{ is_array($log->new_values[$field] ?? null) ? json_encode($log->new_values[$field]) : ($log->new_values[$field] ?? '—') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">No field-level changes recorded for this event.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
