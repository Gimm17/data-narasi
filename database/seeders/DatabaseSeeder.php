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
        // Run AI Providers seeder
        $this->call([
            AIProviderSeeder::class,
        ]);

        // Create admin user (idempotent — safe to run multiple times)
        User::updateOrCreate(
            ['email' => 'admin17@gmail.com'],
            [
                'name' => 'Admin',
                'email' => 'admin17@gmail.com',
                'password' => bcrypt(env('ADMIN_PASSWORD')),
                'is_admin' => true,
            ]
        );
    }
}
