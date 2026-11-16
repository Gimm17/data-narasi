<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AiClient — port PHP dari python-service/ai_provider.py + providers/*.py
 *
 * Menghubungi provider AI lewat HTTP langsung (tanpa SDK):
 * LimitRouter, Gemini, Kimi, GLM, NVIDIA, MiniMax, Claude, OpenRouter, TokenRouter.
 *
 * Semua API key dibaca dari .env lewat env() — tidak pernah di-hardcode.
 * Urutan fallback bisa ditimpa per-request dari tabel ai_providers.
 */
class AiClient
{
    /** Urutan fallback default. */
    public const PROVIDER_ORDER = [
        'limitrouter',
        'gemini',
        'kimi',
        'glm',
        'nvidia',
        'minimax',
        'claude',
        'openrouter',
        'tokenrouter',
    ];

    /**
     * Definisi tiap provider.
     *
     * flavor: openai (chat/completions) | gemini (generateContent) | anthropic (messages)
     */
    private const PROVIDERS = [
        'limitrouter' => [
            'env' => 'LIMITROUTER_API_KEY',
            'flavor' => 'openai',
            'base_url' => 'https://limitrouter.com/v1',
            'model' => 'deepseek-v4.1-flash',
            'model_fallbacks' => ['deepseek-v4-flash', 'deepseek-v3.2', 'glm-5.3-flash'],
            // Model reasoning: token berpikir memakan jatah output
            'reasoning' => true,
        ],
        'gemini' => [
            'env' => 'GEMINI_API_KEY',
            'flavor' => 'gemini',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'model' => 'gemini-3.5-flash',
            'model_fallbacks' => ['gemini-2.5-flash', 'gemini-flash-latest'],
        ],
        'kimi' => [
            'env' => 'KIMI_API_KEY',
            'flavor' => 'openai',
            'base_url' => 'https://api.moonshot.cn/v1',
            'model' => 'moonshot-v1-8k',
        ],
        'glm' => [
            'env' => 'GLM_API_KEY',
            'flavor' => 'openai',
            'base_url' => 'https://api.z.ai/api/coding/paas/v4',
            'model' => 'GLM-4.5-air',
            // GLM cenderung menjawab Mandarin — tambahkan penegasan eksplisit
            'indonesian_rule' => true,
        ],
        'nvidia' => [
            'env' => 'NVIDIA_API_KEY',
            'flavor' => 'openai',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'model' => 'nvidia/nemotron-3.5-lightning-30b-a3b',
            'model_fallbacks' => [
                'openai/gpt-oss-20b',
                'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning',
                'deepseek-ai/deepseek-v4.1-flash',
            ],
            'top_p' => 1.0,
        ],
        'minimax' => [
            'env' => 'MINIMAX_API_KEY',
            'flavor' => 'openai',
            'base_url' => 'https://api.minimax.io/v1',
            'model' => 'MiniMax-M2.5',
            'top_p' => 0.9,
            'strip_thinking' => true,
        ],
        'claude' => [
            'env' => 'CLAUDE_API_KEY',
            'flavor' => 'anthropic',
            'base_url' => 'https://api.anthropic.com/v1',
            'model' => 'claude-sonnet-4-20250514',
        ],
        'openrouter' => [
            'env' => 'OPENROUTER_API_KEY',
            'flavor' => 'openai',
            'base_url' => 'https://openrouter.ai/api/v1',
            'model' => 'openai/gpt-5.5',
            'strip_thinking' => true,
            'headers' => [
                'HTTP-Referer' => 'https://datanarasi.app',
                'X-OpenRouter-Title' => 'DataNarasi',
            ],
            // Kredit habis (402) → retry lebih kecil → model gratis
            'credit_fallback' => true,
        ],
        'tokenrouter' => [
            'env' => 'TOKENROUTER_API_KEY',
            'flavor' => 'openai',
            'base_url' => 'https://api.tokenrouter.com/v1',
            'model' => 'deepseek/deepseek-v4-pro',
            'strip_thinking' => true,
        ],
    ];

    /** Model gratis OpenRouter saat kredit habis. */
    private const OPENROUTER_FREE_FALLBACK = [
        'google/gemini-2.5-flash',
        'deepseek/deepseek-chat-v3-0324',
        'meta-llama/llama-4-maverick',
        'qwen/qwen3-235b-a22b',
    ];

