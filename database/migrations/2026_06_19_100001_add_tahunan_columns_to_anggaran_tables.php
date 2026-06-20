<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('komponen_anggaran', function (Blueprint $table) {
            $table->json('pagu_tahunan')->nullable()->after('pagu');
        });

        Schema::table('indikator_anggaran', function (Blueprint $table) {
            $table->json('target_tahunan')->nullable()->after('target_indikator');
        });
    }

    public function down(): void
    {
        Schema::table('komponen_anggaran', function (Blueprint $table) {
            $table->dropColumn('pagu_tahunan');
        });

        Schema::table('indikator_anggaran', function (Blueprint $table) {
            $table->dropColumn('target_tahunan');
        });
    }
};
