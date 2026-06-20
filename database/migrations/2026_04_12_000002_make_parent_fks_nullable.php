<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('misi', function (Blueprint $table) {
            $table->foreignId('visi_id')->nullable()->change();
        });

        Schema::table('tujuan', function (Blueprint $table) {
            $table->foreignId('misi_id')->nullable()->change();
        });

        Schema::table('sasaran', function (Blueprint $table) {
            $table->foreignId('tujuan_id')->nullable()->change();
        });

        Schema::table('strategi', function (Blueprint $table) {
            $table->foreignId('sasaran_id')->nullable()->change();
        });

        Schema::table('arah_kebijakan', function (Blueprint $table) {
            $table->foreignId('strategi_id')->nullable()->change();
        });

        Schema::table('kegiatan', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->change();
            $table->foreignId('kepmen_id')->nullable()->change();
        });

        Schema::table('sub_kegiatan', function (Blueprint $table) {
            $table->foreignId('kegiatan_id')->nullable()->change();
            $table->foreignId('kepmen_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Reverting nullable to non-nullable is intentionally omitted
        // as it would fail if existing NULL values are present.
    }
};
