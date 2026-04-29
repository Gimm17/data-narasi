<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Populate slug dari api_key_env yang sudah ada
        $envToSlug = [
            'GEMINI_API_KEY' => 'gemini',
            'KIMI_API_KEY' => 'kimi',
            'GLM_API_KEY' => 'glm',
            'NVIDIA_API_KEY' => 'nvidia',
            'MINIMAX_API_KEY' => 'minimax',
            'CLAUDE_API_KEY' => 'claude',
        ];

        foreach ($envToSlug as $env => $slug) {
            DB::table('ai_providers')
                ->where('api_key_env', $env)
                ->update(['slug' => $slug]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
