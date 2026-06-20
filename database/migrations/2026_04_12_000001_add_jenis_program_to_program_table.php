<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program', function (Blueprint $table) {
            $table->string('jenis_program', 30)->default('program');
        });

        DB::table('program')
            ->whereNull('jenis_program')
            ->update(['jenis_program' => 'program']);
    }

    public function down(): void
    {
        Schema::table('program', function (Blueprint $table) {
            $table->dropColumn('jenis_program');
        });
    }
};