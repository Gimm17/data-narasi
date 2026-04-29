<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProviderTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function createUser(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        AIProvider::create([
            'name' => 'Test Provider',
            'slug' => 'test',
            'model_id' => 'test-model',
            'base_url' => 'https://api.test.com',
            'api_key_env' => 'TEST_API_KEY',
            'priority' => 1,
            'is_enabled' => true,
            'max_tokens' => 1024,
            'timeout_seconds' => 30,
        ]);
    }

    public function test_non_admin_cannot_access_admin_panel(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.ai-providers.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_panel(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.ai-providers.index'));
        $response->assertStatus(200);
    }

    public function test_admin_can_toggle_provider(): void
    {
        $admin = $this->createAdmin();
        $provider = AIProvider::first();

        $response = $this->actingAs($admin)->patchJson(
            route('admin.ai-providers.toggle', $provider->id)
        );

        $response->assertStatus(200);
        $provider->refresh();
        $this->assertFalse($provider->is_enabled);
    }

    public function test_guest_cannot_access_admin_panel(): void
    {
        $response = $this->get(route('admin.ai-providers.index'));
        $response->assertRedirect(route('login'));
    }
}
