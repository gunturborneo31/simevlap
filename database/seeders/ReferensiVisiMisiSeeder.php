<?php

namespace Database\Seeders;

use App\Models\Misi;
use App\Models\Strategi;
use App\Models\Sasaran;
use App\Models\Tujuan;
use App\Models\Visi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class ReferensiVisiMisiSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = base_path('referensi/pusat/visi_misi_tujuan_sasaran_strategi.json');

        if (!File::exists($filePath)) {
            $this->command->error('File tidak ditemukan: ' . $filePath);
            return;
        }

        $rows = json_decode(File::get($filePath), true);
        if (!is_array($rows)) {
            $this->command->error('Format JSON tidak valid');
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        // truncate in child->parent order
        Strategi::truncate();
        Sasaran::truncate();
        Tujuan::truncate();
        Misi::truncate();
        Visi::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        foreach ($rows as $row) {
            $kodeVisi = isset($row['kode_visi']) ? trim((string) $row['kode_visi']) : null;
            $visiText = isset($row['visi']) ? trim((string) $row['visi']) : null;

            if (empty($kodeVisi) || empty($visiText)) {
                continue;
            }

            $visi = Visi::withoutGlobalScopes()->updateOrCreate(
                ['kode' => $kodeVisi],
                [
                    'uraian' => $visiText,
                    'deskripsi' => $row['visi'] ?? '',
                    'tahun_awal' => $row['tahun_awal'] ?? 2024,
                    'tahun_akhir' => $row['tahun_akhir'] ?? 2030,
                ]
            );

            $kodeMisi = isset($row['kode_misi']) ? trim((string) $row['kode_misi']) : null;
            $misiText = isset($row['misi']) ? trim((string) $row['misi']) : null;

            if (empty($kodeMisi) || empty($misiText)) {
                continue;
            }

            $misi = Misi::updateOrCreate(
                ['visi_id' => $visi->id, 'kode' => $kodeMisi],
                ['uraian' => $misiText, 'deskripsi' => $row['misi'] ?? '']
            );

            $kodeTujuan = isset($row['kode_tujuan']) ? trim((string) $row['kode_tujuan']) : null;
            $tujuanText = isset($row['tujuan']) ? trim((string) $row['tujuan']) : null;

            if (empty($kodeTujuan) || empty($tujuanText)) {
                continue;
            }

            // Ensure Tujuan is unique by `kode` across all Misi.
            $tujuan = Tujuan::where('kode', $kodeTujuan)->first();
            if (!$tujuan) {
                $tujuan = Tujuan::create([
                    'misi_id' => $misi->id,
                    'kode' => $kodeTujuan,
                    'uraian' => $tujuanText,
                    'deskripsi' => $row['tujuan'] ?? '',
                ]);
            } else {
                // update text fields if missing/empty
                $tujuan->update([
                    'uraian' => $tujuan->uraian ?: $tujuanText,
                    'deskripsi' => $tujuan->deskripsi ?: ($row['tujuan'] ?? ''),
                ]);
            }

            $kodeSasaran = isset($row['kode_sasaran']) ? trim((string) $row['kode_sasaran']) : null;
            $sasaranText = isset($row['sasaran']) ? trim((string) $row['sasaran']) : null;

            if (empty($kodeSasaran) || empty($sasaranText)) {
                continue;
            }

            $sasaran = Sasaran::updateOrCreate(
                ['tujuan_id' => $tujuan->id, 'kode' => $kodeSasaran],
                ['uraian' => $sasaranText, 'deskripsi' => $row['sasaran'] ?? '']
            );

            $kodeStrategi = isset($row['kode_strategi']) ? trim((string) $row['kode_strategi']) : null;
            $strategiText = isset($row['STRATEGI']) ? trim((string) $row['STRATEGI']) : null;

            if (empty($kodeStrategi) || empty($strategiText)) {
                continue;
            }

            Strategi::updateOrCreate(
                ['sasaran_id' => $sasaran->id, 'kode' => $kodeStrategi],
                ['uraian' => $strategiText, 'deskripsi' => $row['STRATEGI'] ?? '']
            );
        }
    }
}
