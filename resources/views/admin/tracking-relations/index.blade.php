@extends('layouts.app')

@section('page-eyebrow', 'Licensing')
@section('page-title', 'Tracking Relations')

@section('page-actions')
    @can('create', App\Models\TrackingRelation::class)
        <a href="{{ route('admin.tracking-relations.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Add Relation</a>
    @endcan
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr>
                        <th>Tracker</th>
                        <th>Tracked</th>
                        <th>Relationship</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($relations as $relation)
                        <tr>
                            <td>{{ $relation->trackerUser?->name }}</td>
                            <td>{{ $relation->trackedUser?->name }}</td>
                            <td>{{ $relation->relationship_name }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $relation->status])</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    @can('update', $relation)
                                        <a href="{{ route('admin.tracking-relations.edit', $relation) }}" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-pen"></i></a>
                                    @endcan
                                    @can('delete', $relation)
                                        <form method="POST" action="{{ route('admin.tracking-relations.destroy', $relation) }}" data-confirm-delete data-confirm-title="Remove this tracking relation?" data-confirm-text="The assigned license will stay with this tracked person and its expiry timer will continue.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="tracker-empty-state"><i class="fa-solid fa-diagram-project"></i><p class="mb-0">No tracking relations yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($relations->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $relations->links() }}</div>
        @endif
    </div>
@endsection
