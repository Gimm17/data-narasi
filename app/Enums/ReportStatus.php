<?php

namespace App\Enums;

/**
 * Status progress report dalam sistem
 */
enum ReportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';

    /**
     * Label dalam Bahasa Indonesia untuk UI
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Processing => 'Sedang Diproses',
            self::Done => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    /**
     * Tailwind CSS badge color class
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-100 text-gray-800',
            self::Processing => 'bg-blue-100 text-blue-800',
            self::Done => 'bg-green-100 text-green-800',
            self::Failed => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Cek apakah status sedang diproses
     */
    public function isProcessing(): bool
    {
        return $this === self::Processing;
    }

    /**
     * Cek apakah status sudah selesai
     */
    public function isDone(): bool
    {
        return $this === self::Done;
    }

    /**
     * Cek apakah status gagal
     */
    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

    /**
     * Cek apakah status masih pending
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
