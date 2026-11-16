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

        // Reports per week (8 minggu terakhir).
        // Dihitung di PHP, bukan YEARWEEK() — fungsi itu tidak ada di SQLite dan
        // perilakunya beda antar versi MySQL/MariaDB. Jumlah barisnya kecil
        // (maks. 8 minggu), jadi biayanya tidak berarti.
        $weeklyCounts = Report::query()
            ->where('created_at', '>=', now()->subWeeks(8)->startOfWeek())
            ->pluck('created_at')
            ->groupBy(fn ($createdAt) => $createdAt->copy()->startOfWeek()->toDateString())
            ->sortKeys()
            ->map(fn ($group, $weekStart) => [
                'label' => \Carbon\Carbon::parse($weekStart)->translatedFormat('d M'),
                'count' => $group->count(),
            ])
            ->values();

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
