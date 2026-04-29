<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Controller untuk mengelola dan menampilkan reports
 * Mendukung anonymous access via visitor_token cookie
 */
class ReportController extends Controller
{
    /**
     * Tampilkan semua report milik visitor/user
     */
    public function index(Request $request)
    {
        $query = $this->getReportsQuery($request);

        $reports = $query->orderBy('created_at', 'desc')->paginate(10);

        return Inertia::render('Report/Index', [
            'reports' => $reports,
        ]);
    }

    /**
     * Tampilkan detail report
     */
    public function show(Request $request, Report $report)
    {
        $this->authorizeReport($request, $report);

        // Load relationships
        $report->load(['columns', 'outputs', 'usageLogs.provider']);

        return Inertia::render('Report/Show', [
            'report' => $report,
        ]);
    }

    /**
     * Tampilkan halaman processing/loading
     */
    public function processing(Request $request, Report $report)
    {
        $this->authorizeReport($request, $report);

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
    public function status(Request $request, Report $report)
    {
        $this->authorizeReport($request, $report);

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
    public function retry(Request $request, Report $report)
    {
        $this->authorizeReport($request, $report);

        if (!$report->isFailed()) {
            return back()->with('error', 'Hanya report yang gagal bisa di-retry.');
        }

        $report->update([
            'status' => \App\Enums\ReportStatus::Pending,
            'error_message' => null,
            'ai_narrative' => null,
            'ai_provider_used' => null,
            'processing_time_ms' => null,
        ]);

        \App\Jobs\ProcessDataJob::dispatch($report->id);

        return redirect()->route('reports.processing', $report->id)
            ->with('success', 'Report sedang diproses ulang.');
    }

    /**
     * Hapus report dan file terkait
     */
    public function destroy(Request $request, Report $report)
    {
        $this->authorizeReport($request, $report);

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

        $report->delete();

        return redirect()->route('reports.index')
            ->with('success', 'Report berhasil dihapus.');
    }

    /**
     * Helper: Ambil query reports berdasarkan visitor_token atau user_id
     */
    private function getReportsQuery(Request $request)
    {
        $user = Auth::user();
        $visitorToken = $request->cookie('dn_visitor');

        if ($user) {
            // Admin/logged-in user: tampilkan reports milik user
            return $user->reports();
        }

        if ($visitorToken) {
            // Anonymous user: tampilkan reports dengan visitor_token yang cocok
            return Report::where('visitor_token', $visitorToken);
        }

        // Tidak ada token, tidak ada report
        return Report::where('id', -1); // empty query
    }

    /**
     * Helper: Cek apakah visitor/user berhak akses report ini
     */
    private function authorizeReport(Request $request, Report $report): void
    {
        $user = Auth::user();
        $visitorToken = $request->cookie('dn_visitor');

        // Admin bisa akses semua
        if ($user && $user->is_admin) {
            return;
        }

        // User yang login bisa akses report miliknya
        if ($user && $report->user_id === $user->id) {
            return;
        }

        // Anonymous visitor bisa akses report dengan token yang cocok
        if ($visitorToken && $report->visitor_token === $visitorToken) {
            return;
        }

        abort(403, 'Anda tidak memiliki akses ke report ini.');
    }
}
