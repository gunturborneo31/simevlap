<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE komponen_anggaran MODIFY document_type ENUM('dpa','renja','renstra') NOT NULL DEFAULT 'dpa'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE komponen_anggaran MODIFY document_type ENUM('dpa','renja') NOT NULL DEFAULT 'dpa'");
    }
};
