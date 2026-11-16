<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan pilihan reasoning_effort per provider.
 *
 * Model reasoning (deepseek-v4.1-flash, glm-5.3, gpt-5.5, dst.) memakai
 * token berpikir yang ikut memakan jatah output. Nilai ini bisa diubah dari
 * admin panel untuk membandingkan hasil antar tingkat.
 *
 * Nilai yang diketahui diterima API: none, minimal, low, medium, high,
 * xhigh, max — tetapi TIDAK semua model mendukung semua nilai
 * (deepseek-v4.1-flash menolak "minimal" dan "medium").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->string('reasoning_effort', 10)->nullable()->after('model_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn('reasoning_effort');
        });
    }
};
