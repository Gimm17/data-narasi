<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Services\PythonServiceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function createReport(): Report
    {
        return Report::create([
            'user_id' => null,
            'visitor_token' => 'test-token-123',
            'title' => 'Test Report',
            'original_filename' => 'test.csv',
            'original_path' => 'uploads/test.csv',
            'status' => 'processing',
            'dataset_type' => 'umum',
            'tone' => 'formal',
        ]);
    }

    public function test_valid_callback_updates_report_to_done(): void
    {
        $report = $this->createReport();
        $client = new PythonServiceClient();

        $payload = json_encode([
            'status' => 'success',
            'ai_narrative' => 'Test narasi dari AI',
            'summary_stats' => ['basic_stats' => ['total_rows' => 100]],
            'cleaning_log' => [],
            'chart_paths' => [],
            'clean_path' => null,
            'clean_rows' => 95,
            'ai_provider_used' => 'gemini',
            'processing_time_ms' => 5000,
            'ai_usage_logs' => [],
        ]);

        $signature = $client->generateHmacSignature($payload);

        $response = $this->withHeaders([
            'X-Callback-Signature' => $signature,
            'Content-Type' => 'application/json',
        ])->postJson(
            route('api.reports.callback', $report->id),
            json_decode($payload, true)
        );

        $response->assertStatus(200);

        $report->refresh();
        $this->assertEquals('done', $report->status->value);
        $this->assertEquals('Test narasi dari AI', $report->ai_narrative);
    }

    public function test_invalid_hmac_returns_401(): void
    {
        $report = $this->createReport();

        $response = $this->withHeaders([
            'X-Callback-Signature' => 'invalid-signature',
            'X-Callback-Secret' => 'wrong-secret',
        ])->postJson(
            route('api.reports.callback', $report->id),
            ['status' => 'success']
        );

        $response->assertStatus(401);
    }

    public function test_failed_callback_marks_report_as_failed(): void
    {
        $report = $this->createReport();
        $client = new PythonServiceClient();

        $payload = json_encode([
            'status' => 'failed',
            'ai_narrative' => null,
            'summary_stats' => [],
            'cleaning_log' => [],
            'chart_paths' => [],
            'clean_path' => null,
            'clean_rows' => 0,
            'ai_provider_used' => null,
            'processing_time_ms' => 0,
            'ai_usage_logs' => [],
            'error_message' => 'AI provider semua gagal',
        ]);

        $signature = $client->generateHmacSignature($payload);

        $response = $this->withHeaders([
            'X-Callback-Signature' => $signature,
        ])->postJson(
            route('api.reports.callback', $report->id),
            json_decode($payload, true)
        );

        $response->assertStatus(200);

        $report->refresh();
        $this->assertEquals('failed', $report->status->value);
    }
}
