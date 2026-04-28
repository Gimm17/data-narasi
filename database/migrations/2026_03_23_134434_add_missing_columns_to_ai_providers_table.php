<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            // Ubah display_name jadi nullable (untuk compatibility)
            $table->string('display_name')->nullable()->change();

            // Tambahkan kolom yang kurang
            $table->string('model_id')->nullable()->after('name');
            $table->string('base_url')->nullable()->after('model_id');
            $table->string('api_key_env')->nullable()->after('base_url');
            $table->boolean('is_enabled')->default(true)->after('is_active');
            $table->unsignedInteger('max_tokens')->default(1024)->after('priority');
            $table->unsignedInteger('timeout_seconds')->default(30)->after('max_tokens');
            $table->unsignedInteger('total_calls')->default(0)->after('timeout_seconds');
            $table->unsignedInteger('total_errors')->default(0)->after('total_calls');
            $table->timestamp('last_used_at')->nullable()->after('total_errors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            // Ubah kembali display_name jadi not nullable
            $table->string('display_name')->nullable(false)->change();

            $table->dropColumn([
                'model_id',
                'base_url',
                'api_key_env',
                'is_enabled',
                'max_tokens',
                'timeout_seconds',
                'total_calls',
                'total_errors',
                'last_used_at',
            ]);
        });
    }
};
