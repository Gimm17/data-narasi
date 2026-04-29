<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Memungkinkan anonymous user (tanpa login) mengupload dan melihat report.
     * Tracking via visitor_token yang disimpan di cookie browser.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // user_id nullable — guest uploads tanpa login
            $table->foreignId('user_id')->nullable()->change();

            // Token unik per visitor browser (UUID, stored in cookie)
            $table->string('visitor_token', 64)->nullable()->after('user_id');
            $table->index('visitor_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['visitor_token']);
            $table->dropColumn('visitor_token');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
