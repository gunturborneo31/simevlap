<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_program_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_program_annotation_id')
                ->constrained('resume_program_annotations')
                ->cascadeOnDelete();
            $table->string('sub_kegiatan_kode', 120)->nullable();
            $table->string('sub_kegiatan_nama', 255)->nullable();
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['resume_program_annotation_id', 'sub_kegiatan_kode'], 'resume_evd_subkeg_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_program_evidences');
    }
};
