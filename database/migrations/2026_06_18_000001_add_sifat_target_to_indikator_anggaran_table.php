<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('indikator_anggaran', function (Blueprint $table) {
            $table->string('sifat_indikator', 30)->nullable()->after('nama_indikator');
            $table->string('target_indikator', 100)->nullable()->after('sifat_indikator');
        });
    }

    public function down(): void
    {
        Schema::table('indikator_anggaran', function (Blueprint $table) {
            $table->dropColumn(['sifat_indikator', 'target_indikator']);
        });
    }
};
