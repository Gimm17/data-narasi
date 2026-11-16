<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AIProvider;
use App\Models\AIUsageLog;
use App\Services\Analysis\AiClient;
use App\Services\Analysis\ApiKeyChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
            'total_cost' => round((float) $todayLogs->sum('cost'), 6),
            'avg_response_time' => $todayLogs->where('is_success', true)->avg('response_time_ms'),
            'fallback_count' => 0,
        ];

        // Calculate stats per provider
        $providersWithStats = $providers->map(function ($provider) use ($today) {
            $logs = $provider->aiUsageLogs()->whereDate('created_at', $today)->get();

            return [
                'id' => $provider->id,
                'name' => $provider->name,
                'slug' => $provider->slug,
                'model_id' => $provider->model_id,
                'reasoning_effort' => $provider->reasoning_effort,
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
                'today_cost' => round((float) $logs->sum('cost'), 6),
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
            'reasoningEfforts' => AiClient::reasoningEfforts(),
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
            'reasoning_effort' => ['sometimes', 'nullable', 'string', Rule::in(AiClient::reasoningEfforts())],
            'max_tokens' => 'sometimes|integer|min:100|max:32000',
            'timeout_seconds' => 'sometimes|integer|min:5|max:300',
            'priority' => 'sometimes|integer|min:1|max:20',
        ]);

        // Update model fields (bukan API key value)
        $updateData = collect($validated)
            ->except('api_key_value')
            ->filter(fn ($v) => $v !== null)
            ->toArray();

        // reasoning_effort boleh dikosongkan (null) untuk kembali ke default provider
        if (array_key_exists('reasoning_effort', $validated) && $validated['reasoning_effort'] === null) {
            $updateData['reasoning_effort'] = null;
        }

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
     * Ambil daftar model terbaru langsung dari provider (GET /models).
     *
     * Dipakai panel admin agar model bisa dicek dan diganti tanpa
     * memperbarui katalog manual.
     */
    public function models(AIProvider $provider, ApiKeyChecker $checker)
    {
        $apiKey = trim((string) (config('ai.keys.' . $provider->slug)
            ?? env($provider->api_key_env)));

        if ($apiKey === '') {
            return response()->json([
                'success' => false,
                'error' => "API key belum diset. Tambahkan {$provider->api_key_env} di file .env",
                'models' => [],
            ], 422);
        }

        try {
            $models = $checker->listModels((string) $provider->slug, $apiKey);

            return response()->json([
                'success' => true,
                'provider' => $provider->slug,
                'current_model' => $provider->model_id,
                'count' => count($models),
                'models' => $models,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Gagal mengambil model {$provider->name}: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'models' => [],
            ], 502);
        }
    }

    /**
     * Bandingkan hasil narasi untuk beberapa tingkat reasoning_effort.
     *
     * Mengirim prompt identik ke model yang sama dengan effort berbeda,
     * lalu mengembalikan hasil per tingkat agar admin dapat membandingkan
     * panjang, waktu, token berpikir, dan isi narasinya.
     */
    public function compareReasoning(Request $request, AIProvider $provider, AiClient $client)
    {
        $validated = $request->validate([
            'prompt' => 'required|string|min:10|max:4000',
            'efforts' => 'required|array|min:2|max:5',
            'efforts.*' => ['string', Rule::in(AiClient::reasoningEfforts())],
        ]);

        // Membandingkan 5 tingkat bisa memakan 5 × timeout AI (ratusan detik).
        // Tanpa ini, request diputus di tengah jalan oleh max_execution_time
        // server dan admin hanya melihat halaman error.
        @set_time_limit(600);

        $apiKey = trim((string) (config('ai.keys.' . $provider->slug)
            ?? env($provider->api_key_env)));

        if ($apiKey === '') {
            return response()->json([
                'success' => false,
                'error' => "API key belum diset untuk {$provider->name}.",
            ], 422);
        }

        $maxTokens = (int) ($provider->max_tokens ?: config('ai.max_tokens', 2048));
        $results = [];

        foreach ($validated['efforts'] as $effort) {
            $startedAt = microtime(true);

            try {
                $response = $client->generate(
                    $validated['prompt'],
                    'Anda analis data senior berbahasa Indonesia. Jawab HANYA dalam Bahasa Indonesia. Setiap klaim didukung angka.',
                    $maxTokens,
                    [[
                        'slug' => $provider->slug,
                        'model_id' => $provider->model_id,
                        'reasoning_effort' => $effort,
                    ]]
                );
            } catch (\Throwable $e) {
                // Satu tingkat gagal tidak boleh menggagalkan seluruh perbandingan
                $results[] = [
                    'effort' => $effort,
                    'success' => false,
                    'narrative' => '',
                    'words' => 0,
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0,
                    'cost_usd' => 0,
                    'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                ];

                continue;
            }

            $results[] = [
                'effort' => $effort,
                'success' => $response['success'],
                'narrative' => $response['narrative'],
                'words' => str_word_count($response['narrative']),
                'prompt_tokens' => $response['prompt_tokens'] ?? 0,
                'completion_tokens' => $response['completion_tokens'] ?? 0,
                'total_tokens' => $response['total_tokens'] ?? 0,
                'cost_usd' => $response['cost_usd'] ?? 0,
                'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'status' => $response['logs'][0]['status'] ?? 'error',
                'error_message' => $response['logs'][0]['error_message'] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'provider' => $provider->slug,
            'model' => $provider->model_id,
            'results' => $results,
        ]);
    }

    /**
     * Update nilai di .env file
     * Hanya bisa diakses admin — aman karena dilindungi middleware
     */
    /**
     * Update nilai di .env file
     * Hanya bisa diakses admin — aman karena dilindungi middleware
     *
     * PENTING: setelah .env berubah, config yang sedang ter-cache masih
     * menyimpan nilai lama. Tanpa menghapus cache, perubahan tidak berlaku
     * sampai admin menjalankan `php artisan optimize:clear` manual.
     */
    private function updateEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            return;
        }

        $envContent = (string) file_get_contents($envPath);

        // Escape nilai agar '/' dan '\' tidak ditafsirkan sebagai pembatas regex
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

        $escapedValue = preg_match('/[\s"\'#]/', $value) ? '"' . addslashes($value) . '"' : $value;

        if (preg_match($pattern, $envContent)) {
            $envContent = (string) preg_replace($pattern, $key . '=' . $escapedValue, $envContent);
        } else {
            $envContent .= "\n{$key}={$escapedValue}\n";
        }

        file_put_contents($envPath, $envContent, LOCK_EX);

        // Terapkan segera: buang cache config lama lalu bangun ulang
        try {
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('config:cache');
        } catch (\Throwable $e) {
            // Shared hosting terkadang melarang tulis ke bootstrap/cache.
            // Perubahan tetap tersimpan di .env untuk diterapkan manual.
            \Illuminate\Support\Facades\Log::warning('Gagal membangun ulang config cache: ' . $e->getMessage());
        }
    }

    /**
     * Check API key health: validitas, balance, rate limits.
     * Dijalankan langsung di PHP (dulu proxy ke Python service).
     */
    public function checkApiKey(AIProvider $provider, ApiKeyChecker $checker)
    {
        try {
            // Baca API key dari config (aman setelah config:cache), fallback ke env()
            $apiKey = trim((string) (config('ai.keys.' . $provider->slug)
                ?? env($provider->api_key_env)));

            if ($apiKey === '') {
                return response()->json([
                    'valid' => false,
                    'error' => "API key belum diset. Tambahkan {$provider->api_key_env} di file .env",
                    'checked_at' => now()->toIso8601String(),
                ]);
            }

            return response()->json(
                $checker->check((string) $provider->slug, $apiKey, $provider->model_id)
            );
        } catch (\Exception $e) {
            Log::error("API key check failed for {$provider->name}: {$e->getMessage()}");

            return response()->json([
                'valid' => false,
                'error' => 'Pemeriksaan gagal: ' . $e->getMessage(),
                'checked_at' => now()->toIso8601String(),
            ]);
        }
    }
}

