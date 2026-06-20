<?php

namespace Database\Seeders;

use App\Models\KomponenAnggaran;
use App\Models\Opd;
use Illuminate\Database\Seeder;

/**
 * Seeder untuk mengisi data program DPA dari referensi/apbd/program.json
 * ke tabel komponen_anggaran dengan jenis 'program'.
 *
 * Data program akan dihubungkan dengan OPD berdasarkan KODE_SKPD.
 */
class ProgramDpaSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = base_path('referensi/apbd/program.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('File referensi/apbd/program.json tidak ditemukan.');
            return;
        }

        $json = file_get_contents($jsonPath);
        $data = json_decode($json, true);

        if (!$data) {
            $this->command->error('Gagal membaca file program.json.');
            return;
        }

        // Ambil semua OPD dan index berdasarkan kode
        $opds = Opd::all()->keyBy('kode');

        $successCount = 0;
        $skipCount = 0;

        foreach ($data as $row) {
            $kodeSkpd   = $row['KODE_SKPD']    ?? null;
            $kodeProgram = $row['KODE_PROGRAM'] ?? null;
            $namaProgram = $row['NAMA_PROGRAM'] ?? null;
            $namaOpd    = $row['NAMA_OPD']      ?? null;
            $tahun      = date('Y');

            if (!$kodeProgram || !$namaProgram || !$kodeSkpd) {
                $skipCount++;
                continue;
            }

            // Cari OPD berdasarkan kode SKPD
            $opd = $opds->get($kodeSkpd);

            // Ambil kode bidang urusan dari kode program (misal: "1.01.02" -> bagian pertama "1.01")
            $parts       = explode('.', $kodeProgram);
            $bidangKode  = count($parts) >= 2 ? $parts[0] . '.' . $parts[1] : $parts[0];
            $urusanKode  = $parts[0] ?? '';

            KomponenAnggaran::updateOrCreate(
                [
                    'kode_program' => $kodeProgram,
                    'opd_id'       => $opd?->id,
                ],
                [
                    'kode'          => $kodeProgram,
                    'jenis'         => 'program',
                    'opd_id'        => $opd?->id,
                    'sub_unit'      => $namaOpd ?? ($opd?->nama ?? $kodeSkpd),
                    'urusan'        => $urusanKode,
                    'bidang_urusan' => $bidangKode,
                    'nama_komponen' => $namaProgram,
                    'tahun'         => $tahun,
                    'parent_id'     => null,
                ]
            );

            $successCount++;
        }

        $this->command->info("Program DPA berhasil di-seed: {$successCount} program.");
        if ($skipCount > 0) {
            $this->command->warn("Dilewati (data tidak lengkap): {$skipCount} baris.");
        }
    }
}
