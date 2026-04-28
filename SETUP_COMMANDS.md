# SETUP_COMMANDS.md
# Jalankan perintah ini SECARA URUT di terminal sebelum mulai coding
# Buka terminal di folder: C:\Users\HP\Laravel\

## STEP 1 — Install Laravel 12 ke folder data-narasi
# PENTING: jalankan dari C:\Users\HP\Laravel\ (BUKAN dari dalam data-narasi)

composer create-project laravel/laravel data-narasi --prefer-dist

# Perintah di atas akan OVERWRITE folder data-narasi yang ada.
# File PROMPT.md, SKILL.md, .cursorrules dll yang sudah ada di dalam
# folder data-narasi akan TERHAPUS.
#
# SOLUSI: sebelum jalankan perintah di atas, pindahkan dulu file-file kita:
#   1. Cut semua file dari C:\Users\HP\Laravel\data-narasi\
#   2. Paste sementara ke C:\Users\HP\Laravel\_datanarasai_backup\
#   3. Jalankan composer create-project
#   4. Copy balik file-file kita ke dalam folder data-narasi yang baru

## STEP 2 — Masuk ke folder project
cd data-narasi

## STEP 3 — Buat database MySQL
# Buka MySQL client (HeidiSQL / TablePlus / phpMyAdmin / cmd mysql)
# Jalankan query:
#   CREATE DATABASE data_narasi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

## STEP 4 — Copy .env.example ke .env
copy .env.example .env

## STEP 5 — Generate app key
php artisan key:generate

## STEP 6 — Edit .env (buka di Cursor, isi bagian ini)
# DB_DATABASE=data_narasi
# DB_USERNAME=root
# DB_PASSWORD=         <-- isi password MySQL kamu
# QUEUE_CONNECTION=redis

## STEP 7 — Install Redis (kalau belum ada)
# Download Memurai (Redis for Windows): https://www.memurai.com/
# Atau pakai WSL dengan redis-server
# Setelah install, pastikan Redis berjalan di port 6379

## STEP 8 — Install Laravel dependencies tambahan
composer require inertiajs/inertia-laravel
composer require tightenco/ziggy
composer require laravel/sanctum

## STEP 9 — Install frontend dependencies
npm install
npm install vue@3
npm install @inertiajs/vue3
npm install @vitejs/plugin-vue
npm install pinia
npm install @headlessui/vue

## STEP 10 — Verifikasi semua OK
php artisan --version
# Harus muncul: Laravel Framework 12.x.x

php artisan migrate
# Harus sukses (migration bawaan Laravel: users, sessions, dll)

npm run dev
# Harus compile tanpa error

## SETELAH SEMUA STEP DI ATAS SELESAI
## Kembali ke Claude Desktop dan ketik: "step 1 selesai, lanjut"
## Claude akan langsung tulis semua file PHP, migration, model, dll ke folder ini
