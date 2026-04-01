<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indikator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->nullable()->constrained('opds')->nullOnDelete();
            $table->enum('document_type', ['rpjmd','renstra','renja','dpa']);
            $table->enum('jenis_indikator', ['IKU','IKK','Program Prioritas','Program Aksi']);
            $table->text('uraian');
            $table->string('satuan', 100);
            $table->enum('jenis', ['input','process','output','outcome','impact']);
            $table->enum('sifat', ['maximize','minimize','stabilize']);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indikator');
    }
};
