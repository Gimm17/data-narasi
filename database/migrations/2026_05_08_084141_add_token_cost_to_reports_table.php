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
        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedInteger('prompt_tokens')->nullable()->after('processing_time_ms');
            $table->unsignedInteger('completion_tokens')->nullable()->after('prompt_tokens');
            $table->unsignedInteger('total_tokens')->nullable()->after('completion_tokens');
            $table->decimal('cost_usd', 10, 6)->nullable()->after('total_tokens');
            $table->string('model_used')->nullable()->after('cost_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['prompt_tokens', 'completion_tokens', 'total_tokens', 'cost_usd', 'model_used']);
        });
    }
};
