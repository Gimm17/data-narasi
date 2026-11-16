<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\Analysis\AnalysisPipeline;
use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue Job untuk memproses data report.
 *
 * Seluruh pipeline (cleansing, analisis, chart, narasi AI) berjalan di dalam
 * proses PHP ini — tidak lagi mengirim request ke Python service lalu menunggu
 * callback. Dengan begitu aplikasi bisa berjalan di shared hosting cPanel.
 */
class ProcessDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Batas waktu job (detik).
     *
     * Nilai ini MENGALAHKAN --timeout milik worker, jadi harus dijaga
     * lebih kecil dari retry_after queue (300). Bila terbalik, job yang
     * masih berjalan akan diambil ulang dan diproses dua kali — panggilan
     * AI dan biayanya ikut berlipat.
     *
     * Anggaran: cleansing + analisis + chart (~2 detik) + narasi AI.
     * Satu provider yang sehat selesai dalam 30–60 detik.
     */
    public int $timeout = 230;

    /**
     * Jumlah percobaan. Kegagalan tidak diulang otomatis — user bisa
     * menekan tombol "Proses Ulang" di halaman report.
     */
    public int $tries = 1;

    public function __construct(protected int $reportId)
    {
    }

    public function handle(AnalysisPipeline $pipeline): void
    {
        $report = Report::find($this->reportId);

        if (!$report) {
            Log::warning("Report ID {$this->reportId} tidak ditemukan.");

            return;
        }

        try {
            $report->markAsProcessing();

            $pipeline->process($report);
        } catch (Throwable $e) {
            $report->markAsFailed($e->getMessage());

            Log::error("Gagal memproses Report ID {$this->reportId}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Dipanggil saat job mati (timeout, worker berhenti, dll).
     */
    public function failed(?Throwable $exception): void
    {
        $report = Report::find($this->reportId);

        if ($report) {
            $report->markAsFailed(
                'Processing gagal: ' . ($exception?->getMessage() ?? 'job dihentikan tanpa pesan')
            );
        }

        Log::error("ProcessDataJob gagal untuk Report ID {$this->reportId}", [
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Label untuk monitoring queue worker.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'report:' . $this->reportId,
            'user:' . (Report::find($this->reportId)?->user_id ?? 'guest'),
        ];
    }
}
