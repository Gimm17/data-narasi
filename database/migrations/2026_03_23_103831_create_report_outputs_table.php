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
        Schema::create('report_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->text('narrative'); // Hasil analisis AI dalam Bahasa Indonesia
            $table->json('summary_statistics')->nullable(); // Ringkasan statistik (JSON)
            $table->json('chart_paths')->nullable(); // Path ke file chart yang dihasilkan (JSON array)
            $table->json('insights')->nullable(); // Insights utama (JSON array)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_outputs');
    }
};
