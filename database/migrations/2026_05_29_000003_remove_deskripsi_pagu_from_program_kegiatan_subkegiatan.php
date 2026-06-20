<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('program', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->dropColumn(['deskripsi', 'pagu']);
        });
        Schema::table('sub_kegiatan', function (Blueprint $table) {
            $table->dropColumn(['deskripsi', 'pagu']);
        });
    }

    public function down(): void
    {
        Schema::table('program', function (Blueprint $table) {
            $table->text('deskripsi')->nullable();
        });
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->text('deskripsi')->nullable();
            $table->decimal('pagu', 15, 2)->default(0);
        });
        Schema::table('sub_kegiatan', function (Blueprint $table) {
            $table->text('deskripsi')->nullable();
            $table->decimal('pagu', 15, 2)->default(0);
        });
    }
};
