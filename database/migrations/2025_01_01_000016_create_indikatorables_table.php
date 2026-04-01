<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indikatorables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_id')->constrained('indikator')->cascadeOnDelete();
            $table->morphs('indicatorable');
            $table->decimal('target', 10, 2)->default(0);
            $table->decimal('realisasi', 10, 2)->nullable();
            $table->integer('tahun');
            $table->integer('triwulan')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indikatorables');
    }
};
