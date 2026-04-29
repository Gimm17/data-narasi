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
                'name' => 'Gemini 1.5 Flash',
                'slug' => 'gemini',
                'model_id' => 'gemini-1.5-flash',
                'base_url' => 'https://generativelanguage.googleapis.com',
                'api_key_env' => 'GEMINI_API_KEY',
                'priority' => 1,
                'is_enabled' => true,
                'max_tokens' => 1024,
                'timeout_seconds' => 30,
            ],
            [
                'name' => 'Kimi',
                'slug' => 'kimi',
                'model_id' => 'kimi-for-coding',
                'base_url' => 'https://api.kimi.com/coding/v1',
                'api_key_env' => 'KIMI_API_KEY',
                'priority' => 2,
                'is_enabled' => true,
                'max_tokens' => 1024,
                'timeout_seconds' => 30,
            ],
            [
                'name' => 'GLM-4 Flash',
                'slug' => 'glm',
                'model_id' => 'glm-4-flash',
                'base_url' => 'https://open.bigmodel.cn/api/paas/v4',
                'api_key_env' => 'GLM_API_KEY',
                'priority' => 3,
                'is_enabled' => true,
                'max_tokens' => 1024,
                'timeout_seconds' => 30,
            ],
            [
                'name' => 'NVIDIA NIM',
                'slug' => 'nvidia',
                'model_id' => 'meta/llama-3.1-8b-instruct',
                'base_url' => 'https://integrate.api.nvidia.com/v1',
                'api_key_env' => 'NVIDIA_API_KEY',
                'priority' => 4,
                'is_enabled' => true,
                'max_tokens' => 1024,
                'timeout_seconds' => 30,
            ],
            [
                'name' => 'MiniMax',
                'slug' => 'minimax',
                'model_id' => 'MiniMax-M2.5',
                'base_url' => 'https://api.minimax.io/v1',
                'api_key_env' => 'MINIMAX_API_KEY',
                'priority' => 5,
                'is_enabled' => true,
                'max_tokens' => 1024,
                'timeout_seconds' => 30,
            ],
            [
                'name' => 'Claude (Anthropic)',
                'slug' => 'claude',
                'model_id' => 'claude-sonnet-4-20250514',
                'base_url' => 'https://api.anthropic.com',
                'api_key_env' => 'CLAUDE_API_KEY',
                'priority' => 6,
                'is_enabled' => false, // Disabled by default (paid)
                'max_tokens' => 1024,
                'timeout_seconds' => 30,
            ],
        ];

        foreach ($providers as $provider) {
            AIProvider::updateOrCreate(
                [
                    'name' => $provider['name'],
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
