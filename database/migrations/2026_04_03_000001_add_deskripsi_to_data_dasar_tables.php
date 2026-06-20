<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visi', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('uraian');
        });

        Schema::table('misi', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('uraian');
        });

        Schema::table('tujuan', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('uraian');
        });

        Schema::table('sasaran', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('uraian');
        });

        Schema::table('strategi', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('uraian');
        });

        Schema::table('arah_kebijakan', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('uraian');
        });

        Schema::table('program', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('nama_rincian');
        });

        Schema::table('kegiatan', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('nama_rincian');
        });

        Schema::table('sub_kegiatan', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('nama_rincian');
        });
    }

    public function down(): void
    {
        Schema::table('sub_kegiatan', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });

        Schema::table('kegiatan', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });

        Schema::table('program', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });

        Schema::table('arah_kebijakan', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });

        Schema::table('strategi', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });

        Schema::table('sasaran', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });

        Schema::table('tujuan', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });

        Schema::table('misi', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });

        Schema::table('visi', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });
    }
};
