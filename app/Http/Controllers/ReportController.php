<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Controller untuk mengelola dan menampilkan reports
 */
class ReportController extends Controller
{
    /**
     * Tampilkan semua report milik user
     */
    public function index()
    {
        $reports = Auth::user()
            ->reports()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Report/Index', [
            'reports' => $reports,
        ]);
    }

    /**
     * Tampilkan detail report
     */
    public function show(Report $report)
    {
        // Policy check: user hanya bisa akses report miliknya sendiri
        $this->authorizeReport($report);

        // Load relationships
        $report->load(['columns', 'outputs', 'usageLogs.provider']);

        return Inertia::render('Report/Show', [
            'report' => $report,
        ]);
    }

    /**
     * Tampilkan halaman processing/loading
     */
    public function processing(Report $report)
    {
        // Policy check
        $this->authorizeReport($report);

        return Inertia::render('Report/Processing', [
            'report' => [
                'id' => $report->id,
                'title' => $report->title,
                'original_filename' => $report->original_filename,
                'status' => $report->status->value,
                'created_at' => $report->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Return status report untuk polling (JSON)
     */
    public function status(Report $report)
    {
        // Policy check
        $this->authorizeReport($report);

        return response()->json([
            'status' => $report->status->value,
            'status_label' => $report->status->label(),
            'is_processing' => $report->isProcessing(),
            'is_done' => $report->isDone(),
            'is_failed' => $report->isFailed(),
            'ai_provider_used' => $report->ai_provider_used,
            'error_message' => $report->error_message,
            'clean_rows' => $report->clean_rows,
            'total_rows' => $report->total_rows,
            'updated_at' => $report->updated_at->toISOString(),
        ]);
    }

    /**
     * Retry processing report yang gagal (tanpa upload ulang)
     */
    public function retry(Report $report)
    {
        $this->authorizeReport($report);

        // Hanya bisa retry report yang failed
        if (!$report->isFailed()) {
            return back()->with('error', 'Hanya report yang gagal bisa di-retry.');
        }

        // Reset status dan error
        $report->update([
            'status' => \App\Enums\ReportStatus::Pending,
            'error_message' => null,
            'ai_narrative' => null,
            'ai_provider_used' => null,
            'processing_time_ms' => null,
        ]);

        // Dispatch ulang job
        \App\Jobs\ProcessDataJob::dispatch($report->id);

        return redirect()->route('reports.processing', $report->id)
            ->with('success', 'Report sedang diproses ulang.');
    }

    /**
     * Hapus report dan file terkait
     */
    public function destroy(Report $report)
    {
        // Policy check
        $this->authorizeReport($report);

        // Hapus file dari storage
        if ($report->original_path && Storage::exists($report->original_path)) {
            Storage::delete($report->original_path);
        }

        if ($report->clean_path && Storage::exists($report->clean_path)) {
            Storage::delete($report->clean_path);
        }

        // Hapus chart files jika ada
        if (!empty($report->chart_paths)) {
            foreach ($report->chart_paths as $chartPath) {
                if (Storage::exists($chartPath)) {
                    Storage::delete($chartPath);
                }
            }
        }

        // Hapus output files
        foreach ($report->outputs as $output) {
            if ($output->file_path && Storage::exists($output->file_path)) {
                Storage::delete($output->file_path);
            }
        }

        // Hapus record dari database (cascade delete akan handle relationships)
        $report->delete();

        return redirect()->route('reports.index')
            ->with('success', 'Report berhasil dihapus.');
    }

    /**
     * Helper method untuk policy check
     * User hanya bisa akses report miliknya sendiri
     */
    private function authorizeReport(Report $report): void
    {
        if ($report->user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki akses ke report ini.');
        }
    }
}
