<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = User::create([
            'fullname' => 'Super Admin',
            'email' => 'testingSuperA@gmail.com',
            'password' => bcrypt('pdudmsSA')
        ]);

        $superAdmin -> assignRole('superAdmin');

    }
}
