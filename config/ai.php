<?php

/**
 * Konfigurasi AI provider untuk pipeline narasi bawaan Laravel.
 * Menggantikan python-service/ai_provider.py + cost_calculator.py.
 *
 * Semua API key tetap dibaca lewat env() di AiClient — file ini hanya
 * mengatur urutan, timeout, dan batas token.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Urutan fallback provider
    |--------------------------------------------------------------------------
    | Slug yang tidak punya API key di .env akan otomatis dilewati.
    | Nilai bisa ditimpa per-request dari tabel ai_providers (admin panel).
    */
    'order' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AI_PROVIDER_ORDER', 'limitrouter,gemini,kimi,glm,nvidia,minimax,claude,openrouter,tokenrouter'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    | timeout      : batas normal per request (detik)
    | long_timeout : provider reasoning yang lebih lambat (detik)
    */
    'timeout' => (int) env('AI_REQUEST_TIMEOUT', 90),
    'long_timeout' => (int) env('AI_REQUEST_LONG_TIMEOUT', 180),

    /*
    |--------------------------------------------------------------------------
    | API key per provider
    |--------------------------------------------------------------------------
    | Dibaca lewat env() di sini — bukan di service — supaya tetap terbaca
    | setelah `php artisan config:cache` di server produksi.
    | Provider yang key-nya kosong akan otomatis dilewati.
    */
    'keys' => [
        'limitrouter' => env('LIMITROUTER_API_KEY'),
        'gemini' => env('GEMINI_API_KEY'),
        'kimi' => env('KIMI_API_KEY'),
        'glm' => env('GLM_API_KEY'),
        'nvidia' => env('NVIDIA_API_KEY'),
        'minimax' => env('MINIMAX_API_KEY'),
        'claude' => env('CLAUDE_API_KEY'),
        'openrouter' => env('OPENROUTER_API_KEY'),
        'tokenrouter' => env('TOKENROUTER_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Daftar model per provider yang mengambil model dari /models
    |--------------------------------------------------------------------------
    | Provider di bawah ini menyediakan endpoint OpenAI-compatible GET /models,
    | sehingga daftar model diambil langsung saat admin membuka panel —
    | tidak perlu memperbarui katalog secara manual.
    */
    'live_model_catalog' => [
        'limitrouter',
    ],

    /*
    |--------------------------------------------------------------------------
    | Token output maksimum untuk narasi
    |--------------------------------------------------------------------------
    | Narasi 5–8 paragraf dengan 6 bagian (overview, tren, breakdown,
    | distribusi, korelasi, rekomendasi) butuh 1000–1600 token output.
    | Nilai 1024 terlalu kecil dan membuat narasi terpotong di tengah.
    */
    'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),

];
