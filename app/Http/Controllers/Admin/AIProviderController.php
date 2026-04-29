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
                'slug' => $provider->slug,
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
            'modelCatalog' => config('ai-models', []),
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

    /**
     * Update provider settings (API key env, model, timeout, tokens)
     * API key disimpan sebagai env var name di DB, nilai sebenarnya tetap di .env
     * Untuk keamanan, kita juga bisa update langsung nilai di .env file
     */
    public function updateProvider(Request $request, AIProvider $provider)
    {
        $validated = $request->validate([
            'api_key_env' => 'sometimes|string|max:100',
            'api_key_value' => 'nullable|string|max:500',
            'model_id' => 'sometimes|string|max:200',
            'max_tokens' => 'sometimes|integer|min:100|max:32000',
            'timeout_seconds' => 'sometimes|integer|min:5|max:300',
            'priority' => 'sometimes|integer|min:1|max:20',
        ]);

        // Update model fields (bukan API key value)
        $updateData = collect($validated)
            ->except('api_key_value')
            ->filter(fn ($v) => $v !== null)
            ->toArray();

        if (!empty($updateData)) {
            $provider->update($updateData);
        }

        // Update API key value di .env file jika diberikan
        if (!empty($validated['api_key_value'])) {
            $envKey = $validated['api_key_env'] ?? $provider->api_key_env;
            $this->updateEnvValue($envKey, $validated['api_key_value']);
        }

        return response()->json([
            'success' => true,
            'message' => "Provider {$provider->name} berhasil diperbarui.",
        ]);
    }

    /**
     * Update nilai di .env file
     * Hanya bisa diakses admin — aman karena dilindungi middleware
     */
    private function updateEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            return;
        }

        $envContent = file_get_contents($envPath);

        // Escape value jika mengandung spasi atau karakter khusus
        $escapedValue = str_contains($value, ' ') ? "\"{$value}\"" : $value;

        // Check if key exists
        if (preg_match("/^{$key}=.*/m", $envContent)) {
            // Replace existing
            $envContent = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$escapedValue}",
                $envContent
            );
        } else {
            // Append new key
            $envContent .= "\n{$key}={$escapedValue}";
        }

        file_put_contents($envPath, $envContent);
    }
}

