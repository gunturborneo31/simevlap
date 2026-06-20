<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_program_annotations', function (Blueprint $table) {
            $table->id();
            $table->string('view', 80);
            $table->string('table_name', 40);
            $table->string('basis', 40);
            $table->integer('tahun')->nullable();
            $table->string('entitas', 255);
            $table->string('program_kode', 120)->nullable();
            $table->string('program_nama', 255);
            $table->text('faktor_penghambat')->nullable();
            $table->text('faktor_pendorong')->nullable();
            $table->text('faktor_tindak_lanjut')->nullable();
            $table->timestamps();

            $table->index(['view', 'table_name', 'basis', 'tahun'], 'resume_ann_scope_idx');
            $table->index(['entitas', 'program_kode'], 'resume_ann_program_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_program_annotations');
    }
};
