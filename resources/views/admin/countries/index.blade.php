@extends('layouts.app')

@section('page-eyebrow', 'System')
@section('page-title', 'Countries')

@section('page-actions')
    @can('create', App\Models\Country::class)
        <a href="{{ route('admin.countries.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Add Country</a>
    @endcan
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>Name</th><th>ISO2</th><th>ISO3</th><th>Phone Code</th><th>States</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($countries as $country)
                        <tr>
                            <td class="fw-bold">{{ $country->name }}</td>
                            <td>{{ $country->iso2 }}</td>
                            <td>{{ $country->iso3 ?? '—' }}</td>
                            <td>{{ $country->phone_code ?? '—' }}</td>
                            <td>{{ $country->states_count }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $country->is_active ? 'active' : 'inactive'])</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    @can('update', $country)
                                        <a href="{{ route('admin.countries.edit', $country) }}" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-pen"></i></a>
                                    @endcan
                                    @can('delete', $country)
                                        <form method="POST" action="{{ route('admin.countries.destroy', $country) }}" data-confirm-delete data-confirm-title="Delete this country?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="tracker-empty-state"><i class="fa-solid fa-earth-americas"></i><p class="mb-0">No countries added yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($countries->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $countries->links() }}</div>
        @endif
    </div>
@endsection
