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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable(); // Judul laporan (opsional, auto-generate dari filename)
            $table->string('original_filename'); // Nama file asli yang diupload
            $table->string('original_path'); // Path file yang disimpan di storage
            $table->string('clean_path')->nullable(); // Path file hasil cleaning
            $table->string('status')->default('pending'); // pending, processing, done, failed
            $table->string('dataset_type')->default('umum'); // Tipe analisis: penjualan, keuangan, dll
            $table->string('tone')->default('formal'); // Tone narasi: formal, casual, dll
            $table->unsignedInteger('total_rows')->default(0); // Jumlah baris data asli
            $table->unsignedInteger('clean_rows')->default(0); // Jumlah baris setelah cleaning
            $table->json('summary_stats')->nullable(); // Ringkasan statistik (JSON)
            $table->json('cleaning_log')->nullable(); // Log langkah cleaning (JSON)
            $table->json('chart_paths')->nullable(); // Path ke file chart (JSON array)
            $table->text('ai_narrative')->nullable(); // Narasi AI hasil analisis
            $table->string('ai_provider_used')->nullable(); // Provider AI yang digunakan
            $table->unsignedInteger('processing_time_ms')->nullable(); // Waktu proses dalam milidetik
            $table->text('error_message')->nullable(); // Pesan error jika status = failed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
