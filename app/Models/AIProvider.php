<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model AI Provider
 * merepresentasikan konfigurasi provider AI (Gemini, Kimi, GLM, Claude)
 */
class AIProvider extends Model
{
    /**
     * Nama tabel secara eksplisit
     * Laravel convention: "AIProvider" → "a_i_providers"
     * Tapi migration kita pakai: "ai_providers"
     */
    protected $table = 'ai_providers';

    /**
     * Kolom yang bisa di-fill secara mass assignment
     */
    protected $fillable = [
        'name',
        'slug',
        'model_id',
        'base_url',
        'api_key_env',
        'priority',
        'is_enabled',
        'max_tokens',
        'timeout_seconds',
        'total_calls',
        'total_errors',
        'last_used_at',
    ];

    /**
     * Type casting untuk kolom tertentu
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'priority' => 'integer',
        'max_tokens' => 'integer',
        'timeout_seconds' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * Relasi has many ke AI usage logs
     */
    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AIUsageLog::class, 'ai_provider_id');
    }

    /**
     * Scope untuk filter hanya provider yang enabled
     * Urutkan berdasarkan priority (asc = paling kecil = paling prioritas)
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true)
            ->orderBy('priority', 'asc');
    }

    /**
     * Ambil API Key dari environment variable berdasarkan api_key_env
     * 
     * CATATAN: Dalam arsitektur microservice, API keys disimpan di Python service.
     * Method ini hanya untuk backward compatibility dan pengecekan lokal.
     * Jika key tidak ada di Laravel env, cek apakah ada record di ai_providers
     * yang menandakan key sudah dikonfigurasi di Python service.
     */
    public function getApiKey(): ?string
    {
        // Coba dari Laravel env (untuk local dev)
        $key = env($this->api_key_env);

        if (!empty($key)) {
            return $key;
        }

        // Di production, API keys ada di Python service container
        // Anggap tersedia jika provider enabled dan api_key_env sudah dikonfigurasi
        if ($this->is_enabled && !empty($this->api_key_env)) {
            return '[configured-in-python-service]';
        }

        return null;
    }

    /**
     * Cek apakah provider ini siap digunakan
     * Harus enabled dan punya api_key_env yang dikonfigurasi
     * (Pengecekan actual API key dilakukan oleh Python service)
     */
    public function isReady(): bool
    {
        return $this->is_enabled && !empty($this->api_key_env);
    }

    /**
     * Increment total_calls counter
     */
    public function incrementCalls(): void
    {
        $this->increment('total_calls');
        $this->touch('last_used_at');
    }

    /**
     * Increment total_errors counter
     */
    public function incrementErrors(): void
    {
        $this->increment('total_errors');
    }
}
