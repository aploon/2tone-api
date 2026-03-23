<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'tenant@example.com'],
            [
                'name' => 'Tenant',
                'password' => Hash::make('password'),
                'role' => User::ROLE_TENANT,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        User::firstOrCreate(
            ['email' => 'arnaudadjovi274@gmail.com'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
                'role' => User::ROLE_OWNER,
                'status' => User::STATUS_ACTIVE,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
            ]
        );
    }
}
