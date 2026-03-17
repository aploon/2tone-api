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
            ['email' => 'test@test.com'],
            [
                'name' => 'Utilisateur Test',
                'password' => Hash::make('password'),
                'role' => User::ROLE_TENANT,
                'status' => User::STATUS_ACTIVE,
            ]
        );
    }
}
