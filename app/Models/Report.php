<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Report
 * merepresentasikan laporan analisis data yang diupload oleh user
 */
class Report extends Model
{
    /**
     * Kolom yang bisa di-fill secara mass assignment
     */
    protected $fillable = [
        'user_id',
        'visitor_token',
        'title',
        'original_filename',
        'original_path',
        'clean_path',
        'status',
        'dataset_type',
        'tone',
        'total_rows',
        'clean_rows',
        'summary_stats',
        'cleaning_log',
        'chart_paths',
        'ai_narrative',
        'ai_provider_used',
        'processing_time_ms',
        'error_message',
    ];

    /**
     * Type casting untuk kolom tertentu
     */
    protected $casts = [
        'status' => ReportStatus::class,
        'summary_stats' => 'array',
        'cleaning_log' => 'array',
        'chart_paths' => 'array',
        'total_rows' => 'integer',
        'clean_rows' => 'integer',
        'processing_time_ms' => 'integer',
    ];

    /**
     * Relasi belongs to user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi has many ke report columns
     */
    public function columns(): HasMany
    {
        return $this->hasMany(ReportColumn::class);
    }

    /**
     * Relasi has many ke AI usage logs
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(AIUsageLog::class);
    }

    /**
     * Relasi has many ke report outputs
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(ReportOutput::class);
    }

    /**
     * Scope untuk filter report milik user tertentu
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Cek apakah report sedang diproses
     */
    public function isProcessing(): bool
    {
        return $this->status === ReportStatus::Processing;
    }

    /**
     * Cek apakah report sudah selesai
     */
    public function isDone(): bool
    {
        return $this->status === ReportStatus::Done;
    }

    /**
     * Cek apakah report gagal
     */
    public function isFailed(): bool
    {
        return $this->status === ReportStatus::Failed;
    }

    /**
     * Cek apakah report masih pending
     */
    public function isPending(): bool
    {
        return $this->status === ReportStatus::Pending;
    }

    /**
     * Update status ke processing
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => ReportStatus::Processing]);
    }

    /**
     * Update status ke done
     */
    public function markAsDone(): void
    {
        $this->update(['status' => ReportStatus::Done]);
    }

    /**
     * Update status ke failed dengan error message
     */
    public function markAsFailed(string $errorMessage = null): void
    {
        $this->update([
            'status' => ReportStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Hitung persentase baris yang bersih vs total
     */
    public function getCleanPercentage(): int
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return intval(($this->clean_rows / $this->total_rows) * 100);
    }

    /**
     * Ambil path file chart pertama (jika ada)
     */
    public function getFirstChartPath(): ?string
    {
        if (empty($this->chart_paths) || !is_array($this->chart_paths)) {
            return null;
        }

        return $this->chart_paths[0] ?? null;
    }
}
