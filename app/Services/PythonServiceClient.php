<?php

namespace App\Services;

use App\Models\Report;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service Client untuk berkomunikasi dengan Python FastAPI Service
 * Mengirim file untuk diproses dan menerima callback saat selesai
 */
class PythonServiceClient
{
    /**
     * Python Service Base URL
     */
    protected string $baseUrl;

    /**
     * Secret token untuk validasi callback
     */
    protected string $secret;

    /**
     * Request timeout dalam detik
     */
    protected int $timeout = 10;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->baseUrl = rtrim(env('PYTHON_SERVICE_URL', 'http://localhost:8001'), '/');
        $this->secret = env('PYTHON_SERVICE_SECRET', 'rahasia-internal-token-ganti-ini');
    }

    /**
     * Kirim report ke Python service untuk diproses
     * Python akan memproses secara async dan callback ke Laravel saat selesai
     *
     * @throws Exception
     */
    public function process(Report $report): void
    {
        $callbackUrl = route('api.reports.callback', $report->id);

        // Gunakan Storage::path() yang mengembalikan path absolut yang benar
        // Laravel 13 menyimpan file local disk di storage/app/private/ bukan storage/app/
        $rawPath = Storage::path($report->original_path);
        // Normalize path separators untuk kompatibilitas Python di Windows
        $filePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rawPath);

        $payload = [
            'report_id' => $report->id,
            'file_path' => $filePath,
            'analysis_type' => $report->dataset_type,
            'tone' => $report->tone,
            'callback_url' => $callbackUrl,
            'callback_secret' => $this->secret,
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/process", $payload);

            if ($response->successful()) {
                Log::info("Report ID {$report->id} diterima oleh Python service (background processing)", [
                    'response' => $response->json(),
                    'status' => $response->status(),
                ]);
            } else {
                throw new Exception("Python service return error: " . $response->body());
            }

        } catch (Exception $e) {
            Log::error("Gagal mengirim Report ID {$report->id} ke Python service", [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            throw new Exception("Gagal menghubungi Python service: " . $e->getMessage());
        }
    }

    /**
     * Validasi callback secret dari Python service
     * Mencegah callback dari source yang tidak sah
     */
    public function validateCallback(?string $secret): bool
    {
        return $secret === $this->secret;
    }

    /**
     * Cek kesehatan Python service
     * Return true jika service tersedia
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(2)->get("{$this->baseUrl}/health");

            return $response->successful() &&
                   isset($response->json()['status']) &&
                   $response->json()['status'] === 'ok';

        } catch (Exception $e) {
            Log::warning("Python service health check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ambil daftar proses yang sedang berjalan di Python service
     * (Optional - untuk monitoring)
     */
    public function getActiveProcesses(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/processes/active");

            if ($response->successful()) {
                return $response->json()['processes'] ?? [];
            }

        } catch (Exception $e) {
            Log::warning("Gagal mengambil active processes: " . $e->getMessage());
        }

        return [];
    }
}
