<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program', function (Blueprint $table) {
            $table->foreignId('kepmen_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Tidak bisa revert ke NOT NULL jika sudah ada data null
    }
};
