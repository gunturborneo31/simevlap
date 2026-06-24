<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Opd;
use App\Models\Program;
use App\Models\Indikator;

class InsertSampleIndicatorsSeeder extends Seeder
{
    public function run()
    {
        $opd = Opd::where('nama', 'like', '%Badan Kepegawaian%')
            ->orWhere('nama', 'like', '%BKPSDM%')
            ->first();

        if (! $opd) {
            $this->command->error('OPD "Badan Kepegawaian..." not found. Aborting.');
            return;
        }

        $programName = 'PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA';

        $program = Program::where('opd_id', $opd->id)
            ->where('nama_rincian', 'like', "%PROGRAM PENUNJANG URUSAN%")
            ->first();

        if (! $program) {
            $this->command->warn('Program not found for OPD — creating a new program record.');
            $program = Program::create([
                'opd_id' => $opd->id,
                'kode_rek' => '99.99.99',
                'nama_rincian' => $programName,
                'document_type' => 'rpjmd',
                'tahun' => 2026,
                'pagu' => 0,
            ]);
        }

        $examples = [
            ['uraian' => 'Persentase ASN yang mengikuti pelatihan kompetensi per tahun', 'satuan' => '%', 'target' => 70],
            ['uraian' => 'Rata-rata jam pelatihan per ASN per tahun', 'satuan' => 'jam', 'target' => 24],
            ['uraian' => 'Persentase jabatan terisi sesuai kompetensi', 'satuan' => '%', 'target' => 85],
            ['uraian' => 'Waktu rata-rata proses rekrutmen', 'satuan' => 'hari', 'target' => 60],
            ['uraian' => 'Persentase penilaian kinerja (SKP) selesai tepat waktu', 'satuan' => '%', 'target' => 95],
            ['uraian' => 'Indeks kepuasan pegawai terhadap layanan SDM', 'satuan' => 'skor', 'target' => 4.0],
            ['uraian' => 'Persentase adopsi e-HRM (digitalisasi proses SDM)', 'satuan' => '%', 'target' => 80],
            ['uraian' => 'Tingkat perputaran pegawai (turnover)', 'satuan' => '%', 'target' => 5],
        ];

        foreach ($examples as $ex) {
            $indikator = Indikator::firstOrCreate(
                ['uraian' => $ex['uraian']],
                [
                    'satuan' => $ex['satuan'] ?? '-',
                    'jenis_indikator' => 'Program Prioritas',
                    'jenis' => 'outcome',
                    'sifat' => 'maximize',
                ]
            );

            DB::table('indikatorables')->updateOrInsert(
                [
                    'indikator_id' => $indikator->id,
                    'indicatorable_type' => Program::class,
                    'indicatorable_id' => $program->id,
                    'tahun' => 2026,
                ],
                [
                    'target' => $ex['target'],
                    'realisasi' => null,
                    'triwulan' => null,
                    'catatan' => 'Sample indicator inserted by seeder',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Inserted/updated sample indicators for program: ' . $program->nama_rincian . ' (OPD: ' . $opd->nama . ')');
    }
}
