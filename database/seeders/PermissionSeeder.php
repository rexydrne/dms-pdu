<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        # Roles
        Role::create(['name' => 'superAdmin', 'guard_name' => 'api']);
        Role::create(['name' => 'admin', 'guard_name' => 'api']);
        Role::create(['name' => 'guest', 'guard_name' => 'api']);

        # Permission - Files
        Permission::create(['name' => 'create-file', 'guard_name' => 'api']);
        Permission::create(['name' => 'edit-file', 'guard_name' => 'api']);
        Permission::create(['name' => 'delete-file', 'guard_name' => 'api']);
        Permission::create(['name' => 'view-file', 'guard_name' => 'api']);
        Permission::create(['name' => 'share-file', 'guard_name' => 'api']);

        # Permission - Labels
        Permission::create(['name' => 'create-label', 'guard_name' => 'api']);
        Permission::create(['name' => 'edit-label', 'guard_name' => 'api']);
        Permission::create(['name' => 'delete-label', 'guard_name' => 'api']);

        # Permission - User
        Permission::create(['name' => 'assign-admin', 'guard_name' => 'api']);
        Permission::create(['name' => 'assign-permission', 'guard_name' => 'api']);

        $roleSuperAdmin = Role::findByName('superAdmin', 'api');
        $roleSuperAdmin -> givePermissionTo('create-file');
        $roleSuperAdmin -> givePermissionTo('edit-file');
        $roleSuperAdmin -> givePermissionTo('delete-file');
        $roleSuperAdmin -> givePermissionTo('view-file');
        $roleSuperAdmin -> givePermissionTo('share-file');
        $roleSuperAdmin -> givePermissionTo('create-label');
        $roleSuperAdmin -> givePermissionTo('edit-label');
        $roleSuperAdmin -> givePermissionTo('delete-label');
        $roleSuperAdmin -> givePermissionTo('assign-admin');
        $roleSuperAdmin -> givePermissionTo('assign-permission');

        $roleAdmin = Role::findByName('admin', 'api');
        $roleAdmin -> givePermissionTo('create-file');
        $roleAdmin -> givePermissionTo('edit-file');
        $roleAdmin -> givePermissionTo('delete-file');
        $roleAdmin -> givePermissionTo('view-file');
        $roleAdmin -> givePermissionTo('share-file');
        $roleAdmin -> givePermissionTo('create-label');
        $roleAdmin -> givePermissionTo('edit-label');
        $roleAdmin -> givePermissionTo('delete-label');

        $roleGuest = Role::findByName('guest', 'api');
        $roleGuest -> givePermissionTo('view-file');

    }
}
