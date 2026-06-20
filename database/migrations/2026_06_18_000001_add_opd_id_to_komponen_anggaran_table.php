<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_anggaran', function (Blueprint $table) {
            $table->foreignId('opd_id')->nullable()->after('id')->constrained('opds')->nullOnDelete();
            $table->string('kode_program')->nullable()->after('kode'); // kode program dari referensi (misal: 1.01.02)
        });
    }

    public function down(): void
    {
        Schema::table('komponen_anggaran', function (Blueprint $table) {
            $table->dropForeign(['opd_id']);
            $table->dropColumn(['opd_id', 'kode_program']);
        });
    }
};
