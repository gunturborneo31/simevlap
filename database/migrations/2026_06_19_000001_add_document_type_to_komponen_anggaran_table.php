<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('komponen_anggaran', function (Blueprint $table) {
            if (!Schema::hasColumn('komponen_anggaran', 'document_type')) {
                $table->enum('document_type', ['dpa', 'renja'])->default('dpa')->after('opd_id');
            }
        });
    }

    public function down()
    {
        Schema::table('komponen_anggaran', function (Blueprint $table) {
            if (Schema::hasColumn('komponen_anggaran', 'document_type')) {
                $table->dropColumn('document_type');
            }
        });
    }
};
