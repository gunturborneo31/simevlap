<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sub_kegiatan', 'pagu')) {
            Schema::table('sub_kegiatan', function (Blueprint $table) {
                $table->bigInteger('pagu')->default(0)->after('nama_rincian');
            });
        }

        if (!Schema::hasColumn('komponen_anggaran', 'pagu')) {
            Schema::table('komponen_anggaran', function (Blueprint $table) {
                $table->bigInteger('pagu')->default(0)->after('nama_komponen');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sub_kegiatan', 'pagu')) {
            Schema::table('sub_kegiatan', function (Blueprint $table) {
                $table->dropColumn('pagu');
            });
        }

        if (Schema::hasColumn('komponen_anggaran', 'pagu')) {
            Schema::table('komponen_anggaran', function (Blueprint $table) {
                $table->dropColumn('pagu');
            });
        }
    }
};
