<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ApiKeyChecker — port PHP dari python-service/health_checker.py
 *
 * Dipanggil dari admin panel untuk memastikan API key masih valid,
 * sekaligus membaca sisa saldo / rate limit bila provider mendukung.
 */
class ApiKeyChecker
{
    private const TIMEOUT = 10;

    /**
     * Cek satu provider.
     *
     * @return array{
     *     valid: bool,
     *     balance: float|null,
     *     balance_detail: array<string, mixed>|null,
     *     rate_limit: array<string, mixed>|null,
     *     tier: string|null,
     *     models_count: int|null,
     *     error: string|null,
     *     response_ms: int,
     *     checked_at: string
     * }
     */
    public function check(string $slug, string $apiKey, ?string $modelId = null): array
    {
        $startedAt = microtime(true);

        $result = [
            'valid' => false,
            'balance' => null,
            'balance_detail' => null,
            'rate_limit' => null,
            'tier' => null,
            'models_count' => null,
            'error' => null,
            'response_ms' => null,
            'checked_at' => now()->toIso8601String(),
        ];

        try {
            $result = match ($slug) {
                'gemini' => $this->checkGemini($apiKey, $result),
                'kimi' => $this->checkKimi($apiKey, $result),
                'minimax' => $this->checkMiniMax($apiKey, $result),
                'claude' => $this->checkClaude($apiKey, $modelId, $result),
                'glm' => $this->checkModelsEndpoint('https://api.z.ai/api/coding/paas/v4', $apiKey, $result, null),
                'nvidia' => $this->checkModelsEndpoint('https://integrate.api.nvidia.com/v1', $apiKey, $result, 'free'),
                'openrouter' => $this->checkModelsEndpoint('https://openrouter.ai/api/v1', $apiKey, $result, 'free'),
                'tokenrouter' => $this->checkTokenRouter($apiKey, $result),
                default => throw new \RuntimeException('Provider tidak dikenal: ' . $slug),
            };
        } catch (Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 300);
            // Jangan pernah bocorkan API key ke response / log
            if (strlen($apiKey) > 8) {
                $message = str_replace($apiKey, '[REDACTED]', $message);
            }

            $result['valid'] = false;
            $result['error'] = $message;

            Log::error("Pemeriksaan API key gagal untuk {$slug}: {$message}");
        }

        $result['response_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $result;
    }

