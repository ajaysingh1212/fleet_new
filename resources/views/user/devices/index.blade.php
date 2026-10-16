@extends('layouts.app')

@section('page-eyebrow', 'Workspace')
@section('page-title', 'My Devices')

@section('page-actions')
    <form method="POST" action="{{ route('my-devices.revoke-others') }}" data-confirm-delete data-confirm-title="Sign out all other devices?">
        @csrf
        <button type="submit" class="btn tracker-outline-btn"><i class="fa-solid fa-right-from-bracket me-2"></i>Sign Out Other Devices</button>
    </form>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>Device</th><th>IP Address</th><th>Last Login</th><th>Last Activity</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td class="text-truncate" style="max-width: 260px;">{{ $session->device_name ?? 'Unknown device' }}</td>
                            <td>{{ $session->ip_address }}</td>
                            <td>{{ $session->last_login_at?->diffForHumans() }}</td>
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
                                    <form method="POST" action="{{ route('my-devices.revoke', $session) }}" data-confirm-delete data-confirm-title="Sign out this device?">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Sign Out</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="tracker-empty-state"><i class="fa-solid fa-mobile-screen-button"></i><p class="mb-0">No device sessions recorded yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($sessions->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $sessions->links() }}</div>
        @endif
    </div>
@endsection
