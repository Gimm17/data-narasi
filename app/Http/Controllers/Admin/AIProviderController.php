<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AIProvider;
use App\Models\AIUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

/**
 * Controller untuk mengelola AI Providers (Admin only)
 */
class AIProviderController extends Controller
{
    /**
     * Tampilkan semua provider + stats hari ini
     */
    public function index()
    {
        $providers = AIProvider::orderBy('priority')->get();

        // Hitung stats hari ini
        $today = Carbon::today();
        $todayLogs = AIUsageLog::whereDate('created_at', $today)->get();

        $todayStats = [
            'total_requests' => $todayLogs->count(),
            'successful_requests' => $todayLogs->where('is_success', true)->count(),
            'failed_requests' => $todayLogs->where('is_success', false)->count(),
            'total_tokens' => $todayLogs->sum('tokens_used'),
            'avg_response_time' => $todayLogs->where('is_success', true)->avg('response_time_ms'),
            'fallback_count' => 0, // No longer tracked per DB schema
        ];

        // Calculate stats per provider
        $providersWithStats = $providers->map(function ($provider) use ($today) {
            $logs = $provider->aiUsageLogs()->whereDate('created_at', $today)->get();

            return [
                'id' => $provider->id,
                'name' => $provider->name,
                'model_id' => $provider->model_id,
                'is_enabled' => $provider->is_enabled,
                'priority' => $provider->priority,
                'api_key_env' => $provider->api_key_env,
                'max_tokens' => $provider->max_tokens,
                'timeout_seconds' => $provider->timeout_seconds,
                'total_calls' => $provider->total_calls,
                'total_errors' => $provider->total_errors,
                'last_used_at' => $provider->last_used_at?->toISOString(),
                'is_ready' => $provider->isReady(),
                'today_calls' => $logs->count(),
                'today_success' => $logs->where('is_success', true)->count(),
                'today_errors' => $logs->where('is_success', false)->count(),
                'today_tokens' => $logs->sum('tokens_used'),
                'today_avg_response' => $logs->where('is_success', true)->avg('response_time_ms'),
            ];
        });

        // Get recent fallback logs (5 terakhir)
        $recentFallbacks = AIUsageLog::with('provider')
            ->where('is_success', false)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'report_id' => $log->report_id,
                    'provider_name' => $log->provider->name ?? 'N/A',
                    'is_success' => $log->is_success,
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at->toISOString(),
                ];
            });

        return Inertia::render('Admin/AIProviders', [
            'providers' => $providersWithStats,
            'todayStats' => $todayStats,
            'recentFallbacks' => $recentFallbacks,
        ]);
    }

    /**
     * Toggle is_enabled provider
     */
    public function toggle(AIProvider $provider)
    {
        $provider->update([
            'is_enabled' => !$provider->is_enabled,
        ]);

        return response()->json([
            'success' => true,
            'is_enabled' => $provider->is_enabled,
            'message' => $provider->is_enabled
                ? "Provider {$provider->name} berhasil diaktifkan."
                : "Provider {$provider->name} berhasil dinonaktifkan.",
        ]);
    }

    /**
     * Update priority providers (bulk reorder)
     * Expected payload: { providers: [{ id: 1, priority: 1 }, { id: 2, priority: 2 }, ...] }
     */
    public function updatePriority(Request $request)
    {
        $validated = $request->validate([
            'providers' => 'required|array',
            'providers.*.id' => 'required|integer|exists:ai_providers,id',
            'providers.*.priority' => 'required|integer|min:1',
        ]);

        foreach ($validated['providers'] as $providerData) {
            AIProvider::where('id', $providerData['id'])
                ->update(['priority' => $providerData['priority']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Urutan provider berhasil diperbarui.',
        ]);
    }

    /**
     * Reset counters provider (optional utility)
     */
    public function resetCounters(AIProvider $provider)
    {
        $provider->update([
            'total_calls' => 0,
            'total_errors' => 0,
            'last_used_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Counter {$provider->name} berhasil direset.",
        ]);
    }
}
