<?php

namespace App\Services\Analysis;

use App\Models\AIProvider;
use App\Models\AIUsageLog;
use App\Models\Report;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * AnalysisPipeline — pengganti python-service/main.py:_process_report_background
 *
 * Menjalankan seluruh proses di dalam PHP tanpa layanan eksternal:
 *   cleansing → analisis statistik → chart PNG → prompt → narasi AI → simpan.
 *
 * Karena semuanya satu proses, tidak ada lagi transfer file base64 maupun
 * callback HMAC: hasil langsung ditulis ke record Report.
 */
class AnalysisPipeline
{
    /** Batas jumlah baris sebelum data disampling. */
    private const MAX_ROWS = 100_000;

    public function __construct(
        private readonly DataCleaner $cleaner = new DataCleaner(),
        private readonly DataAnalyzer $analyzer = new DataAnalyzer(),
        private readonly ChartBuilder $chartBuilder = new ChartBuilder(),
        private readonly PromptBuilder $promptBuilder = new PromptBuilder(),
        private readonly AiClient $aiClient = new AiClient(),
    ) {
    }

    /**
     * Proses satu report dari file mentah sampai narasi tersimpan.
     *
     * @throws Throwable bila tahap kritis gagal (report ditandai failed oleh caller)
     */
    public function process(Report $report): void
    {
        $startedAt = microtime(true);

        $sourcePath = $this->resolveSourcePath($report);
        $this->assertFileSize($sourcePath);

        // ── 1. Pembersihan data ──
        $cleanRelative = $this->cleanPathFor($report);
        $cleanAbsolute = Storage::disk('local')->path($cleanRelative);
        $this->ensureDirectory(dirname($cleanAbsolute));

        $cleanResult = $this->cleaner->run($sourcePath, $cleanAbsolute);
        $table = $cleanResult['table'];
        $cleaningLog = $cleanResult['cleaning_log'];

        if ($table->count() === 0) {
            throw new \RuntimeException('Dataset kosong setelah pembersihan — tidak ada baris yang bisa dianalisis.');
        }

        if ($table->count() > self::MAX_ROWS) {
            Log::warning('Dataset besar disampling', [
                'report_id' => $report->id,
                'rows' => $table->count(),
                'limit' => self::MAX_ROWS,
            ]);
            $table = $this->sampleRows($table, self::MAX_ROWS);
        }

        // ── 2. Analisis statistik ──
        $stats = $this->analyzer->run($table, $report->dataset_type);

        // ── 3. Chart PNG ──
        $charts = $this->chartBuilder->run($table, $stats, $report->id);
        $chartPaths = array_values(array_map(fn (array $chart) => $chart['path'], $charts));

        // ── 4. Prompt ──
        [$systemPrompt, $userPrompt] = $this->promptBuilder->build(
            $stats,
            $cleaningLog,
            $report->tone
        );

        // ── 5. Narasi AI ──
        $ai = $this->aiClient->generate(
            $userPrompt,
            $systemPrompt,
            (int) config('ai.max_tokens', 1024),
            $this->providerOrder()
        );

        // ── 6. Simpan hasil ──
        $report->update([
            'ai_narrative' => $ai['success'] ? $ai['narrative'] : null,
            'summary_stats' => $stats,
            'cleaning_log' => $cleaningLog,
            'chart_paths' => $chartPaths,
            'clean_path' => $cleanRelative,
            'total_rows' => $cleaningLog['original_rows'] ?? $table->count(),
            'clean_rows' => $table->count(),
            'ai_provider_used' => $ai['provider_used'],
            'processing_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'prompt_tokens' => $ai['prompt_tokens'] ?? null,
            'completion_tokens' => $ai['completion_tokens'] ?? null,
            'total_tokens' => $ai['total_tokens'] ?? null,
            'cost_usd' => $ai['cost_usd'] ?? null,
            'model_used' => $ai['model_used'] ?? null,
        ]);

        $this->storeUsageLogs($report, $ai['logs'] ?? []);

        if (!$ai['success']) {
            // Data & chart tetap tersimpan; user melihat narasi cadangan.
            $report->markAsFailed(
                'Semua provider AI gagal (' . $ai['attempts'] . ' percobaan). ' . $this->lastError($ai['logs'] ?? [])
            );
        } else {
            $report->markAsDone();
        }

        Log::info('Report selesai diproses', [
            'report_id' => $report->id,
            'rows' => $table->count(),
            'charts' => count($chartPaths),
            'provider' => $ai['provider_used'],
            'processing_time_ms' => $report->processing_time_ms,
        ]);
    }

