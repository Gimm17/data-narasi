<?php

/**
 * Konfigurasi Python Microservice
 *
 * File ini memastikan semua env() calls terpusat di config file,
 * sehingga aman saat menjalankan `php artisan config:cache` di production.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Python Service Base URL
    |--------------------------------------------------------------------------
    |
    | URL dimana Python FastAPI service berjalan.
    | Default: http://localhost:8001 untuk development lokal.
    |
    */
    'url' => env('PYTHON_SERVICE_URL', 'http://localhost:8001'),

    /*
    |--------------------------------------------------------------------------
    | Callback Secret
    |--------------------------------------------------------------------------
    |
    | Shared secret untuk validasi callback dari Python service.
    | Digunakan untuk HMAC signing dan backward-compatible simple token check.
    | WAJIB diganti di production!
    |
    */
    'secret' => env('PYTHON_SERVICE_SECRET', 'rahasia-internal-token-ganti-ini'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout dalam detik untuk HTTP request ke Python service.
    | Karena Python langsung return 202 Accepted, 10 detik sudah cukup.
    |
    */
    'timeout' => (int) env('PYTHON_SERVICE_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins (CORS)
    |--------------------------------------------------------------------------
    |
    | Domain yang diizinkan mengakses Python service.
    | Digunakan oleh Python FastAPI CORS middleware.
    | Pisahkan multiple origins dengan koma.
    |
    */
    'allowed_origins' => env('ALLOWED_ORIGINS', 'http://localhost:8000'),

];
