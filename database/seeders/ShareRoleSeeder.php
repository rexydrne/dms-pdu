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
        Role::create(['name' => 'receiver']);

        $roleReceiver = Role::findByName('receiver');
        $roleReceiver -> givePermissionTo('edit-file');
        $roleReceiver -> givePermissionTo('view-file');

    }
}
