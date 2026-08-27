<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@cpsu.edu.ph',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Maria Santos',
            'email' => 'maria.santos@cpsu.edu.ph',
            'password' => Hash::make('driver123'),
            'role' => 'driver',
        ]);
    }
}