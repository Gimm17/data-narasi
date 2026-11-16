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
     * Cek apakah kolom ini bertipe numerik.
     *
     * Tabel report_columns hanya punya kolom: id, report_id, name, data_type,
     * is_nullable, sample_value. Kolom seperti is_numeric / null_count tidak
     * ada, jadi nilai yang tidak tersedia dibaca lewat helper null-safe.
     */
    public function isNumericType(): bool
    {
        if (($this->is_numeric ?? null) === true) {
            return true;
        }

        return in_array($this->data_type, ['integer', 'decimal', 'float', 'numeric'], true);
    }

    /**
     * Ambil nilai kolom bila ada, atau null.
     */
    private function attr(string $key): mixed
    {
        $attributes = $this->getAttributes();

        return array_key_exists($key, $attributes) ? $attributes[$key] : null;
    }

    /**
     * Cek apakah kolom ini memiliki banyak nilai null
     * Threshold: > 20% null
     */
    public function hasHighNullCount(): bool
    {
        $totalRows = (int) ($this->report?->total_rows ?? 0);
        $nullCount = $this->attr('null_count');

        if ($totalRows === 0 || $nullCount === null) {
            return false;
        }

        return (($nullCount / $totalRows) * 100) > 20;
    }

    /**
     * Ambil range nilai (jika numerik)
     */
    public function getRange(): ?string
    {
        $min = $this->attr('min_value');
        $max = $this->attr('max_value');

        if (!$this->isNumericType() || $min === null || $max === null) {
            return null;
        }

        return "{$min} - {$max}";
    }

    /**
     * Hitung persentase unique values
     */
    public function getUniquePercentage(): int
    {
        $totalRows = (int) ($this->report?->total_rows ?? 0);
        $unique = $this->attr('unique_count');

        if ($totalRows === 0 || $unique === null) {
            return 0;
        }

        return intval(($unique / $totalRows) * 100);
    }

    /**
     * Cek apakah kolom ini kandidat untuk primary key
     * Syarat: unique_count = total_rows dan null_count = 0
     */
    public function isCandidateKey(): bool
    {
        $totalRows = (int) ($this->report?->total_rows ?? 0);
        $unique = $this->attr('unique_count');
        $nulls = $this->attr('null_count');

        if ($totalRows === 0 || $unique === null || $nulls === null) {
            return false;
        }

        return (int) $unique === $totalRows && (int) $nulls === 0;
    }
}
