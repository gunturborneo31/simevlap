<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('program', function (Blueprint $table) {
            if (Schema::hasColumn('program', 'deskripsi')) {
                $table->dropColumn('deskripsi');
            }
        });
        Schema::table('kegiatan', function (Blueprint $table) {
            if (Schema::hasColumn('kegiatan', 'deskripsi')) {
                $table->dropColumn('deskripsi');
            }
            if (Schema::hasColumn('kegiatan', 'pagu')) {
                $table->dropColumn('pagu');
            }
        });
        Schema::table('sub_kegiatan', function (Blueprint $table) {
            if (Schema::hasColumn('sub_kegiatan', 'deskripsi')) {
                $table->dropColumn('deskripsi');
            }
            if (Schema::hasColumn('sub_kegiatan', 'pagu')) {
                $table->dropColumn('pagu');
            }
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
