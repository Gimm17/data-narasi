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

        // Kredensial admin dibaca dari .env agar tidak ada password polos di repo.
        // Fallback dipakai hanya untuk kemudahan development lokal.
        $email = env('ADMIN_EMAIL', 'admin17@gmail.com');
        $password = env('ADMIN_PASSWORD');

        if (empty($password)) {
            $this->command?->warn('ADMIN_PASSWORD belum diset di .env — akun admin tidak dibuat/diubah.');
            $this->command?->warn('Tambahkan ADMIN_EMAIL dan ADMIN_PASSWORD, lalu jalankan ulang seeder.');

            return;
        }

        // Create admin user (idempotent — safe to run multiple times)
        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'email' => $email,
                'password' => bcrypt($password),
                'is_admin' => true,
            ]
        );

        $this->command?->info("Akun admin siap: {$email}");
    }
}
