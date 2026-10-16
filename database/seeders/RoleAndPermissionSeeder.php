<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view dashboard',
            'manage users',
            'manage roles',
            'manage permissions',
            'manage license plans',
            'manage user licenses',
            'manage tracking relations',
            'use tracking workspace',
            'manage tasks',
            'manage geofences',
            'manage tracked geofences',
            'manage settings',
            'manage countries',
            'manage states',
            'manage cities',
            'manage languages',
            'view notifications',
            'manage notification templates',
            'view activity logs',
            'view audit logs',
            'manage device sessions',
            'manage support tickets',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate('Super Admin', 'web');
        $admin = Role::findOrCreate('Admin', 'web');
        $manager = Role::findOrCreate('Manager', 'web');
        $user = Role::findOrCreate('User', 'web');

        $superAdmin->syncPermissions(Permission::all());
        $admin->syncPermissions([
            'view dashboard',
            'manage users',
            'manage license plans',
            'manage user licenses',
            'manage tracking relations',
            'use tracking workspace',
            'manage tasks',
            'manage geofences',
            'manage tracked geofences',
            'manage settings',
            'view notifications',
            'manage notification templates',
            'view activity logs',
            'view audit logs',
            'manage device sessions',
        ]);
        $manager->syncPermissions([
            'view dashboard',
            'manage user licenses',
            'manage tracking relations',
            'use tracking workspace',
            'manage geofences',
            'manage tracked geofences',
            'view notifications',
            'manage support tickets',
        ]);
        $user->syncPermissions([
            'view dashboard',
            'view notifications',
            'use tracking workspace',
            'manage tasks',
            'manage tracked geofences',
        ]);
    }
}
