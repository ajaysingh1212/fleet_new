@extends('layouts.app')

@section('page-eyebrow', 'Administration')
@section('page-title', 'Roles')

@section('page-actions')
    @can('create', Spatie\Permission\Models\Role::class)
        <a href="{{ route('admin.roles.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Add Role</a>
    @endcan
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Permissions</th>
                        <th>Users</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td class="fw-bold">{{ $role->name }}</td>
                            <td>{{ $role->permissions_count }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    @can('update', $role)
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm tracker-outline-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    @endcan
                                    @can('delete', $role)
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" data-confirm-delete data-confirm-title="Delete this role?" data-confirm-text="Users with this role will lose its permissions.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    @endcan
                                    @if (in_array($role->name, $protectedRoles))
                                        <span class="tracker-badge">System</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($roles->hasPages())
            <div class="card-footer border-0 bg-transparent">
                {{ $roles->links() }}
            </div>
        @endif
    </div>
@endsection
