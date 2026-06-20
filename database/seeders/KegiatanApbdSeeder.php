<?php

namespace Database\Seeders;

use App\Models\Kegiatan;
use App\Models\KomponenAnggaran;
use App\Models\Opd;
use App\Models\Program;
use Illuminate\Database\Seeder;

class KegiatanApbdSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = base_path('referensi/apbd/kegiatan.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('File referensi/apbd/kegiatan.json tidak ditemukan.');
            return;
        }

        $json = file_get_contents($jsonPath);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $this->command->error('Gagal membaca file kegiatan.json.');
            return;
        }

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

        $dpaProgramMap = KomponenAnggaran::query()
            ->select(['id', 'opd_id', 'kode', 'kode_program', 'tahun'])
            ->where('jenis', 'program')
            ->whereNull('parent_id')
            ->get()
            ->keyBy(function ($komponen) {
                $programCode = $komponen->kode_program ?: $komponen->kode;
                return $komponen->opd_id . '|' . $programCode;
            });

        $kegiatanCreated = 0;
        $kegiatanUpdated = 0;
        $dpaLinkedCreated = 0;
        $dpaLinkedUpdated = 0;
        $dpaLinkSkipped = 0;
        $skipped = 0;

        foreach ($data as $row) {
            $kodeKegiatan = trim((string) ($row['KODE_KEGIATAN'] ?? ''));
            $namaKegiatan = trim((string) ($row['NAMA_KEGIATAN'] ?? ''));
            $kodeSkpd = trim((string) ($row['KODE_SKPD'] ?? ''));

            if ($kodeKegiatan === '' || $namaKegiatan === '' || $kodeSkpd === '') {
                $skipped++;
                continue;
            }

            $opd = $opdMap->get($kodeSkpd);
            $kodeProgram = $this->extractKodeProgram($kodeKegiatan);
            $mapKey = ($opd?->id ?? 'null') . '|' . $kodeProgram;

            $program = $programMap->get($mapKey);

            $kegiatan = Kegiatan::updateOrCreate(
                [
                    'kode_rek' => $kodeKegiatan,
                    'opd_id' => $opd?->id,
                ],
                [
                    'program_id' => $program?->id,
                    'kepmen_id' => 1,
                    'document_type' => 'dpa',
                    'nama_rincian' => $namaKegiatan,
                    'tahun' => $tahun,
                ]
            );

            if ($kegiatan->wasRecentlyCreated) {
                $kegiatanCreated++;
            } else {
                $kegiatanUpdated++;
            }

            $dpaProgram = $dpaProgramMap->get($mapKey);

            if (!$dpaProgram) {
                [$programUrusan, $programBidangUrusan] = $this->extractUrusanCodes($kodeProgram);
                $fallbackProgramName = $program?->nama_rincian ?? ('Program ' . $kodeProgram . ' (otomatis)');

                $dpaProgram = KomponenAnggaran::updateOrCreate(
                    [
                        'parent_id' => null,
                        'kode' => $kodeProgram,
                        'jenis' => 'program',
                        'opd_id' => $opd?->id,
                    ],
                    [
                        'kode_program' => $kodeProgram,
                        'sub_unit' => $row['NAMA_OPD'] ?? ($opd?->nama ?? '-'),
                        'urusan' => $programUrusan,
                        'bidang_urusan' => $programBidangUrusan,
                        'nama_komponen' => $this->truncateText($fallbackProgramName, 255),
                        'tahun' => $tahun,
                    ]
                );

                $dpaProgramMap->put($mapKey, $dpaProgram);
            }

            if (!$dpaProgram) {
                $dpaLinkSkipped++;
                continue;
            }

            [$urusan, $bidangUrusan] = $this->extractUrusanCodes($kodeKegiatan);

            $komponen = KomponenAnggaran::updateOrCreate(
                [
                    'parent_id' => $dpaProgram->id,
                    'kode' => $kodeKegiatan,
                    'jenis' => 'kegiatan',
                    'opd_id' => $opd?->id,
                ],
                [
                    'kode_program' => $kodeProgram,
                    'sub_unit' => $row['NAMA_OPD'] ?? ($opd?->nama ?? '-'),
                    'urusan' => $urusan,
                    'bidang_urusan' => $bidangUrusan,
                    'nama_komponen' => $this->truncateText($namaKegiatan, 255),
                    'tahun' => $dpaProgram->tahun ?? $tahun,
                ]
            );

            if ($komponen->wasRecentlyCreated) {
                $dpaLinkedCreated++;
            } else {
                $dpaLinkedUpdated++;
            }
        }

        $this->command->info("Kegiatan APBD: {$kegiatanCreated} ditambahkan, {$kegiatanUpdated} diperbarui.");
        $this->command->info("Link DPA (komponen kegiatan): {$dpaLinkedCreated} ditambahkan, {$dpaLinkedUpdated} diperbarui.");

        if ($dpaLinkSkipped > 0) {
            $this->command->warn("Tidak terhubung ke DPA (program parent tidak ditemukan): {$dpaLinkSkipped} baris.");
        }

        if ($skipped > 0) {
            $this->command->warn("Data dilewati karena tidak lengkap: {$skipped} baris.");
        }
    }

    private function extractKodeProgram(string $kodeKegiatan): string
    {
        $parts = array_values(array_filter(explode('.', $kodeKegiatan), fn ($part) => $part !== ''));
        return implode('.', array_slice($parts, 0, 3));
    }

    private function extractUrusanCodes(string $kode): array
    {
        $parts = array_values(array_filter(explode('.', $kode), fn ($part) => $part !== ''));
        $urusan = $parts[0] ?? '';
        $bidangUrusan = count($parts) >= 2 ? $parts[0] . '.' . $parts[1] : $urusan;

        return [$urusan, $bidangUrusan];
    }

    private function truncateText(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max);
    }
}
