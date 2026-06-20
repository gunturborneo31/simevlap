<?php

namespace Database\Seeders;

use App\Models\IndikatorAnggaran;
use App\Models\KomponenAnggaran;
use App\Models\Opd;
use Illuminate\Database\Seeder;

class IndikatorDpaSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = base_path('referensi/apbd/indikator_fix.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('File referensi/apbd/indikator_fix.json tidak ditemukan.');
            return;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);

        if (!is_array($rows)) {
            $this->command->error('Gagal membaca file indikator_fix.json.');
            return;
        }

        $opdMap = Opd::query()
            ->select(['id', 'kode'])
            ->get()
            ->keyBy(fn ($opd) => trim((string) $opd->kode));

        $komponenByOpdKode = KomponenAnggaran::query()
            ->select(['id', 'opd_id', 'kode', 'jenis'])
            ->whereNotNull('opd_id')
            ->get()
            ->groupBy('opd_id')
            ->map(function ($items) {
                return $items->keyBy(function ($item) {
                    return trim((string) $item->kode) . '|' . trim((string) $item->jenis);
                });
            });

        $created = 0;
        $updated = 0;
        $notFound = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $kodeUnit = trim((string) ($row['kode_unit'] ?? ''));
            $kodeUraian = trim((string) ($row['kode_uraian'] ?? ''));
            $namaIndikator = trim((string) ($row['indikator'] ?? ''));

            if ($kodeUnit === '' || $kodeUraian === '' || $namaIndikator === '') {
                $skipped++;
                continue;
            }

            $opd = $opdMap->get($kodeUnit);

            if (!$opd) {
                $notFound++;
                continue;
            }

            $jenis = $this->normalizeJenis($row['jenis'] ?? null, $kodeUraian);
            $komponenKey = $kodeUraian . '|' . $jenis;

            $komponen = $komponenByOpdKode
                ->get($opd->id)
                ?->get($komponenKey);

            if (!$komponen) {
                $notFound++;
                continue;
            }

            $target = $this->normalizeTarget($row['TARGET'] ?? null);
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

        $this->command->info("Indikator DPA: {$created} ditambahkan, {$updated} diperbarui.");
        $this->command->info("Indikator DPA tidak cocok (kode_unit/kode_uraian tidak ditemukan): {$notFound} baris.");

        if ($skipped > 0) {
            $this->command->warn("Indikator DPA dilewati karena data tidak lengkap: {$skipped} baris.");
        }
    }

    private function normalizeJenis($jenis, string $kodeUraian): string
    {
        $jenisMap = [
            'program' => 'program',
            'kegiatan' => 'kegiatan',
            'sub kegiatan' => 'sub_kegiatan',
            'sub_kegiatan' => 'sub_kegiatan',
        ];

        $jenisKey = strtolower(trim((string) $jenis));
        if (isset($jenisMap[$jenisKey])) {
            return $jenisMap[$jenisKey];
        }

        $parts = array_values(array_filter(explode('.', $kodeUraian), fn ($part) => $part !== ''));
        $count = count($parts);

        if ($count <= 3) {
            return 'program';
        }

        if ($count <= 5) {
            return 'kegiatan';
        }

        return 'sub_kegiatan';
    }

    private function normalizeTarget($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return rtrim(rtrim((string) $value, '0'), '.');
        }

        $target = trim((string) $value);
        if ($target === '') {
            return null;
        }

        return $target;
    }
}
