<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ikus', function (Blueprint $table) {
            $table->id();
            $table->string('indikator');
            $table->string('satuan');
            $table->string('capaian_2024');
            $table->string('target_2025');
            $table->string('target_2026');
            $table->string('target_2027');
            $table->string('target_2028');
            $table->string('target_2029');
            $table->string('target_2030');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('ikus');
    }
};
