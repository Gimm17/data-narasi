<?php

namespace Tests\Feature;

use App\Models\AIProvider;
use App\Models\Report;
use App\Models\User;
use App\Services\Analysis\ApiKeyChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Uji endpoint pemeriksaan API key di admin panel.
 * Menggantikan ReportCallbackTest — endpoint callback Python sudah dihapus
 * karena seluruh pipeline kini berjalan di dalam PHP.
 */
class ApiKeyCheckTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function createProvider(string $slug = 'gemini'): AIProvider
    {
        $defaults = [
            'gemini' => ['label' => 'Gemini', 'model' => 'gemini-3.5-flash', 'url' => 'https://generativelanguage.googleapis.com'],
            'kimi' => ['label' => 'Kimi', 'model' => 'moonshot-v1-8k', 'url' => 'https://api.moonshot.cn/v1'],
            'claude' => ['label' => 'Claude', 'model' => 'claude-sonnet-4-20250514', 'url' => 'https://api.anthropic.com'],
            'limitrouter' => ['label' => 'LimitRouter', 'model' => 'deepseek-v4.1-flash', 'url' => 'https://limitrouter.com/v1'],
        ];
        $config = $defaults[$slug] ?? ['label' => ucfirst($slug), 'model' => $slug . '-model', 'url' => 'https://api.test'];

        return AIProvider::create([
            'name' => $config['label'],
            'slug' => $slug,
            'model_id' => $config['model'],
            'base_url' => $config['url'],
            'api_key_env' => strtoupper($slug) . '_API_KEY',
            'priority' => 1,
            'is_enabled' => true,
            'max_tokens' => 1024,
            'timeout_seconds' => 30,
        ]);
    }

    public function test_check_key_mengembalikan_valid_saat_provider_merespons_ok(): void
    {
        $admin = $this->createAdmin();
        $provider = $this->createProvider();

        config(['ai.keys.gemini' => 'test-key']);

        Http::fake([
            '*generativelanguage*' => Http::response([
                'models' => [
                    ['name' => 'models/gemini-3.5-flash'],
                    ['name' => 'models/gemini-3.5-pro'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.ai-providers.check-key', $provider->id));

        $response->assertStatus(200)
            ->assertJson(['valid' => true, 'models_count' => 2, 'tier' => 'free']);
    }

    public function test_check_key_melaporkan_key_tidak_valid(): void
    {
        $admin = $this->createAdmin();
        $provider = $this->createProvider();

        config(['ai.keys.gemini' => 'kunci-salah']);

        Http::fake([
            '*generativelanguage*' => Http::response(['error' => ['message' => 'API key not valid']], 401),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.ai-providers.check-key', $provider->id));

        $response->assertStatus(200)
            ->assertJson(['valid' => false]);

        $this->assertStringContainsString('401', $response->json('error'));
    }

    public function test_check_key_memberi_pesan_jelas_bila_key_kosong(): void
    {
        $admin = $this->createAdmin();
        $provider = $this->createProvider();

        // String kosong membuat controller berhenti sebelum fallback env(),
        // sehingga hasil test tidak bergantung pada .env mesin pengembang.
        config(['ai.keys.gemini' => '']);

        Http::fake();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.ai-providers.check-key', $provider->id));

        $response->assertStatus(200)
            ->assertJson(['valid' => false]);

        $this->assertStringContainsString('belum diset', (string) $response->json('error'));
    }

    public function test_check_key_tidak_membocorkan_api_key(): void
    {
        $admin = $this->createAdmin();
        $provider = $this->createProvider('kimi');

        $secret = 'sk-rahasia-panjang-sekali-1234567890';
        config(['ai.keys.kimi' => $secret]);

        // Error body memuat key — harus di-redaksi
        Http::fake([
            '*moonshot*' => Http::response(
                ['error' => ['message' => "Invalid key: {$secret}"]],
                401
            ),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.ai-providers.check-key', $provider->id));

        $response->assertStatus(200)
            ->assertJson(['valid' => false]);

        $this->assertStringNotContainsString($secret, (string) $response->json('error'));
    }

    public function test_checker_menandai_rate_limit_sebagai_key_valid(): void
    {
        config(['ai.keys.claude' => 'sk-ant-test']);

        Http::fake([
            '*anthropic*' => Http::response(['error' => ['message' => 'rate limited']], 429),
        ]);

        $result = app(ApiKeyChecker::class)->check('claude', 'sk-ant-test', 'claude-sonnet-4-20250514');

        $this->assertTrue($result['valid']);
        $this->assertEquals('rate_limited', $result['rate_limit']['status'] ?? null);
    }

    public function test_endpoint_callback_lama_sudah_tidak_ada(): void
    {
        $report = Report::create([
            'user_id' => null,
            'visitor_token' => 'test-token',
            'title' => 'Test',
            'original_filename' => 'test.csv',
            'original_path' => 'uploads/test.csv',
            'status' => 'processing',
            'dataset_type' => 'umum',
            'tone' => 'formal',
            'total_rows' => 0,
            'clean_rows' => 0,
        ]);

        $this->postJson("/api/v1/reports/{$report->id}/callback", ['status' => 'success'])
            ->assertStatus(404);
    }

    // ── Daftar model langsung dari provider (GET /models) ──

    public function test_daftar_model_diambil_dari_endpoint_provider(): void
    {
        $admin = $this->createAdmin();
        $provider = $this->createProvider('limitrouter');
        config(['ai.keys.limitrouter' => 'sk-lr-test']);

        Http::fake([
            'limitrouter.com/v1/models' => Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'deepseek-v4.1-flash', 'owned_by' => 'limitrouter', 'capabilities' => ['vision' => true]],
                    ['id' => 'glm-5.3', 'owned_by' => 'limitrouter', 'capabilities' => ['vision' => false]],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.ai-providers.models', $provider->id));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
                'current_model' => 'deepseek-v4.1-flash',
            ]);

        $ids = array_column($response->json('models'), 'id');
        $this->assertContains('deepseek-v4.1-flash', $ids);
        $this->assertContains('glm-5.3', $ids);
    }

    public function test_daftar_model_memberi_error_jelas_saat_gagal(): void
    {
        $admin = $this->createAdmin();
        $provider = $this->createProvider('limitrouter');
        config(['ai.keys.limitrouter' => 'sk-lr-test']);

        Http::fake([
            'limitrouter.com/v1/models' => Http::response(['error' => ['message' => 'unauthorized']], 401),
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.ai-providers.models', $provider->id));

        $response->assertStatus(502)
            ->assertJson(['success' => false, 'models' => []]);

        $this->assertStringContainsString('401', (string) $response->json('error'));
    }

    public function test_ganti_model_lewat_endpoint_update(): void
    {
        $admin = $this->createAdmin();
        $provider = $this->createProvider('limitrouter');

        $this->actingAs($admin)
            ->putJson(route('admin.ai-providers.update', $provider->id), [
                'model_id' => 'deepseek-v4-flash',
            ])
            ->assertStatus(200);

        $this->assertEquals('deepseek-v4-flash', $provider->fresh()->model_id);
    }

    public function test_non_admin_tidak_bisa_mengambil_daftar_model(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $provider = $this->createProvider('limitrouter');

        $this->actingAs($user)
            ->getJson(route('admin.ai-providers.models', $provider->id))
            ->assertStatus(403);
    }

    // ── reasoning_effort ──

    public function test_reasoning_effort_dapat_disimpan_dan_dikosongkan(): void
    {
        $admin = $this->createAdmin();
        $provider = $this->createProvider('limitrouter');

        $this->actingAs($admin)
            ->putJson(route('admin.ai-providers.update', $provider->id), ['reasoning_effort' => 'low'])
            ->assertStatus(200);
        $this->assertEquals('low', $provider->fresh()->reasoning_effort);

        // null = kembali ke default provider
        $this->actingAs($admin)
            ->putJson(route('admin.ai-providers.update', $provider->id), ['reasoning_effort' => null])
            ->assertStatus(200);
        $this->assertNull($provider->fresh()->reasoning_effort);
    }

    public function test_reasoning_effort_tidak_dikenal_ditolak(): void
    {
        $admin = $this->createAdmin();
        $provider = $this->createProvider('limitrouter');

        $this->actingAs($admin)
            ->putJson(route('admin.ai-providers.update', $provider->id), ['reasoning_effort' => 'super-tinggi'])
            ->assertStatus(422);
    }

    public function test_reasoning_effort_dikirim_ke_provider(): void
    {
        config(['ai.keys.limitrouter' => 'sk-lr-test']);

        $captured = [];
        Http::fake([
            'limitrouter.com/v1/chat/completions' => function ($request) use (&$captured) {
                $captured[] = $request->data();

                return Http::response([
                    'model' => 'deepseek-v4.1-flash',
                    'choices' => [[
                        'message' => ['content' => str_repeat(
                            'Total penjualan 1.234.567 dari 198 transaksi dengan rata-rata 6.235. ', 10
                        )],
                        'finish_reason' => 'stop',
                    ]],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
                ], 200);
            },
        ]);

        $client = new \App\Services\Analysis\AiClient();
        $client->generate('prompt', 'sistem', 512, [[
            'slug' => 'limitrouter',
            'model_id' => 'deepseek-v4.1-flash',
            'reasoning_effort' => 'low',
        ]]);

        $this->assertNotEmpty($captured);
        $this->assertEquals('low', $captured[0]['reasoning_effort'] ?? null);
    }

    public function test_bentuk_nested_dipakai_saat_bentuk_flat_ditolak(): void
    {
        config(['ai.keys.limitrouter' => 'sk-lr-test']);

        $seen = [];
        Http::fake([
            'limitrouter.com/v1/chat/completions' => function ($request) use (&$seen) {
                $data = $request->data();
                $seen[] = $data;

                // Bentuk flat untuk nilai ini ditolak provider
                if (isset($data['reasoning_effort'])) {
                    return Http::response(['error' => ['message' => 'Invalid request', 'code' => 'invalid_request']], 400);
                }

                return Http::response([
                    'model' => 'deepseek-v4.1-flash',
                    'choices' => [[
                        'message' => ['content' => str_repeat(
                            'Total penjualan 1.234.567 dari 198 transaksi dengan rata-rata 6.235. ', 10
                        )],
                        'finish_reason' => 'stop',
                    ]],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
                ], 200);
            },
        ]);

        $res = (new \App\Services\Analysis\AiClient())->generate('prompt', 'sistem', 512, [[
            'slug' => 'limitrouter',
            'model_id' => 'deepseek-v4.1-flash',
            'reasoning_effort' => 'medium',
        ]]);

        $this->assertTrue($res['success']);
        $this->assertCount(2, $seen);
        $this->assertEquals('medium', $seen[0]['reasoning_effort']);
        $this->assertSame(['effort' => 'medium'], $seen[1]['reasoning'] ?? null);
    }

    public function test_compare_reasoning_mengembalikan_hasil_per_tingkat(): void
    {
        $admin = $this->createAdmin();
        $provider = $this->createProvider('limitrouter');
        config(['ai.keys.limitrouter' => 'sk-lr-test']);

        Http::fake([
            '*' => Http::response([
                'model' => 'deepseek-v4.1-flash',
                'choices' => [[
                    'message' => ['content' => str_repeat(
                        'Total penjualan 1.234.567 dari 198 transaksi dengan rata-rata 6.235. ', 10
                    )],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('admin.ai-providers.compare-reasoning', $provider->id),
            ['prompt' => 'Buat narasi analisis penjualan singkat.', 'efforts' => ['none', 'high']]
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'model' => 'deepseek-v4.1-flash']);

        $efforts = array_column($response->json('results'), 'effort');
        $this->assertEquals(['none', 'high'], $efforts);
        $this->assertArrayHasKey('words', $response->json('results')[0]);
        $this->assertArrayHasKey('response_time_ms', $response->json('results')[0]);
    }

    public function test_compare_reasoning_menolak_tingkat_tidak_dikenal(): void
    {
        $admin = $this->createAdmin();
        $provider = $this->createProvider('limitrouter');
        config(['ai.keys.limitrouter' => 'sk-lr-test']);

        $this->actingAs($admin)->postJson(
            route('admin.ai-providers.compare-reasoning', $provider->id),
            ['prompt' => 'Buat narasi analisis penjualan singkat.', 'efforts' => ['none', 'ultra']]
        )->assertStatus(422);
    }
}
