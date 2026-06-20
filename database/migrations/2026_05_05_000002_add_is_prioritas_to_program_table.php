<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program', function (Blueprint $table) {
            $table->boolean('is_prioritas')->default(false)->after('jenis_program');
        });
    }

    public function down(): void
    {
        Schema::table('program', function (Blueprint $table) {
            $table->dropColumn('is_prioritas');
        });
    }
};
