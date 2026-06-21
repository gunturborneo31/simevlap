<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realisasi', function (Blueprint $table) {
            if (!Schema::hasColumn('realisasi', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('catatan');
            }

            if (!Schema::hasColumn('realisasi', 'catatan_verifikator')) {
                $table->text('catatan_verifikator')->nullable()->after('is_verified');
            }

            if (!Schema::hasColumn('realisasi', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('catatan_verifikator')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('realisasi', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('realisasi', function (Blueprint $table) {
            if (Schema::hasColumn('realisasi', 'verified_by')) {
                $table->dropConstrainedForeignId('verified_by');
            }

            if (Schema::hasColumn('realisasi', 'verified_at')) {
                $table->dropColumn('verified_at');
            }

            if (Schema::hasColumn('realisasi', 'catatan_verifikator')) {
                $table->dropColumn('catatan_verifikator');
            }

            if (Schema::hasColumn('realisasi', 'is_verified')) {
                $table->dropColumn('is_verified');
            }
        });
    }
};
