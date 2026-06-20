<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Program;

class ProgramUnggulanSeeder extends Seeder
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
                'kode_rek' => $item['kode'] ?? null,
                'nama_rincian' => $item['uraian'] ?? null,
                'jenis_program' => 'unggulan',
                // Set field lain sesuai kebutuhan, default null/0
                'opd_id' => null,
                'kepmen_id' => 1, // Atur sesuai kebutuhan
                'document_type' => 'rpjmd',
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
            ]);
        }
    }
}
