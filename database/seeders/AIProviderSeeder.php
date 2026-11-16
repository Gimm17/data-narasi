<?php

namespace Database\Seeders;

use App\Models\AIProvider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder untuk data awal AI Providers
 * Menambahkan 4 provider: Gemini, Kimi, GLM, Claude
 */
class AIProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            [
                'name' => 'LimitRouter',
                'slug' => 'limitrouter',
                'model_id' => 'deepseek-v4.1-flash',
                'base_url' => 'https://limitrouter.com/v1',
                'api_key_env' => 'LIMITROUTER_API_KEY',
                'priority' => 1, // Default utama
                'is_enabled' => true,
                'max_tokens' => 2048,
                'timeout_seconds' => 60,
            ],
            [
                'name' => 'Gemini',
                'slug' => 'gemini',
                'model_id' => 'gemini-3.5-flash',
                'base_url' => 'https://generativelanguage.googleapis.com',
                'api_key_env' => 'GEMINI_API_KEY',
                'priority' => 2,
                'is_enabled' => true,
                'max_tokens' => 2048,
                'timeout_seconds' => 60,
            ],
            [
                'name' => 'Kimi',
                'slug' => 'kimi',
                'model_id' => 'moonshot-v1-8k',
                'base_url' => 'https://api.moonshot.cn/v1',
                'api_key_env' => 'KIMI_API_KEY',
                'priority' => 3,
                'is_enabled' => true,
                'max_tokens' => 2048,
                'timeout_seconds' => 30,
            ],
            [
                'name' => 'GLM',
                'slug' => 'glm',
                'model_id' => 'GLM-4.5-air',
                'base_url' => 'https://api.z.ai/api/coding/paas/v4',
                'api_key_env' => 'GLM_API_KEY',
                'priority' => 4,
                'is_enabled' => true,
                'max_tokens' => 2048,
                'timeout_seconds' => 30,
            ],
            [
                'name' => 'NVIDIA NIM',
                'slug' => 'nvidia',
                'model_id' => 'nvidia/nemotron-3.5-lightning-30b-a3b',
                'base_url' => 'https://integrate.api.nvidia.com/v1',
                'api_key_env' => 'NVIDIA_API_KEY',
                'priority' => 5,
                'is_enabled' => true,
                'max_tokens' => 2048,
                'timeout_seconds' => 30,
            ],
            [
                'name' => 'MiniMax',
                'slug' => 'minimax',
                'model_id' => 'MiniMax-M2.5',
                'base_url' => 'https://api.minimax.io/v1',
                'api_key_env' => 'MINIMAX_API_KEY',
                'priority' => 6,
                'is_enabled' => true,
                'max_tokens' => 2048,
                'timeout_seconds' => 30,
            ],
            [
                'name' => 'Claude (Anthropic)',
                'slug' => 'claude',
                'model_id' => 'claude-sonnet-4-20250514',
                'base_url' => 'https://api.anthropic.com',
                'api_key_env' => 'CLAUDE_API_KEY',
                'priority' => 7,
                'is_enabled' => false, // Disabled by default (paid)
                'max_tokens' => 2048,
                'timeout_seconds' => 30,
            ],
            [
                'name' => 'OpenRouter',
                'slug' => 'openrouter',
                'model_id' => 'openai/gpt-5.5',
                'base_url' => 'https://openrouter.ai/api/v1',
                'api_key_env' => 'OPENROUTER_API_KEY',
                'priority' => 8,
                'is_enabled' => true,
                'max_tokens' => 2048,
                'timeout_seconds' => 60, // OpenRouter routing may add latency
            ],
            [
                'name' => 'TokenRouter',
                'slug' => 'tokenrouter',
                'model_id' => 'deepseek/deepseek-v4-pro',
                'base_url' => 'https://api.tokenrouter.com/v1',
                'api_key_env' => 'TOKENROUTER_API_KEY',
                'priority' => 9,
                'is_enabled' => true,
                'max_tokens' => 2048,
                'timeout_seconds' => 60,
            ],
        ];

        foreach ($providers as $provider) {
            // Cocokkan lewat slug agar baris lama ikut diperbarui
            // (bukan menambah baris duplikat dengan nama berbeda).
            AIProvider::updateOrCreate(
                [
                    'slug' => $provider['slug'],
                ],
                $provider
            );
        }

        $this->command->info('✅ AI Providers seeded successfully!');
        $this->command->newLine();
        $this->command->table(
            ['Name', 'Model', 'Priority', 'Enabled'],
            AIProvider::all(['name', 'model_id', 'priority', 'is_enabled'])->map(function ($provider) {
                return [
                    $provider->name,
                    $provider->model_id,
                    $provider->priority,
                    $provider->is_enabled ? '✅' : '❌',
                ];
            })->toArray()
        );
    }
}
