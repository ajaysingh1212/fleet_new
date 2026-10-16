<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionRequest;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function index(): View
    {
        $permissions = Permission::withCount('roles')->orderBy('name')->get()
            ->groupBy(fn (Permission $permission) => ucfirst(explode(' ', $permission->name, 2)[0]));

        return view('admin.permissions.index', [
            'permissionGroups' => $permissions,
        ]);
    }

    public function store(PermissionRequest $request): RedirectResponse
    {
        $permission = Permission::create(['name' => $request->validated('name'), 'guard_name' => 'web']);

        $this->activityLogService->log(auth()->user(), 'permission.created', $permission, ['permission' => $permission->name]);

        return redirect()->route('admin.permissions.index')->with('status', 'Permission created successfully.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($permission->roles()->exists()) {
            return redirect()->route('admin.permissions.index')->with('error', 'Cannot delete a permission that is still assigned to roles.');
        }

        $name = $permission->name;
        $permission->delete();

        $this->activityLogService->log(auth()->user(), 'permission.deleted', null, ['permission' => $name]);

        return redirect()->route('admin.permissions.index')->with('status', 'Permission deleted successfully.');
    }
}
