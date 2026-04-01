<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('opd_id')->nullable()->constrained('opds')->nullOnDelete();
            $table->foreignId('kepmen_id')->constrained('kepmen');
            $table->enum('document_type', ['rpjmd','renstra','renja','dpa']);
            $table->string('kode_rek', 50);
            $table->string('nama_rincian', 500);
            $table->decimal('pagu', 15, 2)->default(0);
            $table->integer('tahun_awal')->nullable();
            $table->integer('tahun_akhir')->nullable();
            $table->decimal('target_t1', 10, 2)->nullable();
            $table->decimal('target_t2', 10, 2)->nullable();
            $table->decimal('target_t3', 10, 2)->nullable();
            $table->decimal('target_t4', 10, 2)->nullable();
            $table->decimal('target_t5', 10, 2)->nullable();
            $table->decimal('target_tahunan', 10, 2)->nullable();
            $table->integer('tahun')->nullable();
            $table->text('catatan_evaluasi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_kegiatan');
    }
};
