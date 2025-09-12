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
        Role::create(['name' => 'superAdmin']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'guest']);

        # Permission - Files
        Permission::create(['name' => 'create-file']);
        Permission::create(['name' => 'edit-file']);
        Permission::create(['name' => 'delete-file']);
        Permission::create(['name' => 'view-file']);
        Permission::create(['name' => 'share-file']);

        # Permission - Labels
        Permission::create(['name' => 'create-label']);
        Permission::create(['name' => 'edit-labels']);
        Permission::create(['name' => 'delete-label']);

        # Permission - User
        Permission::create(['name' => 'assign-admin']);
        Permission::create(['name' => 'assign-permission']);

        $roleSuperAdmin = Role::findByName('superAdmin');
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

        $roleAdmin = Role::findByName('admin');
        $roleAdmin -> givePermissionTo('create-file');
        $roleAdmin -> givePermissionTo('edit-file');
        $roleAdmin -> givePermissionTo('delete-file');
        $roleAdmin -> givePermissionTo('view-file');
        $roleAdmin -> givePermissionTo('share-file');
        $roleAdmin -> givePermissionTo('create-label');
        $roleAdmin -> givePermissionTo('edit-label');
        $roleAdmin -> givePermissionTo('delete-label');

        $roleGuest = Role::findByName('guest');
        $roleGuest -> givePermissionTo('view-file');
        
    }
}
