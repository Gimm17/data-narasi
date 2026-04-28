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
     * Secret token untuk validasi callback (HMAC signing)
     */
    protected string $secret;

    /**
     * Request timeout dalam detik
     */
    protected int $timeout;

    /**
     * Constructor — membaca dari config() bukan env() agar cache-safe
     */
    public function __construct()
    {
        $this->baseUrl = rtrim(config('python-service.url', 'http://localhost:8001'), '/');
        $this->secret = config('python-service.secret', 'rahasia-internal-token-ganti-ini');
        $this->timeout = config('python-service.timeout', 10);
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
     * Generate HMAC-SHA256 signature dari payload
     * Digunakan untuk memvalidasi bahwa callback berasal dari Python service
     * dan payload tidak di-tamper
     *
     * @param string $payload JSON-encoded payload
     * @return string HMAC-SHA256 hex digest
     */
    public function generateHmacSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }

    /**
     * Validasi callback dari Python service
     * Primary: HMAC signature validation (lebih aman)
     * Fallback: Shared secret token (backward compatibility)
     *
     * @param string|null $signature HMAC signature dari header X-Callback-Signature
     * @param string|null $secret Shared secret dari header X-Callback-Secret
     * @param string|null $rawPayload Raw JSON body untuk HMAC verification
     * @return bool
     */
    public function validateCallback(?string $signature, ?string $secret, ?string $rawPayload = null): bool
    {
        // Primary: HMAC signature validation
        if ($signature && $rawPayload) {
            $expectedSignature = $this->generateHmacSignature($rawPayload);
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
            Log::warning('HMAC signature mismatch — falling back to secret check');
        }

        // Fallback: Simple shared secret check
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
