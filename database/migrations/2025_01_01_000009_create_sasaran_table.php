<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sasaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tujuan_id')->constrained('tujuan')->cascadeOnDelete();
            $table->string('kode', 50);
            $table->text('uraian');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sasaran');
    }
};
