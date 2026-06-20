<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $legacyUnique = DB::select("SHOW INDEX FROM program WHERE Key_name = 'program_kode_rek_opd_id_unique'");
        $newUnique = DB::select("SHOW INDEX FROM program WHERE Key_name = 'program_kode_rek_opd_id_document_type_unique'");

        Schema::table('program', function (Blueprint $table) use ($legacyUnique, $newUnique) {
            if (!empty($legacyUnique)) {
                $table->dropUnique('program_kode_rek_opd_id_unique');
            }

            if (empty($newUnique)) {
                $table->unique(['kode_rek', 'opd_id', 'document_type'], 'program_kode_rek_opd_id_document_type_unique');
            }
        });
    }

    public function down(): void
    {
        $legacyUnique = DB::select("SHOW INDEX FROM program WHERE Key_name = 'program_kode_rek_opd_id_unique'");
        $newUnique = DB::select("SHOW INDEX FROM program WHERE Key_name = 'program_kode_rek_opd_id_document_type_unique'");

        Schema::table('program', function (Blueprint $table) use ($legacyUnique, $newUnique) {
            if (!empty($newUnique)) {
                $table->dropUnique('program_kode_rek_opd_id_document_type_unique');
            }

            if (empty($legacyUnique)) {
                $table->unique(['kode_rek', 'opd_id'], 'program_kode_rek_opd_id_unique');
            }
        });
    }
};
