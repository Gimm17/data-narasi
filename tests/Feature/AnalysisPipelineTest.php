<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\AIProvider;
use App\Models\AIUsageLog;
use App\Models\Report;
use App\Services\Analysis\AiClient;
use App\Services\Analysis\AnalysisPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uji pipeline analisis end-to-end: cleansing → chart → narasi → simpan.
 * AI dipalsukan agar tidak memanggil API sungguhan saat test.
 */
class AnalysisPipelineTest extends TestCase
{
    use RefreshDatabase;

    /** CSV sumber dengan 3 kategori, kolom tanggal, dan angka. */
    private const CSV = <<<CSV
        tanggal,produk,kategori,qty,harga,total
        2024-01-05,Kopi Susu,Minuman,2,15000,30000
        2024-02-11,Teh Manis,Minuman,1,8000,8000
        2024-03-02,Nasi Goreng,Makanan,1,25000,25000
        2024-04-18,Kopi Susu,Minuman,3,15000,45000
        2024-05-22,Americano,Minuman,1,18000,18000
        2024-06-30,Nasi Goreng,Makanan,2,25000,50000
        2024-07-14,Teh Manis,Minuman,4,8000,32000
        2024-08-09,Americano,Minuman,2,18000,36000
        CSV;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        config([
            'ai.keys' => [
                'gemini' => 'test-key',
                'kimi' => null,
                'glm' => null,
                'nvidia' => null,
                'minimax' => null,
                'claude' => null,
                'openrouter' => null,
                'tokenrouter' => null,
            ],
            'ai.timeout' => 5,
            'ai.long_timeout' => 5,
            'ai.max_tokens' => 512,
        ]);
    }

    /**
     * Buat report yang menunjuk ke file CSV di disk local.
     */
    private function createReportWithFile(): Report
    {
        Storage::disk('local')->put('uploads/1/test.csv', self::CSV);

        return Report::create([
            'user_id' => null,
            'visitor_token' => 'test-token',
            'title' => 'Uji Pipeline',
            'original_filename' => 'test.csv',
            'original_path' => 'uploads/1/test.csv',
            'status' => ReportStatus::Pending,
            'dataset_type' => 'penjualan',
            'tone' => 'formal',
            'total_rows' => 0,
            'clean_rows' => 0,
        ]);
    }

    /**
     * Narasi valid: > 50 kata, ada angka, tanpa karakter CJK.
     */
    private function validNarrative(): string
    {
        return str_repeat(
            'Total penjualan mencapai 145.000 dari 8 transaksi dengan rata-rata 18.125. '
            . 'Kopi Susu menjadi produk terlaris dengan 75.000 atau 51 persen dari total. '
            . 'Kategori Minuman mendominasi 5 dari 8 transaksi. '
            . 'Rekomendasi: fokuskan promosi pada Kopi Susu, tinjau harga Americano, '
            . 'dan tambah stok pada periode akhir bulan. ',
            2
        );
    }

    public function test_pipeline_menyimpan_narasi_chart_dan_status_done(): void
    {
        AIProvider::create([
            'name' => 'Gemini',
            'slug' => 'gemini',
            'model_id' => 'gemini-3.5-flash',
            'api_key_env' => 'GEMINI_API_KEY',
            'priority' => 1,
            'is_enabled' => true,
            'max_tokens' => 512,
            'timeout_seconds' => 30,
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => $this->validNarrative()]]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 700,
                    'candidatesTokenCount' => 300,
                    'totalTokenCount' => 1000,
                ],
                'modelVersion' => 'gemini-3.5-flash',
            ], 200),
        ]);

        $report = $this->createReportWithFile();

        app(AnalysisPipeline::class)->process($report);

        $report->refresh();

        // Status dan narasi
        $this->assertEquals('done', $report->status->value);
        $this->assertNotEmpty($report->ai_narrative);
        $this->assertEquals('gemini', $report->ai_provider_used);
        $this->assertEquals(1000, $report->total_tokens);
        $this->assertGreaterThan(0, (float) $report->cost_usd);

        // Statistik & log pembersihan tersimpan sebagai array
        $this->assertEquals(8, $report->total_rows);
        $this->assertIsArray($report->summary_stats);
        $this->assertArrayHasKey('analysis_type', $report->summary_stats);
        $this->assertIsArray($report->cleaning_log);

        // Chart tertulis ke disk publik dan path-nya tersimpan
        $this->assertNotEmpty($report->chart_paths);
        foreach ($report->chart_paths as $path) {
            Storage::disk('public')->assertExists($path);
            $this->assertStringStartsWith('charts/' . $report->id . '/', $path);
        }

        // File bersih tersimpan di disk privat
        $this->assertNotNull($report->clean_path);
        Storage::disk('local')->assertExists($report->clean_path);

        // Usage log tercatat & counter provider bertambah
        $this->assertEquals(1, AIUsageLog::where('report_id', $report->id)->count());
        $this->assertEquals(1, AIProvider::where('slug', 'gemini')->value('total_calls'));
    }

    public function test_pipeline_gagal_ai_tetap_menyimpan_data(): void
    {
        AIProvider::create([
            'name' => 'Gemini',
            'slug' => 'gemini',
            'model_id' => 'gemini-3.5-flash',
            'api_key_env' => 'GEMINI_API_KEY',
            'priority' => 1,
            'is_enabled' => true,
            'max_tokens' => 512,
            'timeout_seconds' => 30,
        ]);

        Http::fake(['*' => Http::response(['error' => ['message' => 'quota habis']], 429)]);

        $report = $this->createReportWithFile();

        app(AnalysisPipeline::class)->process($report);

        $report->refresh();

        // Semua provider gagal → status failed, tapi chart & statistik tetap ada
        $this->assertEquals('failed', $report->status->value);
        $this->assertEquals('fallback', $report->ai_provider_used);
        $this->assertNull($report->ai_narrative);
        $this->assertNotEmpty($report->chart_paths);
        $this->assertNotEmpty($report->summary_stats);
        $this->assertStringContainsString('provider AI gagal', $report->error_message);

        // Log kegagalan mencatat rate_limit dan menambah counter error
        $log = AIUsageLog::where('report_id', $report->id)->first();
        $this->assertNotNull($log);
        $this->assertFalse($log->is_success);
        $this->assertEquals(1, AIProvider::where('slug', 'gemini')->value('total_errors'));
    }

    public function test_pipeline_gagal_bila_file_sumber_hilang(): void
    {
        $report = Report::create([
            'user_id' => null,
            'visitor_token' => 'test-token',
            'title' => 'File Hilang',
            'original_filename' => 'hilang.csv',
            'original_path' => 'uploads/1/hilang.csv',
            'status' => ReportStatus::Pending,
            'dataset_type' => 'umum',
            'tone' => 'formal',
            'total_rows' => 0,
            'clean_rows' => 0,
        ]);

        $this->expectExceptionMessage('File sumber tidak ditemukan');

        app(AnalysisPipeline::class)->process($report);
    }

    public function test_pipeline_menolak_file_terlalu_besar(): void
    {
        // Tulis file > 15 MB sungguhan ke disk fake (disk 'local' di fake
        // memakai filesystem asli di storage/framework/testing).
        $path = Storage::disk('local')->path('uploads/1/besar.csv');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }
        $handle = fopen($path, 'wb');
        fseek($handle, 16 * 1024 * 1024 - 1);
        fwrite($handle, '0');
        fclose($handle);

        $report = Report::create([
            'user_id' => null,
            'visitor_token' => 'test-token',
            'title' => 'Terlalu Besar',
            'original_filename' => 'besar.csv',
            'original_path' => 'uploads/1/besar.csv',
            'status' => ReportStatus::Pending,
            'dataset_type' => 'umum',
            'tone' => 'formal',
            'total_rows' => 0,
            'clean_rows' => 0,
        ]);

        $this->expectExceptionMessage('File terlalu besar');

        app(AnalysisPipeline::class)->process($report);
    }

    public function test_job_memproses_report_dan_menandai_gagal_saat_error(): void
    {
        // Report tanpa file → job harus menandai failed, bukan melempar exception
        $report = Report::create([
            'user_id' => null,
            'visitor_token' => 'test-token',
            'title' => 'Tanpa File',
            'original_filename' => 'x.csv',
            'original_path' => 'uploads/1/tidak-ada.csv',
            'status' => ReportStatus::Pending,
            'dataset_type' => 'umum',
            'tone' => 'formal',
            'total_rows' => 0,
            'clean_rows' => 0,
        ]);

        (new \App\Jobs\ProcessDataJob($report->id))->handle(app(AnalysisPipeline::class));

        $report->refresh();
        $this->assertEquals('failed', $report->status->value);
        $this->assertStringContainsString('tidak ditemukan', $report->error_message);
    }
}
