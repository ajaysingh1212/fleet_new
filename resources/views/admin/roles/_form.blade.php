@php
    $role = $role ?? null;
    $rolePermissions = $rolePermissions ?? [];
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="tracker-form-label" for="name">Role Name</label>
        <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $role?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<h3 class="tracker-card-title mb-3">Permissions</h3>
<div class="row g-3 tracker-permission-matrix">
    @foreach ($permissionGroups as $group => $permissions)
        <div class="col-md-4">
            <div class="tracker-form-label">{{ $group }}</div>
            @foreach ($permissions as $permission)
                @php $checked = in_array($permission->name, old('permissions', $rolePermissions)); @endphp
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permissions[]" id="permission_{{ $permission->id }}" value="{{ $permission->name }}" @checked($checked)>
                    <label class="form-check-label" for="permission_{{ $permission->id }}">{{ $permission->name }}</label>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
