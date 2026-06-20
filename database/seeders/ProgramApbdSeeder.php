<?php

namespace Database\Seeders;

use App\Models\Opd;
use App\Models\Program;
use Illuminate\Database\Seeder;

class ProgramApbdSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = base_path('referensi/apbd/program.json');
        
        if (!file_exists($jsonPath)) {
            $this->command->error('File referensi/apbd/program.json not found.');
            return;
        }

        $json = file_get_contents($jsonPath);
        $data = json_decode($json, true);

        // Ambil semua OPD dengan index kodenya
        $opds = Opd::all()->keyBy('kode');

        foreach ($data as $row) {
            $opd = $opds->get($row['KODE_SKPD']);
            
            if ($opd) {
                // Update or create program
                Program::updateOrCreate(
                    [
                        'kode_rek' => $row['KODE_PROGRAM'],
                        'opd_id' => $opd->id,
                    ],
                    [
                        'nama_rincian' => $row['NAMA_PROGRAM'],
                        'document_type' => 'dpa',
                        'kepmen_id' => 1, // Default ke KEPMENDAGRI No 900.1-2850 seperti permintaan sebelumnya
                        'pagu' => 0,
                    ]
                );
            }
        }
        
        $this->command->info('Program data dari referensi/apbd/program.json seeded successfully.');
    }
}
