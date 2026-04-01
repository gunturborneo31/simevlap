<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realisasi', function (Blueprint $table) {
            $table->id();
            $table->morphs('realisaseable');
            $table->foreignId('opd_id')->nullable()->constrained('opds')->nullOnDelete();
            $table->enum('document_type', ['rpjmd','renstra','renja','dpa']);
            $table->integer('tahun');
            $table->integer('tahun_ke')->nullable();
            $table->integer('triwulan');
            $table->decimal('realisasi_fisik', 10, 2)->default(0);
            $table->decimal('realisasi_keuangan', 15, 2)->nullable();
            $table->decimal('sisa_anggaran', 15, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->constrained('users');
            $table->timestamps();
            $table->unique(['realisaseable_id','realisaseable_type','tahun','tahun_ke','triwulan'], 'realisasi_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realisasi');
    }
};
