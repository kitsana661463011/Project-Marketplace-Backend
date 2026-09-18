<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin User (Admin@gmail.com / 12345Test!)
        User::updateOrCreate(
            ['email' => 'Admin@gmail.com'],
            [
                'username' => 'Admin',
                'password' => bcrypt('12345Test!'),
                'role' => 'admin',
                'status' => 'active',
                'phone' => '0811111111',
            ]
        );
    }
}
