<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Policies\RolePolicy;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles' => Role::withCount(['permissions', 'users'])->orderBy('name')->paginate(15),
            'protectedRoles' => RolePolicy::PROTECTED_ROLES,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', [
            'permissionGroups' => $this->groupedPermissions(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions') ?? []);

        $this->activityLogService->log(auth()->user(), 'role.created', $role, ['role' => $role->name]);

        return redirect()->route('admin.roles.index')->with('status', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        $role->load('permissions');

        return view('admin.roles.edit', [
            'role' => $role,
            'permissionGroups' => $this->groupedPermissions(),
            'rolePermissions' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions') ?? []);

        $this->activityLogService->log(auth()->user(), 'role.updated', $role, ['role' => $role->name]);

        return redirect()->route('admin.roles.index')->with('status', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $roleName = $role->name;
        $role->delete();

        $this->activityLogService->log(auth()->user(), 'role.deleted', null, ['role' => $roleName]);

        return redirect()->route('admin.roles.index')->with('status', 'Role deleted successfully.');
    }

    protected function groupedPermissions(): Collection
    {
        return Permission::orderBy('name')->get()->groupBy(function (Permission $permission) {
            return ucfirst(explode(' ', $permission->name, 2)[0]);
        });
    }
}