    /** Penegasan Bahasa Indonesia untuk provider yang suka keluar jalur. */
    private const INDONESIAN_RULE = "\n\nPENTING: Jawab HANYA dalam Bahasa Indonesia. Jangan gunakan bahasa Mandarin atau bahasa lain sama sekali.";

    /**
     * Batas atas token output untuk pengulangan otomatis.
     * Prompt 6 bagian biasanya butuh 1000–1600 token output.
     */
    private const MAX_OUTPUT_CEILING = 4096;

    /** Timeout HTTP per request (detik). */
    private int $timeout;

    /** Timeout untuk provider yang lambat (reasoning model). */
    private int $longTimeout;

    public function __construct()
    {
        $this->timeout = (int) config('ai.timeout', 90);
        $this->longTimeout = (int) config('ai.long_timeout', 180);
    }

    /**
     * Generate narasi dengan fallback otomatis antar provider.
     *
     * @param array<int, string|array{slug: string, model_id?: string|null}>|null $providerOrder
     * @return array{
     *     narrative: string,
     *     provider_used: string,
     *     model_used?: string|null,
     *     attempts: int,
     *     success: bool,
     *     logs: array<int, array<string, mixed>>,
     *     processing_time_ms: int,
     *     prompt_tokens?: int,
     *     completion_tokens?: int,
     *     total_tokens?: int,
     *     cost_usd?: float
     * }
     */
    public function generate(
        string $prompt,
        string $systemPrompt,
        int $maxTokens = 1024,
        ?array $providerOrder = null
    ): array {
        $logs = [];
        $startTime = microtime(true);

        ['order' => $order, 'models' => $modelOverrides, 'efforts' => $effortOverrides] = $this->resolveOrder($providerOrder);

        foreach (array_values($order) as $index => $slug) {
            $attempt = $index + 1;
            $apiKey = $this->apiKeyFor($slug);

            if ($apiKey === '') {
                $logs[] = $this->failureLog(
                    $slug,
                    'auth_error',
                    $attempt,
                    "{$slug} tidak aktif (API key " . self::PROVIDERS[$slug]['env'] . ' kosong)'
                );
                Log::info("AI skip {$slug}: API key belum diset");

                continue;
            }

            $override = $modelOverrides[$slug] ?? null;
            $effort = $this->normalizeEffort($effortOverrides[$slug] ?? null);

            try {
                $startGenerate = microtime(true);
                $result = $this->callProvider($slug, $apiKey, $prompt, $systemPrompt, $maxTokens, $override, $effort);
                $processingTime = (int) round((microtime(true) - $startGenerate) * 1000);

                $modelUsed = $result['model_used'] ?: ($override ?: self::PROVIDERS[$slug]['model']);
                $promptTokens = $result['prompt_tokens'];
                $completionTokens = $result['completion_tokens'];
                $totalTokens = $result['total_tokens'];
                $costUsd = CostCalculator::calculate($modelUsed, $promptTokens, $completionTokens);

                // Estimasi bila API tidak mengembalikan usage
                if ($totalTokens === 0) {
                    $totalTokens = max(1, (int) (str_word_count($result['narrative']) * 1.3));
                    $promptTokens = 0;
                    $completionTokens = $totalTokens;
                }

                $logs[] = [
                    'provider_name' => $slug,
                    'status' => 'success',
                    'attempt_number' => $attempt,
                    'error_message' => null,
                    'tokens_used' => $totalTokens,
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'model_used' => $modelUsed,
                    'cost_usd' => $costUsd,
                    'response_time_ms' => $processingTime,
                ];

                Log::info("AI {$slug} berhasil generate narasi", [
                    'model' => $modelUsed,
                    'tokens' => $totalTokens,
                    'cost_usd' => $costUsd,
                ]);

                return [
                    'narrative' => $result['narrative'],
                    'provider_used' => $slug,
                    'model_used' => $modelUsed,
                    'attempts' => $attempt,
                    'success' => true,
                    'logs' => $logs,
                    'processing_time_ms' => $processingTime,
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $totalTokens,
                    'cost_usd' => $costUsd,
                ];
            } catch (\Throwable $e) {
                $message = $this->extractErrorMessage($e->getMessage());
                $status = $this->classifyError($e->getMessage());

                $logs[] = $this->failureLog($slug, $status, $attempt, $message);
                Log::warning("AI {$slug} gagal: {$status} — {$message}");
            }
        }

        $totalTime = (int) round((microtime(true) - $startTime) * 1000);
        Log::error('Semua AI provider gagal, memakai narasi fallback', ['attempts' => count($order)]);

        return [
            'narrative' => $this->fallbackNarrative(),
            'provider_used' => 'fallback',
            'attempts' => count($order),
            'success' => false,
            'logs' => $logs,
            'processing_time_ms' => $totalTime,
        ];
    }

