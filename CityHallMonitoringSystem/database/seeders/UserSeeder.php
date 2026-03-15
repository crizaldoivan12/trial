<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Default accounts for initial system access.
        // Change passwords after first login.
        User::updateOrCreate(
            ['email' => 'admin@cityhall.local'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('Admin@12345'),
                'role' => 'Admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'encoder@cityhall.local'],
            [
                'name' => 'Default Encoder',
                'password' => Hash::make('Encoder@12345'),
                'role' => 'Encoder',
            ]
        );

        User::updateOrCreate(
            ['email' => 'viewer@cityhall.local'],
            [
                'name' => 'Default Viewer',
                'password' => Hash::make('Viewer@12345'),
                'role' => 'Viewer',
            ]
        );
    }
}

