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
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_provider_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('prompt_length'); // Jumlah karakter prompt
            $table->unsignedInteger('response_length'); // Jumlah karakter response
            $table->unsignedInteger('tokens_used')->nullable(); // Jumlah token yang digunakan
            $table->decimal('cost', 10, 6)->nullable(); // Biaya dalam USD
            $table->unsignedInteger('response_time_ms'); // Waktu respon dalam milidetik
            $table->boolean('is_success')->default(true); // Apakah request berhasil
            $table->text('error_message')->nullable(); // Pesan error jika gagal
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