    /**
     * API key satu provider.
     *
     * Diambil dari config('ai.keys') agar tetap terbaca setelah config:cache;
     * fallback ke env() untuk pemakaian di luar konteks Laravel.
     */
    private function apiKeyFor(string $slug): string
    {
        $fromConfig = config('ai.keys.' . $slug);
        $value = is_string($fromConfig) && trim($fromConfig) !== ''
            ? $fromConfig
            : (string) env(self::PROVIDERS[$slug]['env']);

        return trim($value);
    }

    /**
     * Susun urutan provider + override model dari DB admin panel.
     *
     * Menerima dua format: ["gemini", ...] atau [{"slug": "...", "model_id": "..."}].
     *
     * @param array<int, string|array<string, mixed>>|null $providerOrder
     * @return array{order: array<int, string>, models: array<string, string>}
     */
    private function resolveOrder(?array $providerOrder): array
    {
        $defaults = $this->configuredOrder();
        $models = [];
        $efforts = [];

        if ($providerOrder === null || $providerOrder === []) {
            return ['order' => $defaults, 'models' => $models, 'efforts' => $efforts];
        }

        $valid = [];
        foreach ($providerOrder as $item) {
            if (is_array($item)) {
                $slug = (string) ($item['slug'] ?? '');
                if (!isset(self::PROVIDERS[$slug])) {
                    continue;
                }
                $valid[] = $slug;
                if (!empty($item['model_id'])) {
                    $models[$slug] = (string) $item['model_id'];
                }
                if (!empty($item['reasoning_effort'])) {
                    $efforts[$slug] = (string) $item['reasoning_effort'];
                }
            } elseif (is_string($item) && isset(self::PROVIDERS[$item])) {
                $valid[] = $item;
            }
        }

        if ($valid === []) {
            return ['order' => $defaults, 'models' => $models, 'efforts' => $efforts];
        }

        // Buang duplikat tapi pertahankan urutan prioritas
        $valid = array_values(array_unique($valid));
        Log::info('AI provider order dari DB: ' . implode(' → ', $valid), [
            'model_overrides' => $models,
            'reasoning_overrides' => $efforts,
        ]);

        return ['order' => $valid, 'models' => $models, 'efforts' => $efforts];
    }

