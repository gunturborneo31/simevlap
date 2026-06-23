<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestIndicatorSeeder extends Seeder
{
    public function run()
    {
        // Insert sample komponen_anggaran rows and indikator_anggaran linked to them
        $samples = [
            [
                'opd_id' => 5,
                'kode_program' => '1.05.01.2.01',
                'kode' => '1.05.01.2.01',
                'jenis' => 'kegiatan',
                'sub_unit' => '',
                'urusan' => '',
                'bidang_urusan' => '',
                'nama_komponen' => 'Penyelenggaraan Operasi Pencarian dan Pertolongan',
                'document_type' => 'dpa',
                'tahun' => 2026,
                'pagu' => 0,
            ],
            [
                'opd_id' => 19,
                'kode_program' => '5.02.02.2.01',
                'kode' => '5.02.02.2.01',
                'jenis' => 'kegiatan',
                'sub_unit' => '',
                'urusan' => '',
                'bidang_urusan' => '',
                'nama_komponen' => 'Pengembangan Inovasi dan Teknologi',
                'document_type' => 'dpa',
                'tahun' => 2026,
                'pagu' => 0,
            ],
        ];

        foreach ($samples as $s) {
            $id = DB::table('komponen_anggaran')->insertGetId(array_merge(
                collect($s)->only(['opd_id','kode_program','kode','jenis','sub_unit','urusan','bidang_urusan','nama_komponen','document_type','tahun','pagu'])->toArray(),
                ['created_at' => now(), 'updated_at' => now()]
            ));

            DB::table('indikator_anggaran')->insert([
                'komponen_anggaran_id' => $id,
                'nama_indikator' => 'Persentase capaian target kegiatan',
                'satuan' => 'persen',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('indikator_anggaran')->insert([
                'komponen_anggaran_id' => $id,
                'nama_indikator' => 'Jumlah layanan yang diberikan',
                'satuan' => 'unit',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
