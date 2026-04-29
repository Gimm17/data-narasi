<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_upload_page(): void
    {
        $response = $this->get(route('upload.create'));
        // Inertia pages return 200 (HTML) or 302 (redirect to home if misconfigured)
        $response->assertSuccessful();
    }

    public function test_guest_can_upload_csv_file(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->createWithContent(
            'test_data.csv',
            "nama,nilai,kota\nAndi,85,Jakarta\nBudi,90,Surabaya"
        );

        $response = $this->post(route('upload.store'), [
            'file' => $file,
            'analysis_type' => 'umum',
            'tone' => 'formal',
        ]);

        // Should redirect to processing page
        $response->assertRedirect();
        $this->assertDatabaseCount('reports', 1);

        $report = Report::first();
        $this->assertNotNull($report->visitor_token);
        $this->assertNull($report->user_id);
        $this->assertEquals('pending', $report->status->value);
        $this->assertEquals('umum', $report->dataset_type);
        $this->assertEquals('formal', $report->tone);
    }

    public function test_upload_requires_file(): void
    {
        $response = $this->from(route('upload.create'))
            ->post(route('upload.store'), [
                'analysis_type' => 'umum',
                'tone' => 'formal',
            ]);

        $response->assertRedirect(route('upload.create'));
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_upload_requires_analysis_type(): void
    {
        $file = UploadedFile::fake()->createWithContent('test.csv', "a,b\n1,2");

        $response = $this->from(route('upload.create'))
            ->post(route('upload.store'), [
                'file' => $file,
                'tone' => 'formal',
            ]);

        $response->assertRedirect(route('upload.create'));
        $this->assertDatabaseCount('reports', 0);
    }
}
