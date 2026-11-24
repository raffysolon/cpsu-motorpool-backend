<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin accounts are managed explicitly; do not recreate a default login.

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