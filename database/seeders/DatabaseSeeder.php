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

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'User',
            'email' => 'test@gmail.com',
            'is_admin' => true,
        ]);
    }
}
