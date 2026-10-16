@extends('layouts.app')

@section('page-eyebrow', 'Administration')
@section('page-title', 'Users')

@section('page-actions')
    @can('create', App\Models\User::class)
        <a href="{{ route('admin.users.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Add User</a>
    @endcan
@endsection

@section('content')
    <div class="tracker-filter-bar mb-4">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="tracker-form-label">Search</label>
                <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Name, email, employee ID, phone">
            </div>
            <div class="col-md-3">
                <label class="tracker-form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="tracker-form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                    <option value="deleted" @selected(($filters['status'] ?? '') === 'deleted')>Deleted</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn tracker-primary-btn flex-fill">Filter</button>
                <a href="{{ route('admin.users.index') }}" class="btn tracker-outline-btn"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Employee ID</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="tracker-avatar-sm">
                                        @if ($user->avatar)
                                            <img src="{{ asset('storage/'.$user->avatar) }}" alt="{{ $user->name }}">
                                        @else
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div>
                                        <div class="fw-bold">{{ $user->name }}</div>
                                        <div class="small text-muted">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $user->employee_id }}</td>
                            <td>{{ $user->getRoleNames()->implode(', ') ?: '—' }}</td>
                            <td>{{ $user->department ?? '—' }}</td>
                            <td>
                                @if ($user->trashed())
                                    @include('admin.partials.status-pill', ['status' => 'deleted'])
                                @else
                                    @include('admin.partials.status-pill', ['status' => $user->status])
                                @endif
                            </td>
                            <td>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm tracker-outline-btn" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @if (! $user->trashed())
                                        @can('update', $user)
                                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm tracker-outline-btn " title="Edit"><i class="fa-solid fa-pen"></i></a>
                                        @endcan
                                        @can('delete', $user)
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm-delete data-confirm-title="Delete this user?" data-confirm-text="{{ $user->name }} will be moved to trash.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm tracker-outline-btn tracker-outline-btn-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        @endcan
                                    @else
                                        @can('restore', $user)
                                            <form method="POST" action="{{ route('admin.users.restore', $user->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm tracker-outline-btn" title="Restore"><i class="fa-solid fa-rotate-left"></i></button>
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="tracker-empty-state">
                                    <i class="fa-solid fa-users"></i>
                                    <p class="mb-0">No users found for the selected filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-footer border-0 bg-transparent">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection