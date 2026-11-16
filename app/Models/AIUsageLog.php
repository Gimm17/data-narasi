<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model AIUsageLog
 * merepresentasikan log penggunaan AI provider untuk setiap request
 */
class AIUsageLog extends Model
{
    /**
     * Nama tabel database (override auto-generated a_i_usage_logs)
     */
    protected $table = 'ai_usage_logs';
    /**
     * Kolom yang bisa di-fill secara mass assignment
     */
    protected $fillable = [
        'report_id',
        'ai_provider_id',
        'prompt_length',
        'response_length',
        'tokens_used',
        'cost',
        'response_time_ms',
        'is_success',
        'error_message',
    ];

    /**
     * Type casting untuk kolom tertentu
     */
    protected $casts = [
        'is_success' => 'boolean',
        'tokens_used' => 'integer',
        'response_time_ms' => 'integer',
        'prompt_length' => 'integer',
        'response_length' => 'integer',
        'cost' => 'decimal:6',
    ];

    /**
     * Relasi belongs to report
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Relasi belongs to AI provider
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }

    /**
     * Cek apakah request berhasil.
     * Kolomnya bernama is_success (boolean), bukan status.
     */
    public function isSuccess(): bool
    {
        return (bool) $this->is_success;
    }

    /**
     * Cek apakah request gagal karena rate limit.
     * Jenis error tidak punya kolom sendiri — dibaca dari error_message.
     */
    public function isRateLimit(): bool
    {
        return $this->hasErrorMatching('rate');
    }

    /**
     * Cek apakah request gagal karena timeout
     */
    public function isTimeout(): bool
    {
        return $this->hasErrorMatching('timeout');
    }

    /**
     * Cek apakah request gagal karena auth error
     */
    public function isAuthError(): bool
    {
        return $this->hasErrorMatching('auth')
            || $this->hasErrorMatching('unauthorized')
            || $this->hasErrorMatching('401');
    }

    /**
     * Cek apakah request gagal karena validation
     */
    public function isValidationError(): bool
    {
        return $this->hasErrorMatching('validation') || $this->hasErrorMatching('validasi');
    }

    /**
     * Apakah error_message mengandung kata tertentu (case-insensitive).
     */
    private function hasErrorMatching(string $needle): bool
    {
        if ($this->is_success) {
            return false;
        }

        return str_contains(mb_strtolower((string) $this->error_message), $needle);
    }

    /**
     * Hitung cost dalam USD (estimasi kasar)
     * Asumsi: $0.00001 per token
     */
    public function getEstimatedCost(): float
    {
        if ($this->tokens_used === null) {
            return 0.0;
        }

        return $this->tokens_used * 0.00001;
    }

    /**
     * Format response time ke readable string
     */
    public function getFormattedResponseTime(): string
    {
        if ($this->response_time_ms === null) {
            return 'N/A';
        }

        if ($this->response_time_ms < 1000) {
            return $this->response_time_ms . 'ms';
        }

        return number_format($this->response_time_ms / 1000, 2) . 's';
    }
}
