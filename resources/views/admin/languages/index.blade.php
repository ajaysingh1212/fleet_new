@extends('layouts.app')

@section('page-eyebrow', 'System')
@section('page-title', 'Languages')

@section('page-actions')
    @can('create', App\Models\Language::class)
        <a href="{{ route('admin.languages.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Add Language</a>
    @endcan
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr><th>Name</th><th>Locale</th><th>Default</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($languages as $language)
                        <tr>
                            <td class="fw-bold">{{ $language->name }}</td>
                            <td>{{ $language->locale }}</td>
                            <td>{{ $language->is_default ? 'Yes' : 'No' }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $language->is_active ? 'active' : 'inactive'])</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    @can('update', $language)
                                        <a href="{{ route('admin.languages.edit', $language) }}" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-pen"></i></a>
                                    @endcan
                                    @can('delete', $language)
                                        <form method="POST" action="{{ route('admin.languages.destroy', $language) }}" data-confirm-delete data-confirm-title="Delete this language?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="tracker-empty-state"><i class="fa-solid fa-language"></i><p class="mb-0">No languages added yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($languages->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $languages->links() }}</div>
        @endif
    </div>
@endsection
