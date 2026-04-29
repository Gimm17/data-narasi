<?php

namespace App\Http\Controllers;

use App\Models\AIProvider;
use App\Models\AIUsageLog;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Dashboard Analytics (Admin only)
 */
class DashboardController extends Controller
{
    public function index()
    {
        $totalReports = Report::count();
        $doneReports = Report::where('status', 'done')->count();
        $successRate = $totalReports > 0 ? round(($doneReports / $totalReports) * 100, 1) : 0;
        $avgProcessingTime = Report::where('status', 'done')->avg('processing_time_ms') ?? 0;
        $totalRows = Report::sum('total_rows');

        // Reports per week (last 8 weeks)
        $weeklyCounts = Report::selectRaw("
                YEARWEEK(created_at, 1) as week_key,
                MIN(DATE(created_at)) as week_start,
                COUNT(*) as count
            ")
            ->where('created_at', '>=', now()->subWeeks(8))
            ->groupBy('week_key')
            ->orderBy('week_key')
            ->get()
            ->map(fn($r) => [
                'label' => \Carbon\Carbon::parse($r->week_start)->format('d M'),
                'count' => $r->count,
            ]);

        // AI provider usage distribution
        $providerUsage = AIUsageLog::where('is_success', true)
            ->selectRaw('ai_provider_id, COUNT(*) as count')
            ->groupBy('ai_provider_id')
            ->get()
            ->map(function ($log) {
                $provider = AIProvider::find($log->ai_provider_id);
                return [
                    'name' => $provider?->name ?? 'Unknown',
                    'count' => $log->count,
                ];
            });

        // Analysis type distribution
        $analysisTypes = Report::where('status', 'done')
            ->selectRaw('dataset_type, COUNT(*) as count')
            ->groupBy('dataset_type')
            ->orderByDesc('count')
            ->get();

        // Recent reports
        $recentReports = Report::latest()
            ->take(5)
            ->get(['id', 'title', 'original_filename', 'status', 'dataset_type', 'total_rows', 'created_at', 'ai_provider_used']);

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_reports' => $totalReports,
                'success_rate' => $successRate,
                'avg_processing_time' => round($avgProcessingTime),
                'total_rows' => $totalRows,
            ],
            'weeklyCounts' => $weeklyCounts,
            'providerUsage' => $providerUsage,
            'analysisTypes' => $analysisTypes,
            'recentReports' => $recentReports,
        ]);
    }
}
