<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Model ReportOutput
 * merepresentasikan output file yang dihasilkan dari analisis (PDF, Excel, Chart, dll)
 */
class ReportOutput extends Model
{
    /**
     * Kolom yang bisa di-fill secara mass assignment
     */
    protected $fillable = [
        'report_id',
        'output_type',
        'file_path',
        'public_url',
        'share_token',
        'is_public',
        'download_count',
        'expires_at',
    ];

    /**
     * Type casting untuk kolom tertentu
     */
    protected $casts = [
        'is_public' => 'boolean',
        'download_count' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Relasi belongs to report
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Generate random share token 8 karakter
     * Override jika sudah ada
     */
    public function generateShareToken(): void
    {
        $token = null;
        $exists = true;

        // Loop sampai dapat token yang unik
        while ($exists) {
            $token = strtoupper(Str::random(8));
            $exists = self::where('share_token', $token)->exists();
        }

        $this->share_token = $token;
        $this->save();
    }

    /**
     * Cek apakah share link masih valid (belum expire)
     */
    public function isShareLinkValid(): bool
    {
        if (!$this->is_public) {
            return false;
        }

        if ($this->expires_at === null) {
            return true; // Tidak ada expiry
        }

        return $this->expires_at->isFuture();
    }

    /**
     * Increment download counter
     */
    public function incrementDownload(): void
    {
        $this->increment('download_count');
    }

    /**
     * Cek apakah output ini PDF
     */
    public function isPDF(): bool
    {
        return $this->output_type === 'pdf';
    }

    /**
     * Cek apakah output ini Excel
     */
    public function isExcel(): bool
    {
        return $this->output_type === 'excel';
    }

    /**
     * Cek apakah output ini Chart PNG
     */
    public function isChart(): bool
    {
        return $this->output_type === 'chart_png';
    }

    /**
     * Cek apakah output ini JSON API
     */
    public function isJSON(): bool
    {
        return $this->output_type === 'json_api';
    }

    /**
     * Cek apakah output ini share page
     */
    public function isSharePage(): bool
    {
        return $this->output_type === 'share_page';
    }

    /**
     * Get public URL dengan fallback ke share URL
     */
    public function getPublicUrl(): string
    {
        if ($this->public_url) {
            return $this->public_url;
        }

        if ($this->share_token) {
            return route('share.show', $this->share_token);
        }

        return '#';
    }

    /**
     * Get label output type untuk display
     */
    public function getTypeLabel(): string
    {
        return match ($this->output_type) {
            'pdf' => 'PDF Document',
            'excel' => 'Excel Spreadsheet',
            'chart_png' => 'Chart Image',
            'json_api' => 'JSON Data',
            'share_page' => 'Shareable Page',
            default => 'Unknown',
        };
    }
}
