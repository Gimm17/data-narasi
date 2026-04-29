<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportRetryTest extends TestCase
{
    use RefreshDatabase;

    private function createFailedReport(string $visitorToken = 'test-visitor'): Report
    {
        return Report::create([
            'user_id' => null,
            'visitor_token' => $visitorToken,
            'title' => 'Failed Report',
            'original_filename' => 'test.csv',
            'original_path' => 'uploads/test.csv',
            'status' => 'failed',
            'dataset_type' => 'umum',
            'tone' => 'formal',
            'error_message' => 'AI provider gagal',
        ]);
    }

    public function test_visitor_can_retry_own_failed_report(): void
    {
        Queue::fake();
        $report = $this->createFailedReport('my-token');

        $response = $this->withCookie('dn_visitor', 'my-token')
            ->post(route('reports.retry', $report->id));

        $response->assertRedirect(route('reports.processing', $report->id));

        $report->refresh();
        $this->assertEquals('pending', $report->status->value);
        $this->assertNull($report->error_message);
    }

    public function test_visitor_cannot_retry_others_report(): void
    {
        $report = $this->createFailedReport('other-token');

        $response = $this->withCookie('dn_visitor', 'my-token')
            ->post(route('reports.retry', $report->id));

        $response->assertStatus(403);
    }

    public function test_cannot_retry_non_failed_report(): void
    {
        $report = Report::create([
            'visitor_token' => 'my-token',
            'title' => 'Done Report',
            'original_filename' => 'test.csv',
            'original_path' => 'uploads/test.csv',
            'status' => 'done',
            'dataset_type' => 'umum',
            'tone' => 'formal',
        ]);

        $response = $this->withCookie('dn_visitor', 'my-token')
            ->post(route('reports.retry', $report->id));

        $response->assertRedirect();
        $report->refresh();
        $this->assertEquals('done', $report->status->value);
    }
}
