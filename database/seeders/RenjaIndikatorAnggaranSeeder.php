<?php

namespace Database\Seeders;

use App\Models\IndikatorAnggaran;
use App\Models\KomponenAnggaran;
use App\Models\Opd;
use Illuminate\Database\Seeder;

class RenjaIndikatorAnggaranSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = base_path('referensi/rkpd/indikator_fix.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('File referensi/rkpd/indikator_fix.json tidak ditemukan.');
            return;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);

        if (!is_array($rows)) {
            $this->command->error('Gagal membaca file referensi/rkpd/indikator_fix.json.');
            return;
        }

        $tahun = 2026;

        $opdMap = Opd::query()
            ->select(['id', 'kode'])
            ->get()
            ->keyBy(fn ($opd) => trim((string) $opd->kode));

        $komponenMap = KomponenAnggaran::query()
            ->select(['id', 'opd_id', 'kode', 'jenis'])
            ->where('document_type', 'renja')
            ->where('tahun', $tahun)
            ->whereNotNull('opd_id')
            ->get()
            ->groupBy('opd_id')
            ->map(fn ($items) => $items->keyBy(fn ($item) => trim((string) $item->kode) . '|' . trim((string) $item->jenis)));

        $created = 0;
        $updated = 0;
        $notFound = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $kodeUnit = trim((string) ($row['kode_unit'] ?? ''));
            $kodeUraian = trim((string) ($row['kode_uraian'] ?? ''));
            $namaIndikator = trim((string) ($row['indikator'] ?? ''));
            $jenis = $this->normalizeJenis($row['jenis'] ?? ($row['jenis_indikator'] ?? null));

            if ($kodeUnit === '' || $kodeUraian === '' || $namaIndikator === '' || $namaIndikator === '-' || $jenis === '') {
                $skipped++;
                continue;
            }

            $opd = $opdMap->get($kodeUnit);

            if (!$opd) {
                $notFound++;
                continue;
            }

            $komponen = $komponenMap
                ->get($opd->id)
                ?->get($kodeUraian . '|' . $jenis);

            if (!$komponen) {
                $notFound++;
                continue;
            }

            $target = $this->normalizeTargetString($row['TARGET'] ?? null);
            $satuan = trim((string) ($row['SATUAN'] ?? ''));

            $indikator = IndikatorAnggaran::updateOrCreate(
                [
                    'komponen_anggaran_id' => $komponen->id,
                    'nama_indikator' => $namaIndikator,
                ],
                [
                    'sifat_indikator' => null,
                    'target_indikator' => $target,
                    'satuan' => $satuan !== '' ? $satuan : '-',
                ]
            );

            if ($indikator->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $this->command->info("Indikator anggaran RENJA (tahun {$tahun}): {$created} ditambahkan, {$updated} diperbarui.");
        $this->command->info("Indikator anggaran RENJA tidak cocok (kode_unit/kode_uraian): {$notFound} baris.");

        if ($skipped > 0) {
            $this->command->warn("Indikator anggaran RENJA dilewati: {$skipped} baris.");
        }
    }

    private function normalizeJenis($jenis): string
    {
        $jenisKey = strtolower(trim((string) $jenis));

        return match ($jenisKey) {
            'program', 'programs' => 'program',
            'kegiatan', 'activities' => 'kegiatan',
            'sub kegiatan', 'sub_kegiatan', 'subkegiatan' => 'sub_kegiatan',
            default => '',
        };
    }

    private function normalizeTargetString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return rtrim(rtrim((string) $value, '0'), '.');
        }

        $target = trim((string) $value);

        if ($target === '' || $target === '-') {
            return null;
        }

        return $target;
    }
}
