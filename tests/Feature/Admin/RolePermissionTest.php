<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    public function test_plain_user_cannot_manage_roles(): void
    {
        $this->actingAsPlainUser();

        $this->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_a_role_with_permissions(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post(route('admin.roles.store'), [
            'name' => 'Support Agent',
            'permissions' => ['manage support tickets'],
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'Support Agent')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('manage support tickets'));
    }

    public function test_super_admin_cannot_delete_a_protected_system_role(): void
    {
        $this->actingAsSuperAdmin();
        $role = Role::where('name', 'Manager')->first();

        $this->delete(route('admin.roles.destroy', $role))->assertForbidden();
        $this->assertDatabaseHas('roles', ['name' => 'Manager']);
    }

    public function test_super_admin_can_create_a_custom_permission(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post(route('admin.permissions.store'), [
            'name' => 'manage invoices',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.permissions.index'));
        $this->assertDatabaseHas('permissions', ['name' => 'manage invoices']);
    }

    public function test_permission_in_use_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();
        $permission = Permission::where('name', 'manage support tickets')->first();

        $response = $this->delete(route('admin.permissions.destroy', $permission));

        $response->assertRedirect(route('admin.permissions.index'));
        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }
}