    /**
     * Validasi nilai reasoning_effort.
     *
     * Daftar ini adalah enum yang diterima API LimitRouter. Tidak semua model
     * mendukung semua nilai — deepseek-v4.1-flash menolak "minimal" dan
     * "medium" — sehingga nilai yang ditolak akan jatuh ke percobaan tanpa
     * parameter (lihat callProvider).
     *
     * @return array<int, string>
     */
    public static function reasoningEfforts(): array
    {
        return ['none', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'];
    }

    /**
     * Kembalikan nilai yang valid, atau null bila kosong/tidak dikenal.
     */
    private function normalizeEffort(?string $effort): ?string
    {
        if ($effort === null) {
            return null;
        }

        $effort = strtolower(trim($effort));

        return in_array($effort, self::reasoningEfforts(), true) ? $effort : null;
    }

    /**
     * Urutan provider dari AI_PROVIDER_ORDER (env), dengan fallback default.
     *
     * @return array<int, string>
     */
    private function configuredOrder(): array
    {
        $order = config('ai.order');

        if (!is_array($order) || $order === []) {
            return self::PROVIDER_ORDER;
        }

        $filtered = array_values(array_filter(
            $order,
            fn ($slug) => is_string($slug) && isset(self::PROVIDERS[$slug])
        ));

        return $filtered !== [] ? $filtered : self::PROVIDER_ORDER;
    }

    /**
     * Panggil satu provider dan kembalikan narasi + usage.
     *
     * @return array{narrative: string, prompt_tokens: int, completion_tokens: int, total_tokens: int, model_used: string}
     */
    private function callProvider(
        string $slug,
        string $apiKey,
        string $prompt,
        string $systemPrompt,
        int $maxTokens,
        ?string $modelOverride,
        ?string $effort = null
    ): array {
        $config = self::PROVIDERS[$slug];
        $model = $modelOverride ?: $config['model'];

        // Urutan percobaan: model yang diminta admin dulu, lalu model default
        // provider, lalu model pengganti. Model bisa dihentikan provider kapan
        // saja (404/410) — tanpa rantai ini, satu baris lama di tabel
        // ai_providers akan mematikan provider tersebut selamanya.
        $candidates = [$model];
        foreach (array_merge([$config['model']], $config['model_fallbacks'] ?? []) as $alternative) {
            if (!in_array($alternative, $candidates, true)) {
                $candidates[] = $alternative;
            }
        }

        $lastError = null;
        foreach ($candidates as $candidate) {
            // Provider ini menerima dua bentuk parameter reasoning:
            //   1. flat   : reasoning_effort = "low"        (dipakai sebagian besar model)
            //   2. nested : reasoning = { effort: "low" }   (dipakai model yang menolak bentuk flat)
            // "none" hanya benar-benar mematikan berpikir pada bentuk flat, jadi
            // urutannya flat → nested → tanpa parameter.
            $attempts = $effort === null
                ? [[null, 'flat']]
                : [[$effort, 'flat'], [$effort, 'nested'], [null, 'flat']];

            foreach ($attempts as $index => [$currentEffort, $style]) {
                $isLastAttempt = $index === count($attempts) - 1;

                try {
                    $result = $this->dispatch(
                        $config,
                        $apiKey,
                        $candidate,
                        $prompt,
                        $systemPrompt,
                        $maxTokens,
                        $slug,
                        $currentEffort,
                        $style
                    );

                    // Provider bisa memakai model lain dari yang diminta
                    // (mis. OpenRouter beralih ke model gratis saat kredit habis).
                    return ['model_used' => $result['model_used'] ?? $candidate] + $result;
                } catch (\Throwable $e) {
                    $lastError = $e;

                    // Parameter ini ditolak provider — coba bentuk berikutnya
                    if ($currentEffort !== null && $this->isEffortRejected($e->getMessage())) {
                        Log::warning("Model {$candidate} menolak reasoning_effort={$currentEffort} ({$style}), mencoba bentuk lain");

                        continue;
                    }

                    // Model pengganti bila model ini sudah dihentikan provider
                    if ($this->isModelUnavailable($e->getMessage())) {
                        Log::warning("Model {$candidate} tidak tersedia untuk {$slug}, mencoba model pengganti");

                        break;
                    }

                    // Bentuk parameter ini menghasilkan jawaban tidak valid.
                    // Sebelum menyerah, masih ada percobaan terakhir tanpa
                    // parameter reasoning — jangan batalkan di sini.
                    if (!$isLastAttempt && $currentEffort !== null) {
                        Log::warning("Hasil dengan reasoning_effort={$currentEffort} ({$style}) tidak valid, mencoba tanpa parameter");

                        continue;
                    }

                    throw $e;
                }
            }
        }

        throw $lastError;
    }

    /**
     * Apakah provider menolak nilai/bentuk reasoning_effort yang dikirim?
     *
     * LimitRouter hanya mengembalikan pesan generik "Invalid request" tanpa
     * menyebut nama parameternya, jadi setiap error 4xx saat parameter itu
     * dikirim dianggap sebagai penolakan dan dicoba bentuk lainnya.
     */
    private function isEffortRejected(string $message): bool
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'reasoning') && (
            str_contains($lower, 'invalid')
            || str_contains($lower, 'unknown variant')
            || str_contains($lower, 'not supported')
            || str_contains($lower, 'unsupported')
        )) {
            return true;
        }

        return str_contains($lower, 'invalid request')
            || str_contains($lower, 'invalid_request')
            || str_contains($lower, 'bad request');
    }

    /**
     * Kirim ke provider sesuai gaya API-nya, lalu validasi narasi.
     *
     * @param array<string, mixed> $config
     * @return array{narrative: string, prompt_tokens: int, completion_tokens: int, total_tokens: int, model_used?: string}
     */
    private function dispatch(
        array $config,
        string $apiKey,
        string $model,
        string $prompt,
        string $systemPrompt,
        int $maxTokens,
        string $slug,
        ?string $effort = null,
        string $effortStyle = 'flat'
    ): array {
        $result = match ($config['flavor']) {
            'gemini' => $this->callGemini($config, $apiKey, $model, $prompt, $systemPrompt, $maxTokens),
            'anthropic' => $this->callAnthropic($config, $apiKey, $model, $prompt, $systemPrompt, $maxTokens),
            default => $this->callOpenAiCompatible($config, $apiKey, $model, $prompt, $systemPrompt, $maxTokens, $slug === 'glm', $effort, $effortStyle),
        };

        $narrative = $result['narrative'];

        if (!empty($config['strip_thinking'])) {
            $narrative = $this->stripThinkingTags($narrative);
        }

        if (!$this->validateNarrative($narrative)) {
            throw new \RuntimeException('Generated narrative tidak memenuhi kriteria validasi');
        }

        $result['narrative'] = trim($narrative);

        return $result;
    }

    /**
     * Apakah error menandakan model sudah dihentikan provider?
     */
    private function isModelUnavailable(string $message): bool
    {
        if (!preg_match('/\b(404|410)\b/', $message)) {
            return false;
        }

        $lower = strtolower($message);

        return str_contains($lower, 'no longer available')
            || str_contains($lower, 'end of life')
            || str_contains($lower, 'not found')
            || str_contains($lower, 'does not exist')
            || str_contains($lower, 'decommission');
    }

    /**
     * Provider bergaya OpenAI: /chat/completions.
     *
     * @param array<string, mixed> $config
     * @return array{narrative: string, prompt_tokens: int, completion_tokens: int, total_tokens: int, model_used?: string}
     */
    private function callOpenAiCompatible(
        array $config,
        string $apiKey,
        string $model,
        string $prompt,
        string $systemPrompt,
        int $maxTokens,
        bool $appendIndonesianRule = false,
        ?string $effort = null,
        string $effortStyle = 'flat'
    ): array {
        if (!empty($config['credit_fallback'])) {
            return $this->callOpenRouterWithCreditFallback(
                $config,
                $apiKey,
                $model,
                $prompt,
                $systemPrompt,
                $maxTokens
            );
        }

        if ($appendIndonesianRule) {
            $systemPrompt .= self::INDONESIAN_RULE;
            $prompt .= self::INDONESIAN_RULE;
        }

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => $maxTokens,
        ];
        if (isset($config['top_p'])) {
            $body['top_p'] = $config['top_p'];
        }

        // Model reasoning menghitung token berpikir ke dalam jatah output.
        // Pada uji nyata, 243 dari 282 token habis untuk reasoning sehingga
        // narasi terpotong. Nilai efektifnya: pilihan admin dari panel,
        // lalu default provider, lalu "none" bila provider ditandai reasoning.
        $effectiveEffort = $effort ?? ($config['reasoning'] ?? false ? 'none' : null);
        if ($effectiveEffort !== null) {
            if ($effortStyle === 'nested') {
                $body['reasoning'] = ['effort' => $effectiveEffort];
            } else {
                $body['reasoning_effort'] = $effectiveEffort;
            }
        }

        $response = $this->send($config['base_url'] . '/chat/completions', [
            'Authorization' => 'Bearer ' . $apiKey,
        ], $body, $config);

        $message = $response['choices'][0]['message'] ?? [];
        $narrative = $message['content'] ?? '';

        // Sebagian model reasoning mengirim konten final di field terpisah.
        if ((!is_string($narrative) || trim($narrative) === '') && is_string($message['reasoning_content'] ?? null)) {
            $narrative = $message['reasoning_content'];
        }

        if (!is_string($narrative) || trim($narrative) === '') {
            throw new \RuntimeException('Provider mengembalikan narasi kosong (choices[0].message.content kosong)');
        }

        $usage = $response['usage'] ?? [];

        return [
            'narrative' => $narrative,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            'model_used' => (string) ($response['model'] ?? $model),
        ];
    }

    /**
     * OpenRouter: bila 402 (kredit kurang) → kurangi max_tokens → model gratis.
     *
     * Model yang benar-benar dipakai ikut dikembalikan agar biaya dihitung
     * dari model yang sebenarnya, bukan model yang gagal.
     *
     * @param array<string, mixed> $config
     * @return array{narrative: string, prompt_tokens: int, completion_tokens: int, total_tokens: int, model_used: string}
     */
    private function callOpenRouterWithCreditFallback(
        array $config,
        string $apiKey,
        string $model,
        string $prompt,
        string $systemPrompt,
        int $maxTokens
    ): array {
        try {
            return $this->openRouterAttempt($config, $apiKey, $model, $prompt, $systemPrompt, $maxTokens);
        } catch (\Throwable $e) {
            if (!$this->isCreditError($e->getMessage())) {
                throw $e;
            }
            Log::warning("OpenRouter 402 dengan {$model}, mencoba max_tokens lebih kecil");
        }

        $reduced = min($maxTokens, 512);
        try {
            return $this->openRouterAttempt($config, $apiKey, $model, $prompt, $systemPrompt, $reduced);
        } catch (\Throwable $e) {
            if (!$this->isCreditError($e->getMessage())) {
                throw $e;
            }
            Log::warning('OpenRouter masih 402, mencoba model gratis');
        }

        foreach (self::OPENROUTER_FREE_FALLBACK as $freeModel) {
            try {
                return $this->openRouterAttempt($config, $apiKey, $freeModel, $prompt, $systemPrompt, $maxTokens);
            } catch (\Throwable $e) {
                Log::warning("OpenRouter fallback {$freeModel} gagal: " . $this->extractErrorMessage($e->getMessage()));
            }
        }

        throw new \RuntimeException(
            'OpenRouter kredit habis dan semua model fallback gagal. Top up di https://openrouter.ai/settings/credits'
        );
    }

    /**
     * Satu panggilan OpenRouter (dipakai ulang oleh strategi fallback).
     *
     * @param array<string, mixed> $config
     * @return array{narrative: string, prompt_tokens: int, completion_tokens: int, total_tokens: int, model_used: string}
     */
    private function openRouterAttempt(
        array $config,
        string $apiKey,
        string $model,
        string $prompt,
        string $systemPrompt,
        int $maxTokens
    ): array {
        $headers = array_merge(['Authorization' => 'Bearer ' . $apiKey], $config['headers'] ?? []);

        $response = $this->send($config['base_url'] . '/chat/completions', $headers, [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => $maxTokens,
        ], $config);

        $narrative = $response['choices'][0]['message']['content'] ?? '';
        if (!is_string($narrative) || trim($narrative) === '') {
            throw new \RuntimeException('OpenRouter mengembalikan narasi kosong');
        }

        $usage = $response['usage'] ?? [];

        return [
            'narrative' => $narrative,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            // OpenRouter bisa mengalihkan ke model lain; pakai yang dilaporkan
            'model_used' => (string) ($response['model'] ?? $model),
        ];
    }

    /**
     * Gemini: POST /models/{model}:generateContent.
     *
     * @param array<string, mixed> $config
     * @return array{narrative: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    private function callGemini(
        array $config,
        string $apiKey,
        string $model,
        string $prompt,
        string $systemPrompt,
        int $maxTokens
    ): array {
        $url = $config['base_url'] . '/models/' . $model . ':generateContent';

        $generationConfig = [
            'temperature' => 0.7,
            'maxOutputTokens' => $maxTokens,
            // Model 3.x punya "thinking" bawaan: tanpa budget 0, seluruh
            // jatah token habis untuk proses berpikir tersembunyi dan narasi
            // terpotong di beberapa kalimat (finishReason MAX_TOKENS).
            'thinkingConfig' => ['thinkingBudget' => 0],
        ];

        $body = [
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => $generationConfig,
        ];

        try {
            $response = $this->send($url, ['x-goog-api-key' => $apiKey], $body, $config);
        } catch (\RuntimeException $e) {
            // Model lama bisa menolak thinkingConfig — ulangi tanpa parameter itu
            if (!str_contains(strtolower($e->getMessage()), 'thinking')) {
                throw $e;
            }

            unset($generationConfig['thinkingConfig']);
            $body['generationConfig'] = $generationConfig;

            $response = $this->send($url, ['x-goog-api-key' => $apiKey], $body, $config);
        }

        $candidate = $response['candidates'][0] ?? null;
        if ($candidate === null) {
            $blocked = $response['promptFeedback']['blockReason'] ?? 'tidak ada kandidat';
            throw new \RuntimeException('Gemini tidak mengembalikan kandidat: ' . $blocked);
        }

        $finishReason = $candidate['finishReason'] ?? '';

        // Terpotong karena jatah token habis — prompt 6 bagian memang butuh
        // lebih dari 1024 token. Ulangi sekali dengan jatah dua kali lipat
        // sebelum menyerah, supaya narasi tidak berhenti di tengah kalimat.
        if ($finishReason === 'MAX_TOKENS' && $maxTokens < self::MAX_OUTPUT_CEILING) {
            return $this->callGemini(
                $config,
                $apiKey,
                $model,
                $prompt,
                $systemPrompt,
                min($maxTokens * 2, self::MAX_OUTPUT_CEILING)
            );
        }

        $parts = $candidate['content']['parts'] ?? [];
        $narrative = '';
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $narrative .= $part['text'];
            }
        }

        if (trim($narrative) === '') {
            throw new \RuntimeException('Gemini mengembalikan narasi kosong (finishReason: ' . ($finishReason ?: 'UNKNOWN') . ')');
        }

        if ($finishReason === 'MAX_TOKENS') {
            // Masih terpotong walau jatah sudah maksimal: terima bila isinya
            // sudah panjang (utuh secara substansi), tolak bila terlalu pendek.
            if (str_word_count($narrative) < 150) {
                throw new \RuntimeException('Narasi Gemini terpotong terlalu pendek (finishReason MAX_TOKENS)');
            }

            Log::warning('Narasi Gemini terpotong di batas token, namun isinya sudah panjang — diterima', [
                'model' => $model,
                'words' => str_word_count($narrative),
            ]);
        }

        $usage = $response['usageMetadata'] ?? [];

        return [
            'narrative' => $narrative,
            'prompt_tokens' => (int) ($usage['promptTokenCount'] ?? 0),
            'completion_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
            'total_tokens' => (int) ($usage['totalTokenCount'] ?? 0),
            'model_used' => (string) ($response['modelVersion'] ?? $model),
        ];
    }

    /**
     * Anthropic Claude: POST /messages.
     *
     * @param array<string, mixed> $config
     * @return array{narrative: string, prompt_tokens: int, completion_tokens: int, total_tokens: int, model_used: string}
     */
    private function callAnthropic(
        array $config,
        string $apiKey,
        string $model,
        string $prompt,
        string $systemPrompt,
        int $maxTokens
    ): array {
        $response = $this->send($config['base_url'] . '/messages', [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ], [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => 0.7,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ], $config);

        $narrative = '';
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $narrative .= $block['text'] ?? '';
            }
        }

        if (trim($narrative) === '') {
            throw new \RuntimeException('Claude mengembalikan narasi kosong');
        }

        $usage = $response['usage'] ?? [];
        $promptTokens = (int) ($usage['input_tokens'] ?? 0);
        $completionTokens = (int) ($usage['output_tokens'] ?? 0);

        return [
            'narrative' => $narrative,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'model_used' => (string) ($response['model'] ?? $model),
        ];
    }

    /**
     * Kirim request JSON dan kembalikan body sebagai array.
     *
     * Melempar RuntimeException dengan status + potongan body bila gagal,
     * supaya classificasi error tetap bisa membaca "429"/"401"/"timeout".
     *
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function send(string $url, array $headers, array $body, array $config): array
    {
        $timeout = !empty($config['long_timeout']) ? $this->longTimeout : $this->timeout;

        try {
            $response = Http::withHeaders(array_merge($headers, ['Content-Type' => 'application/json']))
                ->timeout($timeout)
                ->connectTimeout(min(20, $timeout))
                ->post($url, $body);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Koneksi gagal / timeout: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $snippet = mb_substr(trim((string) $response->body()), 0, 500);

            throw new \RuntimeException("HTTP {$response->status()}: {$snippet}");
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new \RuntimeException('Respons provider bukan JSON yang valid: ' . mb_substr((string) $response->body(), 0, 300));
        }

        return $json;
    }

    /**
     * Validasi narasi (port validate_narrative dari base.py).
     */
    public function validateNarrative(string $text): bool
    {
        $cleaned = trim($text);
        if ($cleaned === '') {
            return false;
        }

        // Minimal 50 kata
        if (str_word_count($cleaned) < 50) {
            $words = str_word_count($cleaned);
            Log::warning("Validasi narasi gagal: terlalu pendek ({$words} kata, min 50)");

            return false;
        }

        // Tidak boleh mengandung karakter CJK
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $cleaned)) {
            Log::warning('Validasi narasi gagal: mengandung karakter CJK');

            return false;
        }

        // Harus menyebut minimal satu angka
        if (!preg_match('/\d/', $cleaned)) {
            Log::warning('Validasi narasi gagal: tidak mengandung angka');

            return false;
        }

        // Awalan terlarang hanya peringatan lunak, tidak menggagalkan
        $firstWord = strtolower(strtok($cleaned, " \n\t") ?: '');
        if (in_array($firstWord, ['berikut', 'tentu', 'baik'], true)) {
            Log::info("Narasi diawali '{$firstWord}' — diterima dengan peringatan");
        }

        return true;
    }

    /**
     * Hapus blok <think>...</think> dari model reasoning.
     */
    private function stripThinkingTags(string $text): string
    {
        return trim((string) preg_replace('/<think>.*?<\/think>/s', '', $text));
    }

    /**
     * Klasifikasi error untuk status log.
     *
     * Sengaja memakai pencocokan yang lebih ketat daripada versi Python:
     * substring 'rate' pernah cocok dengan hostname "integrate.api.nvidia.com"
     * sehingga error SSL tercatat sebagai rate_limit.
     */
    public function classifyError(string $message): string
    {
        $lower = strtolower($message);

        return match (true) {
            (bool) preg_match('/\b429\b/', $message),
            str_contains($lower, 'rate limit'),
            str_contains($lower, 'ratelimit'),
            str_contains($lower, 'rate_limit'),
            str_contains($lower, 'too many requests') => 'rate_limit',

            str_contains($lower, 'timeout'),
            str_contains($lower, 'timed out'),
            str_contains($lower, 'timed-out') => 'timeout',

            (bool) preg_match('/\b401\b/', $message),
            (bool) preg_match('/\b403\b/', $message),
            str_contains($lower, 'unauthorized'),
            str_contains($lower, 'forbidden'),
            str_contains($lower, 'invalid api key'),
            str_contains($lower, 'invalid_api_key'),
            str_contains($lower, 'api key not valid'),
            str_contains($lower, 'incorrect api key') => 'auth_error',

            str_contains($lower, 'validation'),
            str_contains($lower, 'kriteria validasi') => 'validation_fail',

            default => 'error',
        };
    }

    /**
     * Bersihkan pesan error: redaksi API key, rapikan spasi, maks 200 karakter.
     *
     * Body error JSON provider sering diawali baris kosong atau kurung kurawal,
     * jadi baris pertama tidak cukup informatif — spasi dirapikan dulu.
     */
    public function extractErrorMessage(string $message): string
    {
        $clean = (string) preg_replace('/\s+/', ' ', trim($message));
        $clean = (string) preg_replace('/AIza[A-Za-z0-9\-_]+/', '[REDACTED]', $clean);
        $clean = (string) preg_replace('/sk-[A-Za-z0-9\-_]{16,}/', '[REDACTED]', $clean);

        return mb_substr($clean, 0, 200);
    }

    /**
     * Apakah error menandakan kredit OpenRouter habis?
     */
    private function isCreditError(string $message): bool
    {
        return str_contains($message, '402') || str_contains(strtolower($message), 'credit');
    }

    /**
     * Entri log untuk satu percobaan yang gagal.
     *
     * @return array<string, mixed>
     */
    private function failureLog(string $slug, string $status, int $attempt, string $message): array
    {
        return [
            'provider_name' => $slug,
            'status' => $status,
            'attempt_number' => $attempt,
            'error_message' => $message,
            'tokens_used' => null,
            'response_time_ms' => null,
        ];
    }

    /**
     * Narasi cadangan bila seluruh provider gagal (port _generate_fallback_narrative).
     */
    public function fallbackNarrative(): string
    {
        return <<<'TEXT'
            Analisis Data Telah Selesai

            Terima kasih telah menunggu. Analisis data Anda telah selesai diproses. Namun, saat ini kami mengalami kendala teknis dalam menghasilkan narasi insight yang mendalam.

            Data Anda telah berhasil dibersihkan dan dianalisis. Berikut ringkasan proses yang telah dilakukan:

            1. Data Cleansing: Membersihkan data dari duplikat, nilai kosong, dan format yang tidak konsisten
            2. Statistical Analysis: Menghitung statistik deskriptif dan mengidentifikasi pola dalam data
            3. Chart Generation: Membuat visualisasi grafik untuk memudahkan pemahaman data

            Untuk mendapatkan narasi insight yang lebih detail, silakan coba beberapa saat lagi atau hubungi administrator.

            Terima kasih atas kesabaran Anda.
            TEXT;
    }

    /**
     * Daftar provider yang dikenal (dipakai admin panel / monitoring).
     *
     * @return array<int, string>
     */
    public static function supportedProviders(): array
    {
        return array_keys(self::PROVIDERS);
    }

    /**
     * Env key untuk satu provider.
     */
    public static function envKey(string $slug): ?string
    {
        return self::PROVIDERS[$slug]['env'] ?? null;
    }
}
