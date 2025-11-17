<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ShareRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::create(['name' => 'receiver', 'guard_name' => 'api']);

        $roleReceiver = Role::findByName('receiver', 'api');
        $roleReceiver -> givePermissionTo('edit-file');
        $roleReceiver -> givePermissionTo('view-file');

    }
}
