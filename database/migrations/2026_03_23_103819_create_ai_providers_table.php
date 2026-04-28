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
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // nama provider: gemini, kimi, glm, claude
            $table->string('display_name'); // Nama tampilan: Gemini Free, Kimi API, etc
            $table->string('api_key')->nullable(); // API key (disimpan encrypted)
            $table->boolean('is_active')->default(true); // Apakah provider aktif
            $table->unsignedInteger('priority')->default(0); // Urutan fallback (0 = highest)
            $table->unsignedInteger('daily_limit')->nullable(); // Batas harian request
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
