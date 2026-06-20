<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_dasar_relasi', function (Blueprint $table) {
            $table->id();
            $table->string('child_type', 50);
            $table->unsignedBigInteger('child_id');
            $table->string('parent_type', 50);
            $table->unsignedBigInteger('parent_id');
            $table->timestamps();

            $table->unique(['child_type', 'child_id', 'parent_type', 'parent_id'], 'uq_data_dasar_relasi_pair');
            $table->index(['child_type', 'child_id'], 'idx_data_dasar_relasi_child');
            $table->index(['parent_type', 'parent_id'], 'idx_data_dasar_relasi_parent');
        });

        $now = now();

        $this->seedLegacyRelations('misi', 'visi', 'misi', 'id', 'visi_id', $now);
        $this->seedLegacyRelations('tujuan', 'misi', 'tujuan', 'id', 'misi_id', $now);
        $this->seedLegacyRelations('sasaran', 'tujuan', 'sasaran', 'id', 'tujuan_id', $now);
        $this->seedLegacyRelations('strategi', 'sasaran', 'strategi', 'id', 'sasaran_id', $now);
        $this->seedLegacyRelations('arah_kebijakan', 'strategi', 'arah-kebijakan', 'id', 'strategi_id', $now);
        $this->seedLegacyRelations('kegiatan', 'program', 'kegiatan', 'id', 'program_id', $now);
        $this->seedLegacyRelations('sub_kegiatan', 'kegiatan', 'sub-kegiatan', 'id', 'kegiatan_id', $now);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_dasar_relasi');
    }

    private function seedLegacyRelations(
        string $childTable,
        string $parentType,
        string $childType,
        string $childKey,
        string $parentKey,
        $now
    ): void {
        $records = DB::table($childTable)
            ->whereNotNull($parentKey)
            ->select([$childKey, $parentKey])
            ->get()
            ->map(fn ($row) => [
                'child_type' => $childType,
                'child_id' => $row->{$childKey},
                'parent_type' => $parentType,
                'parent_id' => $row->{$parentKey},
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if (!empty($records)) {
            DB::table('data_dasar_relasi')->insert($records);
        }
    }
};
