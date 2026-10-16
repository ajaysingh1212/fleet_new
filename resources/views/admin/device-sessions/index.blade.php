@extends('layouts.app')

@section('page-eyebrow', 'Monitoring')
@section('page-title', 'Device Sessions')

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>User</th><th>Device</th><th>IP</th><th>Last Activity</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td>{{ $session->user?->name }}</td>
                            <td class="text-truncate" style="max-width: 260px;">{{ $session->device_name ?? 'Unknown device' }}</td>
                            <td>{{ $session->ip_address }}</td>
                            <td>{{ $session->last_activity_at?->diffForHumans() }}</td>
                            <td>
                                @if ($session->is_current && ! $session->logged_out_at)
                                    @include('admin.partials.status-pill', ['status' => 'active'])
                                @else
                                    @include('admin.partials.status-pill', ['status' => 'inactive'])
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($session->is_current && ! $session->logged_out_at)
                                    <form method="POST" action="{{ route('admin.device-sessions.revoke', $session) }}" data-confirm-delete data-confirm-title="Revoke this session?">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="tracker-empty-state"><i class="fa-solid fa-display"></i><p class="mb-0">No device sessions recorded yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($sessions->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $sessions->links() }}</div>
        @endif
    </div>
@endsection
