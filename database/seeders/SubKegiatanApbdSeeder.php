<?php

namespace Database\Seeders;

use App\Models\Kegiatan;
use App\Models\KomponenAnggaran;
use App\Models\Opd;
use App\Models\Program;
use App\Models\SubKegiatan;
use Illuminate\Database\Seeder;

class SubKegiatanApbdSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = base_path('referensi/apbd/sub_kegiatan.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('File referensi/apbd/sub_kegiatan.json tidak ditemukan.');
            return;
        }

        $json = file_get_contents($jsonPath);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $this->command->error('Gagal membaca file sub_kegiatan.json.');
            return;
        }

        $programNameMap = $this->loadProgramNameMap();
        $kegiatanNameMap = $this->loadKegiatanNameMap();

        $tahun = (int) date('Y');

        $opdMap = Opd::query()
            ->select(['id', 'kode', 'nama'])
            ->get()
            ->keyBy('kode');

        $programMap = Program::query()
            ->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])
            ->where('document_type', 'dpa')
            ->get()
            ->keyBy(fn ($program) => $program->opd_id . '|' . $program->kode_rek);

        $kegiatanMap = Kegiatan::query()
            ->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])
            ->where('document_type', 'dpa')
            ->get()
            ->keyBy(fn ($kegiatan) => $kegiatan->opd_id . '|' . $kegiatan->kode_rek);

        $dpaProgramMap = KomponenAnggaran::query()
            ->select(['id', 'opd_id', 'kode', 'kode_program', 'tahun', 'nama_komponen'])
            ->where('jenis', 'program')
            ->whereNull('parent_id')
            ->get()
            ->keyBy(function ($komponen) {
                $programCode = $komponen->kode_program ?: $komponen->kode;
                return $komponen->opd_id . '|' . $programCode;
            });

        $dpaKegiatanMap = KomponenAnggaran::query()
            ->select(['id', 'opd_id', 'kode', 'nama_komponen'])
            ->where('jenis', 'kegiatan')
            ->get()
            ->keyBy(fn ($komponen) => $komponen->opd_id . '|' . $komponen->kode);

        $subCreated = 0;
        $subUpdated = 0;
        $dpaSubCreated = 0;
        $dpaSubUpdated = 0;
        $skipped = 0;

        foreach ($data as $row) {
            $kodeSubKegiatan = trim((string) ($row['KODE_SUB_KEGIATAN'] ?? ''));
            $namaSubKegiatan = trim((string) (($row['NAMA SUB_KEGIATAN'] ?? $row['NAMA_SUB_KEGIATAN'] ?? '')));
            $kodeSkpd = trim((string) ($row['KODE_SKPD'] ?? ''));
            $kodeSubUnit = trim((string) ($row['KODE_SUB_UNIT'] ?? ''));
            $namaSubUnit = trim((string) ($row['NAMA_SUB_UNIT'] ?? ''));

            if ($kodeSubKegiatan === '' || $namaSubKegiatan === '' || $kodeSkpd === '') {
                $skipped++;
                continue;
            }

            // Prioritaskan sub unit agar SKPD seperti Sekretariat Daerah
            // terpecah per Bagian (berdasarkan KODE_SUB_UNIT).
            $opdSkpd = $opdMap->get($kodeSkpd);
            $opd = $opdMap->get($kodeSubUnit) ?? $opdSkpd;
            $kodeKegiatan = $this->extractKodeKegiatan($kodeSubKegiatan);
            $kodeProgram = $this->extractKodeProgram($kodeSubKegiatan);
            $pagu = $this->normalizePagu(
                $row['PAGU_VALIDASI'] ?? $row['PAGU'] ?? $row['RINCIAN'] ?? 0
            );

            $programKey = ($opd?->id ?? 'null') . '|' . $kodeProgram;
            $programKeySkpd = ($opdSkpd?->id ?? 'null') . '|' . $kodeProgram;
            $kegiatanKey = ($opd?->id ?? 'null') . '|' . $kodeKegiatan;
            $kegiatanKeySkpd = ($opdSkpd?->id ?? 'null') . '|' . $kodeKegiatan;

            $program = $programMap->get($programKey) ?? $programMap->get($programKeySkpd);
            $kegiatan = $kegiatanMap->get($kegiatanKey) ?? $kegiatanMap->get($kegiatanKeySkpd);

            $namaProgramReferensi =
                $programNameMap[$kodeSkpd . '|' . $kodeProgram]
                ?? $programNameMap[$kodeProgram]
                ?? null;

            $namaKegiatanReferensi =
                $kegiatanNameMap[$kodeSkpd . '|' . $kodeKegiatan]
                ?? $kegiatanNameMap[$kodeKegiatan]
                ?? null;

            if (!$kegiatan) {
                $kegiatan = Kegiatan::updateOrCreate(
                    [
                        'kode_rek' => $kodeKegiatan,
                        'opd_id' => $opd?->id,
                    ],
                    [
                        'program_id' => $program?->id,
                        'kepmen_id' => 1,
                        'document_type' => 'dpa',
                        'nama_rincian' => $this->truncateText(
                            $namaKegiatanReferensi ?? ('Kegiatan ' . $kodeKegiatan . ' (otomatis)'),
                            500
                        ),
                        'tahun' => $tahun,
                    ]
                );

                $kegiatanMap->put($kegiatanKey, $kegiatan);
            } elseif ($namaKegiatanReferensi && str_contains((string) $kegiatan->nama_rincian, '(otomatis)')) {
                $kegiatan->update([
                    'nama_rincian' => $this->truncateText($namaKegiatanReferensi, 500),
                ]);
            }

            $sub = SubKegiatan::updateOrCreate(
                [
                    'kode_rek' => $kodeSubKegiatan,
                    'opd_id' => $opd?->id,
                ],
                [
                    'kegiatan_id' => $kegiatan?->id,
                    'kepmen_id' => 1,
                    'document_type' => 'dpa',
                    'nama_rincian' => $this->truncateText($namaSubKegiatan, 500),
                    'pagu' => $pagu,
                    'tahun' => $tahun,
                ]
            );

            if ($sub->wasRecentlyCreated) {
                $subCreated++;
            } else {
                $subUpdated++;
            }

            $dpaProgram = $dpaProgramMap->get($programKey) ?? $dpaProgramMap->get($programKeySkpd);

            if (!$dpaProgram) {
                [$programUrusan, $programBidangUrusan] = $this->extractUrusanCodes($kodeProgram);
                $fallbackProgramName = $program?->nama_rincian
                    ?? $namaProgramReferensi
                    ?? ('Program ' . $kodeProgram . ' (otomatis)');

                $dpaProgram = KomponenAnggaran::updateOrCreate(
                    [
                        'parent_id' => null,
                        'kode' => $kodeProgram,
                        'jenis' => 'program',
                        'opd_id' => $opd?->id,
                    ],
                    [
                        'kode_program' => $kodeProgram,
                        'sub_unit' => $namaSubUnit ?: ($row['NAMA_SKPD'] ?? ($opd?->nama ?? '-')),
                        'urusan' => $programUrusan,
                        'bidang_urusan' => $programBidangUrusan,
                        'nama_komponen' => $this->truncateText($fallbackProgramName, 255),
                        'pagu' => 0,
                        'tahun' => $tahun,
                    ]
                );

                $dpaProgramMap->put($programKey, $dpaProgram);
            } elseif ($namaProgramReferensi && str_contains((string) $dpaProgram->nama_komponen, '(otomatis)')) {
                $dpaProgram->update([
                    'nama_komponen' => $this->truncateText($namaProgramReferensi, 255),
                ]);
            }

            $dpaKegiatan = $dpaKegiatanMap->get($kegiatanKey) ?? $dpaKegiatanMap->get($kegiatanKeySkpd);

            if (!$dpaKegiatan) {
                [$kegUrusan, $kegBidangUrusan] = $this->extractUrusanCodes($kodeKegiatan);
                $fallbackKegiatanName = $kegiatan?->nama_rincian
                    ?? $namaKegiatanReferensi
                    ?? ('Kegiatan ' . $kodeKegiatan . ' (otomatis)');

                $dpaKegiatan = KomponenAnggaran::updateOrCreate(
                    [
                        'parent_id' => $dpaProgram?->id,
                        'kode' => $kodeKegiatan,
                        'jenis' => 'kegiatan',
                        'opd_id' => $opd?->id,
                    ],
                    [
                        'kode_program' => $kodeProgram,
                        'sub_unit' => $namaSubUnit ?: ($row['NAMA_SKPD'] ?? ($opd?->nama ?? '-')),
                        'urusan' => $kegUrusan,
                        'bidang_urusan' => $kegBidangUrusan,
                        'nama_komponen' => $this->truncateText($fallbackKegiatanName, 255),
                        'pagu' => 0,
                        'tahun' => $dpaProgram?->tahun ?? $tahun,
                    ]
                );

                $dpaKegiatanMap->put($kegiatanKey, $dpaKegiatan);
            } elseif ($namaKegiatanReferensi && str_contains((string) $dpaKegiatan->nama_komponen, '(otomatis)')) {
                $dpaKegiatan->update([
                    'nama_komponen' => $this->truncateText($namaKegiatanReferensi, 255),
                ]);
            }

            [$urusan, $bidangUrusan] = $this->extractUrusanCodes($kodeSubKegiatan);

            $dpaSub = KomponenAnggaran::updateOrCreate(
                [
                    'parent_id' => $dpaKegiatan?->id,
                    'kode' => $kodeSubKegiatan,
                    'jenis' => 'sub_kegiatan',
                    'opd_id' => $opd?->id,
                ],
                [
                    'kode_program' => $kodeProgram,
                    'sub_unit' => $namaSubUnit ?: ($row['NAMA_SKPD'] ?? ($opd?->nama ?? '-')),
                    'urusan' => $urusan,
                    'bidang_urusan' => $bidangUrusan,
                    'nama_komponen' => $this->truncateText($namaSubKegiatan, 255),
                    'pagu' => $pagu,
                    'tahun' => $dpaKegiatan?->tahun ?? $tahun,
                ]
            );

            if ($dpaSub->wasRecentlyCreated) {
                $dpaSubCreated++;
            } else {
                $dpaSubUpdated++;
            }

            // Bersihkan jejak data lama: dulu data ditautkan ke KODE_SKPD induk.
            // Jika sekarang dipetakan ke KODE_SUB_UNIT yang berbeda, hapus duplikat lama.
            if (
                $kodeSubUnit !== ''
                && $opdSkpd
                && $opd
                && (int) $opdSkpd->id !== (int) $opd->id
            ) {
                SubKegiatan::query()
                    ->where('kode_rek', $kodeSubKegiatan)
                    ->where('opd_id', $opdSkpd->id)
                    ->delete();

                KomponenAnggaran::query()
                    ->where('jenis', 'sub_kegiatan')
                    ->where('kode', $kodeSubKegiatan)
                    ->where('opd_id', $opdSkpd->id)
                    ->delete();
            }
        }

        $this->command->info("Sub kegiatan APBD: {$subCreated} ditambahkan, {$subUpdated} diperbarui.");
        $this->command->info("Link DPA (komponen sub kegiatan): {$dpaSubCreated} ditambahkan, {$dpaSubUpdated} diperbarui.");

        if ($skipped > 0) {
            $this->command->warn("Data sub kegiatan dilewati karena tidak lengkap: {$skipped} baris.");
        }
    }

    private function extractKodeProgram(string $kodeSubKegiatan): string
    {
        $parts = array_values(array_filter(explode('.', $kodeSubKegiatan), fn ($part) => $part !== ''));

        return implode('.', array_slice($parts, 0, 3));
    }

    private function extractKodeKegiatan(string $kodeSubKegiatan): string
    {
        $parts = array_values(array_filter(explode('.', $kodeSubKegiatan), fn ($part) => $part !== ''));

        return implode('.', array_slice($parts, 0, 5));
    }

    private function extractUrusanCodes(string $kode): array
    {
        $parts = array_values(array_filter(explode('.', $kode), fn ($part) => $part !== ''));
        $urusan = $parts[0] ?? '';
        $bidangUrusan = count($parts) >= 2 ? $parts[0] . '.' . $parts[1] : $urusan;

        return [$urusan, $bidangUrusan];
    }

    private function normalizePagu($value): int
    {
        if (is_string($value)) {
            $value = preg_replace('/[^0-9\-]/', '', $value);
        }

        return (int) ($value ?: 0);
    }

    private function loadProgramNameMap(): array
    {
        $path = base_path('referensi/apbd/program.json');
        if (!file_exists($path)) {
            return [];
        }

        $rows = json_decode(file_get_contents($path), true);
        if (!is_array($rows)) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $kodeSkpd = trim((string) ($row['KODE_SKPD'] ?? ''));
            $kodeProgram = trim((string) ($row['KODE_PROGRAM'] ?? ''));
            $namaProgram = trim((string) ($row['NAMA_PROGRAM'] ?? ''));

            if ($kodeProgram === '' || $namaProgram === '') {
                continue;
            }

            $map[$kodeProgram] = $namaProgram;

            if ($kodeSkpd !== '') {
                $map[$kodeSkpd . '|' . $kodeProgram] = $namaProgram;
            }
        }

        return $map;
    }

    private function loadKegiatanNameMap(): array
    {
        $path = base_path('referensi/apbd/kegiatan.json');
        if (!file_exists($path)) {
            return [];
        }

        $rows = json_decode(file_get_contents($path), true);
        if (!is_array($rows)) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $kodeSkpd = trim((string) ($row['KODE_SKPD'] ?? ''));
            $kodeKegiatan = trim((string) ($row['KODE_KEGIATAN'] ?? ''));
            $namaKegiatan = trim((string) ($row['NAMA_KEGIATAN'] ?? ''));

            if ($kodeKegiatan === '' || $namaKegiatan === '') {
                continue;
            }

            $map[$kodeKegiatan] = $namaKegiatan;

            if ($kodeSkpd !== '') {
                $map[$kodeSkpd . '|' . $kodeKegiatan] = $namaKegiatan;
            }
        }

        return $map;
    }

    private function truncateText(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max);
    }
}
