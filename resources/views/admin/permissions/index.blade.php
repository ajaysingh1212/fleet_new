@extends('layouts.app')

@section('page-eyebrow', 'Administration')
@section('page-title', 'Permissions')

@section('page-actions')
    <button type="button" class="btn tracker-primary-btn" data-bs-toggle="modal" data-bs-target="#createPermissionModal">
        <i class="fa-solid fa-plus me-2"></i>Add Permission
    </button>
@endsection

@section('content')
    <div class="row g-4">
        @foreach ($permissionGroups as $group => $permissions)
            <div class="col-md-6 col-xl-4">
                <div class="card tracker-surface-card h-100">
                    <div class="card-header border-0 bg-transparent">
                        <h3 class="tracker-card-title mb-0">{{ $group }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="tracker-mini-list">
                            @foreach ($permissions as $permission)
                                <div class="tracker-mini-item">
                                    <span>{{ $permission->name }} <span class="text-muted small">({{ $permission->roles_count }} roles)</span></span>
                                    <form method="POST" action="{{ route('admin.permissions.destroy', $permission) }}" data-confirm-delete data-confirm-title="Delete this permission?" data-confirm-text="This cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="modal fade" id="createPermissionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content tracker-modal">
                <form method="POST" action="{{ route('admin.permissions.store') }}">
                    @csrf
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Add Permission</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="tracker-form-label" for="permission_name">Permission Name</label>
                        <input id="permission_name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. manage invoices" value="{{ old('name') }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <span class="tracker-form-hint">Use lowercase, space-separated action names (e.g. "manage invoices").</span>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn tracker-outline-btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn tracker-primary-btn">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($errors->any())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('createPermissionModal')).show();
                });
            </script>
        @endpush
    @endif
@endsection
