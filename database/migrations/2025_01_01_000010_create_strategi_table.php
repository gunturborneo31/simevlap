<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sasaran_id')->constrained('sasaran')->cascadeOnDelete();
            $table->string('kode', 50);
            $table->text('uraian');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategi');
    }
};
