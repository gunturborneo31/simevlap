<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Program;

class ProgramUnggulanToAksiSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('json/program_unggulan.json');
        $data = json_decode(file_get_contents($jsonPath), true);

        if (!is_array($data)) {
            throw new \Exception('Format JSON program_unggulan.json tidak valid.');
        }

        foreach ($data as $item) {
            Program::create([
                'opd_id' => null,
                'kepmen_id' => null,
                'document_type' => 'rpjmd',
                'jenis_program' => 'program-aksi',
                'kode_rek' => $item['kode'] ?? null,
                'nama_rincian' => $item['uraian'] ?? null,
                'pagu' => 0,
                'tahun_awal' => null,
                'tahun_akhir' => null,
                'target_t1' => null,
                'target_t2' => null,
                'target_t3' => null,
                'target_t4' => null,
                'target_t5' => null,
                'target_tahunan' => null,
                'tahun' => null,
                'catatan_evaluasi' => null,
                'is_prioritas' => true,
            ]);
        }
    }
}