    /**
     * Endpoint standar OpenAI-compatible: GET /models.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function checkModelsEndpoint(string $baseUrl, string $apiKey, array $result, ?string $tier): array
    {
        $response = Http::timeout(self::TIMEOUT)
            ->withToken($apiKey)
            ->get($baseUrl . '/models');

        if ($response->successful()) {
            $result['valid'] = true;
            $result['models_count'] = count($response->json('data') ?? []);
            $result['tier'] = $tier;

            return $result;
        }

        $result['error'] = $this->describeStatus($response->status(), $response->json('error.message'));

        return $result;
    }

    /**
     * Gemini: GET /v1beta/models.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function checkGemini(string $apiKey, array $result): array
    {
        $response = Http::timeout(self::TIMEOUT)
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->get('https://generativelanguage.googleapis.com/v1beta/models');

        if ($response->successful()) {
            $result['valid'] = true;
            $result['models_count'] = count($response->json('models') ?? []);
            $result['tier'] = 'free';

            return $result;
        }

        $result['error'] = $this->describeStatus($response->status(), $response->json('error.message'));

        return $result;
    }

    /**
     * Kimi: /models + saldo dari /users/me/balance.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function checkKimi(string $apiKey, array $result): array
    {
        $base = 'https://api.moonshot.cn/v1';

        $result = $this->checkModelsEndpoint($base, $apiKey, $result, null);
        if (!$result['valid']) {
            return $result;
        }

        try {
            $balanceResponse = Http::timeout(self::TIMEOUT)->withToken($apiKey)->get($base . '/users/me/balance');

            if ($balanceResponse->successful()) {
                $data = $balanceResponse->json('data') ?? [];
                $result['balance'] = $data['available_balance'] ?? null;
                $result['balance_detail'] = [
                    'available' => $data['available_balance'] ?? null,
                    'voucher' => $data['voucher_balance'] ?? null,
                    'cash' => $data['cash_balance'] ?? null,
                ];
                $result['tier'] = ((float) ($data['cash_balance'] ?? 0)) > 0 ? 'premium' : 'free';
            }
        } catch (Throwable $e) {
            Log::warning('Pemeriksaan saldo Kimi gagal: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * MiniMax: /models + tier dari prefix key + sisa Token Plan.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function checkMiniMax(string $apiKey, array $result): array
    {
        $result = $this->checkModelsEndpoint('https://api.minimax.io/v1', $apiKey, $result, null);
        if (!$result['valid']) {
            return $result;
        }

        $result['tier'] = match (true) {
            str_starts_with($apiKey, 'sk-cp-') => 'coding_plan',
            str_starts_with($apiKey, 'sk-api-') => 'standard',
            default => 'unknown',
        };

        if (str_starts_with($apiKey, 'sk-cp-')) {
            try {
                $plan = Http::timeout(self::TIMEOUT)
                    ->withToken($apiKey)
                    ->get('https://www.minimax.io/v1/token_plan/remains');

                if ($plan->successful()) {
                    $result['balance_detail'] = $plan->json();
                }
            } catch (Throwable $e) {
                Log::warning('Pemeriksaan Token Plan MiniMax gagal: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Claude: panggilan /messages super kecil + rate-limit headers.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function checkClaude(string $apiKey, ?string $modelId, array $result): array
    {
        $response = Http::timeout(self::TIMEOUT)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $modelId ?: 'claude-3-haiku-20240307',
                'max_tokens' => 1,
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ]);

        if ($response->successful()) {
            $result['valid'] = true;

            $rateLimit = [];
            foreach ([
                'requests_limit' => 'anthropic-ratelimit-requests-limit',
                'requests_remaining' => 'anthropic-ratelimit-requests-remaining',
                'input_tokens_limit' => 'anthropic-ratelimit-input-tokens-limit',
                'input_tokens_remaining' => 'anthropic-ratelimit-input-tokens-remaining',
                'output_tokens_limit' => 'anthropic-ratelimit-output-tokens-limit',
                'output_tokens_remaining' => 'anthropic-ratelimit-output-tokens-remaining',
            ] as $key => $header) {
                $value = $response->header($header);
                if ($value !== '' && $value !== null) {
                    $rateLimit[$key] = is_numeric($value) ? (int) $value : $value;
                }
            }

            if ($rateLimit !== []) {
                $result['rate_limit'] = $rateLimit;
            }

            return $result;
        }

        if ($response->status() === 429) {
            // Key valid, hanya kena limit sementara
            $result['valid'] = true;
            $result['error'] = 'Rate limited — coba lagi nanti';
            $result['rate_limit'] = ['status' => 'rate_limited'];

            return $result;
        }

        $result['error'] = $this->describeStatus($response->status(), $response->json('error.message'));

        return $result;
    }

    /**
     * TokenRouter: hitung model teks saja dari /models.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function checkTokenRouter(string $apiKey, array $result): array
    {
        $result = $this->checkModelsEndpoint('https://api.tokenrouter.com/v1', $apiKey, $result, null);
        if (!$result['valid']) {
            return $result;
        }

        $response = Http::timeout(self::TIMEOUT)
            ->withToken($apiKey)
            ->get('https://api.tokenrouter.com/v1/models');

        $models = $response->json('data') ?? [];
        $textModels = array_filter(
            $models,
            fn ($model) => in_array('openai', (array) ($model['supported_endpoint_types'] ?? []), true)
        );

        $result['models_count'] = count($textModels);
        $result['tier'] = 'pro';

        return $result;
    }

    /**
     * Ambil daftar model yang benar-benar tersedia dari provider.
     *
     * Dipakai admin panel untuk memilih model tanpa memperbarui katalog
     * manual. Hanya provider OpenAI-compatible yang punya GET /models.
     *
     * @return array<int, array{id: string, name: string, desc: string}>
     */
    public function listModels(string $slug, string $apiKey): array
    {
        $baseUrl = match ($slug) {
            'limitrouter' => 'https://limitrouter.com/v1',
            'tokenrouter' => 'https://api.tokenrouter.com/v1',
            'openrouter' => 'https://openrouter.ai/api/v1',
            'nvidia' => 'https://integrate.api.nvidia.com/v1',
            'minimax' => 'https://api.minimax.io/v1',
            'glm' => 'https://api.z.ai/api/coding/paas/v4',
            'kimi' => 'https://api.moonshot.cn/v1',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
            default => throw new \RuntimeException('Provider tidak mendukung daftar model: ' . $slug),
        };

        $response = Http::timeout(self::TIMEOUT)
            ->withToken($apiKey)
            ->get($baseUrl . '/models');

        if ($response->failed()) {
            throw new \RuntimeException(
                'Gagal mengambil daftar model: ' . $this->describeStatus($response->status(), $response->json('error.message'))
            );
        }

        // Gemini memakai struktur { models: [ { name: "models/x" } ] }
        $raw = $response->json('models') ?? $response->json('data') ?? [];

        $models = [];
        foreach ($raw as $model) {
            $id = (string) ($model['id'] ?? $model['name'] ?? '');
            if ($id === '') {
                continue;
            }

            // Gemini mengembalikan prefiks "models/"
            $id = preg_replace('#^models/#', '', $id);

            $models[] = [
                'id' => $id,
                'name' => $id,
                'desc' => $this->describeModel($model),
            ];
        }

        usort($models, fn (array $a, array $b) => strcmp($a['id'], $b['id']));

        return $models;
    }

    /**
     * Ringkasan singkat satu entri model dari respons /models.
     *
     * @param array<string, mixed> $model
     */
    private function describeModel(array $model): string
    {
        $parts = [];

        if (!empty($model['owned_by'])) {
            $parts[] = (string) $model['owned_by'];
        }

        $vision = $model['capabilities']['vision'] ?? null;
        if ($vision === true) {
            $parts[] = 'vision';
        }

        if (!empty($model['supportedGenerationMethods'])) {
            $parts[] = implode('/', (array) $model['supportedGenerationMethods']);
        }

        return $parts !== [] ? implode(' · ', $parts) : 'text';
    }

    /**
     * Terjemahkan HTTP status jadi pesan yang bisa dibaca admin.
     */
    private function describeStatus(int $status, ?string $providerMessage = null): string
    {
        return match ($status) {
            401 => 'API key tidak valid (401 Unauthorized)',
            402 => 'Kredit habis (402 Payment Required)' . ($providerMessage ? ': ' . $providerMessage : ''),
            403 => 'API key tidak punya akses (403 Forbidden)',
            429 => 'Rate limit tercapai (429 Too Many Requests)',
            default => 'Respons tidak terduga: HTTP ' . $status . ($providerMessage ? ' — ' . $providerMessage : ''),
        };
    }
}
