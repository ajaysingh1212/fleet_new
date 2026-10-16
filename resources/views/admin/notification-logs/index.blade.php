@extends('layouts.app')

@section('page-eyebrow', 'Communication')
@section('page-title', 'Notification Log')

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>Type</th><th>Recipient</th><th>Message</th><th>Read</th><th>Sent</th></tr>
                </thead>
                <tbody>
                    @forelse ($notifications as $notification)
                        <tr>
                            <td>{{ class_basename($notification->type) }}</td>
                            <td>{{ $notification->notifiable?->name ?? $notification->notifiable_type.' #'.$notification->notifiable_id }}</td>
                            <td class="text-truncate" style="max-width: 320px;">{{ $notification->data['message'] ?? '—' }}</td>
                            <td>{{ $notification->read_at ? 'Yes' : 'No' }}</td>
                            <td>{{ $notification->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="tracker-empty-state"><i class="fa-regular fa-bell"></i><p class="mb-0">No notifications sent yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($notifications->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection
