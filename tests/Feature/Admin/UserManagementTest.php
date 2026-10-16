<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    public function test_plain_user_cannot_access_user_management(): void
    {
        $this->actingAsPlainUser();

        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_a_user(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post(route('admin.users.store'), [
            'employee_id' => 'EMP-90001',
            'name' => 'QA Tester',
            'email' => 'qa.tester@example.com',
            'password' => 'Password@123',
            'status' => 'active',
            'timezone' => 'UTC',
            'roles' => ['User'],
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'qa.tester@example.com']);
        $this->assertTrue(User::where('email', 'qa.tester@example.com')->first()->hasRole('User'));
    }

    public function test_super_admin_can_update_and_soft_delete_a_user(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $target = User::factory()->create();
        $target->assignRole('User');

        $updateResponse = $this->put(route('admin.users.update', $target), [
            'employee_id' => $target->employee_id,
            'name' => 'Updated Name',
            'email' => $target->email,
            'status' => 'active',
            'timezone' => 'UTC',
            'roles' => ['User'],
        ]);

        $updateResponse->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
        $this->assertSame('Updated Name', $target->fresh()->name);

        $deleteResponse = $this->delete(route('admin.users.destroy', $target));
        $deleteResponse->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', ['id' => $target->id]);

        $this->assertNotSame($admin->id, $target->id);
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $admin = $this->actingAsSuperAdmin();

        $this->delete(route('admin.users.destroy', $admin))->assertForbidden();
        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
    }
}
