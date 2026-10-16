@extends('layouts.app')

@section('page-eyebrow', 'System')
@section('page-title', 'Cities')

@section('page-actions')
    @can('create', App\Models\City::class)
        <a href="{{ route('admin.cities.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Add City</a>
    @endcan
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>Name</th><th>State</th><th>Country</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($cities as $city)
                        <tr>
                            <td class="fw-bold">{{ $city->name }}</td>
                            <td>{{ $city->state?->name }}</td>
                            <td>{{ $city->country?->name }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $city->is_active ? 'active' : 'inactive'])</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    @can('update', $city)
                                        <a href="{{ route('admin.cities.edit', $city) }}" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-pen"></i></a>
                                    @endcan
                                    @can('delete', $city)
                                        <form method="POST" action="{{ route('admin.cities.destroy', $city) }}" data-confirm-delete data-confirm-title="Delete this city?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="tracker-empty-state"><i class="fa-solid fa-city"></i><p class="mb-0">No cities added yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($cities->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $cities->links() }}</div>
        @endif
    </div>
@endsection
