<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@cpsu.edu.ph'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]
        );

        User::firstOrCreate(
            ['email' => 'maria.santos@cpsu.edu.ph'],
            [
                'name' => 'Maria Santos',
                'password' => Hash::make('driver123'),
                'role' => 'driver',
            ]
        );
    }
}