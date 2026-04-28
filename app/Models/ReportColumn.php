<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model ReportColumn
 * merepresentasikan metadata kolom dari file yang diupload
 */
class ReportColumn extends Model
{
    /**
     * Kolom yang bisa di-fill secara mass assignment
     */
    protected $fillable = [
        'report_id',
        'column_name',
        'data_type',
        'null_count',
        'unique_count',
        'min_value',
        'max_value',
        'mean_value',
        'sample_values',
        'is_numeric',
        'has_outliers',
    ];

    /**
     * Type casting untuk kolom tertentu
     */
    protected $casts = [
        'sample_values' => 'array',
        'is_numeric' => 'boolean',
        'has_outliers' => 'boolean',
        'null_count' => 'integer',
        'unique_count' => 'integer',
        'min_value' => 'decimal',
        'max_value' => 'decimal',
        'mean_value' => 'decimal',
    ];

    /**
     * Relasi belongs to report
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Cek apakah kolom ini bertipe numerik
     */
    public function isNumericType(): bool
    {
        return $this->is_numeric || in_array($this->data_type, ['integer', 'decimal', 'float']);
    }

    /**
     * Cek apakah kolom ini memiliki banyak nilai null
     * Threshold: > 20% null
     */
    public function hasHighNullCount(): bool
    {
        if (!$this->report) {
            return false;
        }

        $totalRows = $this->report->total_rows;
        if ($totalRows === 0) {
            return false;
        }

        $nullPercentage = ($this->null_count / $totalRows) * 100;

        return $nullPercentage > 20;
    }

    /**
     * Ambil range nilai (jika numerik)
     */
    public function getRange(): ?string
    {
        if (!$this->isNumericType() || $this->min_value === null || $this->max_value === null) {
            return null;
        }

        return "{$this->min_value} - {$this->max_value}";
    }

    /**
     * Hitung persentase unique values
     */
    public function getUniquePercentage(): int
    {
        if (!$this->report || $this->report->total_rows === 0) {
            return 0;
        }

        return intval(($this->unique_count / $this->report->total_rows) * 100);
    }

    /**
     * Cek apakah kolom ini kandidat untuk primary key
     * Syarat: unique_count = total_rows dan null_count = 0
     */
    public function isCandidateKey(): bool
    {
        if (!$this->report) {
            return false;
        }

        return $this->unique_count === $this->report->total_rows && $this->null_count === 0;
    }
}
