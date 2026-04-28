<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\PythonServiceClient;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue Job untuk memproses data report menggunakan Python service
 */
class ProcessDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of seconds the job can run
     * Python processing bisa memakan waktu hingga 5 menit
     */
    public $timeout = 300;

    /**
     * Number of tries (tidak auto-retry)
     * Error akan dicatat dan user akan dinotifikasi
     */
    public $tries = 1;

    /**
     * ID dari report yang akan diproses
     */
    protected int $reportId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $reportId)
    {
        $this->reportId = $reportId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $report = Report::find($this->reportId);

        if (!$report) {
            Log::warning("Report ID {$this->reportId} tidak ditemukan.");
            return;
        }

        try {
            // Update status ke processing
            $report->markAsProcessing();

            // Buat instance Python Service Client
            $pythonClient = new PythonServiceClient();

            // Kirim request ke Python service
            $pythonClient->process($report);

            // Python service akan callback ke Laravel saat selesai
            // Jadi tidak perlu update status di sini

        } catch (Exception $e) {
            // Update report status ke failed
            $report->markAsFailed($e->getMessage());

            // Log error untuk debugging
            Log::error("Gagal memproses Report ID {$this->reportId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     * Dipanggil ketika job gagal (exception, timeout, dll)
     */
    public function failed(Exception $exception): void
    {
        $report = Report::find($this->reportId);

        if ($report) {
            $report->markAsFailed(
                'Processing gagal: ' . $exception->getMessage()
            );
        }

        Log::error("ProcessDataJob gagal untuk Report ID {$this->reportId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     * Untuk monitoring di queue worker
     */
    public function tags(): array
    {
        return [
            'report:' . $this->reportId,
            'user:' . Report::find($this->reportId)?->user_id ?? 'unknown',
        ];
    }
}
