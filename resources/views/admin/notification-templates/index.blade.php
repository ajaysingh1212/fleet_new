@extends('layouts.app')

@section('page-eyebrow', 'Communication')
@section('page-title', 'Notification Templates')

@section('page-actions')
    <a href="{{ route('admin.notification-templates.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Add Template</a>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>Name</th><th>Channel</th><th>Subject</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td class="fw-bold">{{ $template->name }}</td>
                            <td>{{ ucfirst($template->channel) }}</td>
                            <td>{{ $template->subject ?? '—' }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $template->is_active ? 'active' : 'inactive'])</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('admin.notification-templates.edit', $template) }}" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-pen"></i></a>
                                    <form method="POST" action="{{ route('admin.notification-templates.destroy', $template) }}" data-confirm-delete data-confirm-title="Delete this template?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="tracker-empty-state"><i class="fa-solid fa-envelope-open-text"></i><p class="mb-0">No templates yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($templates->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $templates->links() }}</div>
        @endif
    </div>
@endsection