    /**
     * Ambil sampel baris secara acak-tetap (deterministik per report).
     */
    private function sampleRows(DataTable $table, int $limit): DataTable
    {
        $rows = $table->rows();
        $total = count($rows);

        if ($total <= $limit) {
            return $table;
        }

        // Sampling merata: ambil setiap baris ke-n agar sebaran tetap representatif
        $step = $total / $limit;
        $sampled = [];
        for ($i = 0; $i < $limit; $i++) {
            $sampled[] = $rows[(int) floor($i * $step)];
        }

        return new DataTable($sampled, $table->columns());
    }

    /**
     * Buat folder tujuan bila belum ada.
     */
    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }
    }

    /**
     * Path absolut file sumber yang diunggah user.
     */
    private function resolveSourcePath(Report $report): string
    {
        $disk = Storage::disk('local');

        if (empty($report->original_path) || !$disk->exists($report->original_path)) {
            throw new \RuntimeException('File sumber tidak ditemukan: ' . ($report->original_path ?: '(kosong)'));
        }

        return $disk->path($report->original_path);
    }

    /**
     * Tolak file yang terlalu besar sebelum memuatnya ke memori.
     *
     * Angka batas dibaca dari config/datanarasi.php — sumber yang sama dengan
     * validasi UploadFileRequest, supaya keduanya tidak bisa lagi berbeda.
     */
    private function assertFileSize(string $path): void
    {
        $maxMb = (int) config('datanarasi.max_upload_mb', 10);
        $sizeMb = filesize($path) / 1048576;

        if ($sizeMb > $maxMb) {
            throw new \RuntimeException(sprintf(
                'File terlalu besar: %.1f MB (maks %d MB).',
                $sizeMb,
                $maxMb
            ));
        }
    }

    /**
     * Lokasi file bersih hasil cleansing.
     *
     * Disimpan di disk local (privat) karena hanya dipakai untuk unduhan
     * pemilik report — bukan aset publik.
     */
    private function cleanPathFor(Report $report): string
    {
        $ext = strtolower(pathinfo((string) $report->original_path, PATHINFO_EXTENSION)) ?: 'csv';

        return sprintf('cleans/report_%d_clean.%s', $report->id, $ext);
    }

    /**
     * Urutan provider dari tabel ai_providers (admin panel).
     *
     * @return array<int, array{slug: string, model_id: string|null, reasoning_effort: string|null}>|null
     */
    private function providerOrder(): ?array
    {
        $order = AIProvider::query()
            ->where('is_enabled', true)
            ->orderBy('priority')
            ->get(['slug', 'model_id', 'reasoning_effort'])
            ->filter(fn (AIProvider $provider) => !empty($provider->slug))
            ->unique('slug')
            ->map(fn (AIProvider $provider) => [
                'slug' => (string) $provider->slug,
                'model_id' => $provider->model_id ?: null,
                'reasoning_effort' => $provider->reasoning_effort ?: null,
            ])
            ->values()
            ->all();

        return $order !== [] ? $order : null;
    }

    /**
     * Simpan satu baris ai_usage_logs per percobaan provider.
     *
     * @param array<int, array<string, mixed>> $logs
     */
    private function storeUsageLogs(Report $report, array $logs): void
    {
        if ($logs === []) {
            return;
        }

        foreach ($logs as $logData) {
            $slug = (string) ($logData['provider_name'] ?? '');
            $provider = AIProvider::where('slug', $slug)->first();

            if (!$provider) {
                Log::warning("Provider '{$slug}' tidak ada di tabel ai_providers, usage log dilewati.");

                continue;
            }

            $isSuccess = ($logData['status'] ?? '') === 'success';

            AIUsageLog::create([
                'report_id' => $report->id,
                'ai_provider_id' => $provider->id,
                'prompt_length' => (int) ($logData['prompt_tokens'] ?? 0),
                'response_length' => (int) ($logData['completion_tokens'] ?? ($logData['tokens_used'] ?? 0)),
                'tokens_used' => $logData['tokens_used'] ?? null,
                'cost' => $logData['cost_usd'] ?? null,
                'response_time_ms' => (int) ($logData['response_time_ms'] ?? 0),
                'is_success' => $isSuccess,
                'error_message' => $logData['error_message'] ?? null,
            ]);

            if ($isSuccess) {
                $provider->incrementCalls();
            } else {
                $provider->incrementErrors();
            }
        }
    }

    /**
     * Ambil error terakhir dari daftar percobaan untuk pesan ke user.
     *
     * @param array<int, array<string, mixed>> $logs
     */
    private function lastError(array $logs): string
    {
        if ($logs === []) {
            return '';
        }

        $last = end($logs);

        return (string) ($last['error_message'] ?? '');
    }
}
