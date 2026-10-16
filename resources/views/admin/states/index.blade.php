@extends('layouts.app')

@section('page-eyebrow', 'System')
@section('page-title', 'States')

@section('page-actions')
    @can('create', App\Models\State::class)
        <a href="{{ route('admin.states.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Add State</a>
    @endcan
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>Name</th><th>Country</th><th>Code</th><th>Cities</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($states as $state)
                        <tr>
                            <td class="fw-bold">{{ $state->name }}</td>
                            <td>{{ $state->country?->name }}</td>
                            <td>{{ $state->code ?? '—' }}</td>
                            <td>{{ $state->cities_count }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $state->is_active ? 'active' : 'inactive'])</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    @can('update', $state)
                                        <a href="{{ route('admin.states.edit', $state) }}" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-pen"></i></a>
                                    @endcan
                                    @can('delete', $state)
                                        <form method="POST" action="{{ route('admin.states.destroy', $state) }}" data-confirm-delete data-confirm-title="Delete this state?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="tracker-empty-state"><i class="fa-solid fa-map"></i><p class="mb-0">No states added yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($states->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $states->links() }}</div>
        @endif
    </div>
@endsection
