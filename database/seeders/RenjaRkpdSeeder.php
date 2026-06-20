<?php

namespace Database\Seeders;

use App\Models\IndikatorAnggaran;
use App\Models\Indikator;
use App\Models\Kegiatan;
use App\Models\KomponenAnggaran;
use App\Models\Opd;
use App\Models\Program;
use App\Models\SubKegiatan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class RenjaRkpdSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Import RENJA RKPD dimulai...');

        $tahun = (int) date('Y');
        $opds = Opd::all()->keyBy('kode');

        $this->importPrograms($opds, $tahun);
        $this->importKegiatan($opds, $tahun);
        $this->importSubKegiatan($opds, $tahun);
        $this->importIndikators($opds, $tahun);
        $this->syncKomponenAnggaran($tahun);
        $this->importIndikatorAnggaran($opds, $tahun);

        $this->command->info('Import RENJA RKPD selesai.');
    }

    private function importPrograms(Collection $opds, int $tahun): void
    {
        $jsonPath = base_path('referensi/rkpd/program.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('File referensi/rkpd/program.json tidak ditemukan.');
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (!is_array($data)) {
            $this->command->error('Gagal membaca file referensi/rkpd/program.json.');
            return;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($data as $row) {
            $kodeProgram = trim((string) ($row['KODE_PROGRAM'] ?? ''));
            $namaProgram = trim((string) ($row['NAMA_PROGRAM'] ?? ''));
            $kodeSkpd = trim((string) ($row['KODE_SKPD'] ?? ''));
            $namaOpd = trim((string) ($row['NAMA_OPD'] ?? ''));

            if ($kodeProgram === '' || $namaProgram === '' || $kodeSkpd === '') {
                $skipped++;
                continue;
            }

            $opd = $opds->get($kodeSkpd);

            $program = Program::updateOrCreate(
                [
                    'kode_rek' => $kodeProgram,
                    'opd_id' => $opd?->id,
                ],
                [
                    'kepmen_id' => 1,
                    'document_type' => 'renja',
                    'nama_rincian' => $namaProgram,
                    'pagu' => 0,
                    'tahun' => $tahun,
                ]
            );

            if ($program->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $this->command->info("Program RENJA: {$created} ditambahkan, {$updated} diperbarui, {$skipped} dilewati.");
    }

    private function importKegiatan(Collection $opds, int $tahun): void
    {
        $jsonPath = base_path('referensi/rkpd/kegaiatan.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('File referensi/rkpd/kegaiatan.json tidak ditemukan.');
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (!is_array($data)) {
            $this->command->error('Gagal membaca file referensi/rkpd/kegaiatan.json.');
            return;
        }

        $programMap = Program::query()
            ->where('document_type', 'renja')
            ->get()
            ->keyBy(fn ($program) => $program->opd_id . '|' . $program->kode_rek);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($data as $row) {
            $kodeKegiatan = trim((string) ($row['KODE_KEGIATAN'] ?? ''));
            $namaKegiatan = trim((string) ($row['NAMA_KEGIATAN'] ?? ''));
            $kodeSkpd = trim((string) ($row['KODE_SKPD'] ?? ''));

            if ($kodeKegiatan === '' || $namaKegiatan === '' || $kodeSkpd === '') {
                $skipped++;
                continue;
            }

            $opd = $opds->get($kodeSkpd);
            $kodeProgram = $this->extractKodeProgram($kodeKegiatan);
            $program = $programMap->get(($opd?->id ?? 'null') . '|' . $kodeProgram);

            if (!$program) {
                $program = Program::create([
                    'opd_id' => $opd?->id,
                    'kepmen_id' => 1,
                    'document_type' => 'renja',
                    'kode_rek' => $kodeProgram,
                    'nama_rincian' => 'Program ' . $kodeProgram . ' (otomatis)',
                    'pagu' => 0,
                    'tahun' => $tahun,
                ]);
                $programMap->put(($opd?->id ?? 'null') . '|' . $kodeProgram, $program);
            }

            $kegiatan = Kegiatan::updateOrCreate(
                [
                    'kode_rek' => $kodeKegiatan,
                    'opd_id' => $opd?->id,
                ],
                [
                    'program_id' => $program?->id,
                    'kepmen_id' => 1,
                    'document_type' => 'renja',
                    'nama_rincian' => $namaKegiatan,
                    'tahun' => $tahun,
                ]
            );

            if ($kegiatan->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $this->command->info("Kegiatan RENJA: {$created} ditambahkan, {$updated} diperbarui, {$skipped} dilewati.");
    }

    private function importSubKegiatan(Collection $opds, int $tahun): void
    {
        $jsonPath = base_path('referensi/rkpd/sub_kegiatan.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('File referensi/rkpd/sub_kegiatan.json tidak ditemukan.');
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (!is_array($data)) {
            $this->command->error('Gagal membaca file referensi/rkpd/sub_kegiatan.json.');
            return;
        }

        $programMap = Program::query()
            ->where('document_type', 'renja')
            ->get()
            ->keyBy(fn ($program) => $program->opd_id . '|' . $program->kode_rek);

        $kegiatanMap = Kegiatan::query()
            ->where('document_type', 'renja')
            ->get()
            ->keyBy(fn ($kegiatan) => $kegiatan->opd_id . '|' . $kegiatan->kode_rek);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($data as $row) {
            $kodeSub = trim((string) ($row['KODE_SUB_KEGIATAN'] ?? ''));
            $namaSub = trim((string) (($row['NAMA_SUB_KEGIATAN'] ?? $row['NAMA SUB_KEGIATAN'] ?? '')));
            $kodeSkpd = trim((string) ($row['KODE_SKPD'] ?? ''));
            $kodeSubUnit = trim((string) ($row['KODE_SUB_UNIT'] ?? ''));
            $pagu = $this->normalizePagu($row['PAGU_VALIDASI'] ?? $row['PAGU'] ?? $row['RINCIAN'] ?? 0);

            if ($kodeSub === '' || $namaSub === '' || $kodeSkpd === '') {
                $skipped++;
                continue;
            }

            $opdSkpd = $opds->get($kodeSkpd);
            $opd = $opds->get($kodeSubUnit) ?? $opdSkpd;
            $kodeKegiatan = $this->extractKodeKegiatan($kodeSub);

            $programKey = ($opd?->id ?? 'null') . '|' . $this->extractKodeProgram($kodeSub);
            $kegiatanKey = ($opd?->id ?? 'null') . '|' . $kodeKegiatan;

            $program = $programMap->get($programKey);
            if (!$program) {
                $program = Program::create([
                    'opd_id' => $opd?->id,
                    'kepmen_id' => 1,
                    'document_type' => 'renja',
                    'kode_rek' => $this->extractKodeProgram($kodeSub),
                    'nama_rincian' => 'Program ' . $this->extractKodeProgram($kodeSub) . ' (otomatis)',
                    'pagu' => 0,
                    'tahun' => $tahun,
                ]);
                $programMap->put($programKey, $program);
            }

            $kegiatan = $kegiatanMap->get($kegiatanKey);
            if (!$kegiatan) {
                $kegiatan = Kegiatan::create([
                    'program_id' => $program?->id,
                    'opd_id' => $opd?->id,
                    'kepmen_id' => 1,
                    'document_type' => 'renja',
                    'kode_rek' => $kodeKegiatan,
                    'nama_rincian' => 'Kegiatan ' . $kodeKegiatan . ' (otomatis)',
                    'tahun' => $tahun,
                ]);
                $kegiatanMap->put($kegiatanKey, $kegiatan);
            }

            $sub = SubKegiatan::updateOrCreate(
                [
                    'kode_rek' => $kodeSub,
                    'opd_id' => $opd?->id,
                ],
                [
                    'kegiatan_id' => $kegiatan?->id,
                    'kepmen_id' => 1,
                    'document_type' => 'renja',
                    'nama_rincian' => $namaSub,
                    'pagu' => $pagu,
                    'tahun' => $tahun,
                ]
            );

            if ($sub->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $this->command->info("Sub kegiatan RENJA: {$created} ditambahkan, {$updated} diperbarui, {$skipped} dilewati.");
    }

    private function importIndikators(Collection $opds, int $tahun): void
    {
        $jsonPath = base_path('referensi/rkpd/indikator_fix.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('File referensi/rkpd/indikator_fix.json tidak ditemukan.');
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (!is_array($data)) {
            $this->command->error('Gagal membaca file referensi/rkpd/indikator_fix.json.');
            return;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($data as $row) {
            $kodeUnit = trim((string) ($row['kode_unit'] ?? ''));
            $kodeUraian = trim((string) ($row['kode_uraian'] ?? ''));
            $jenis = $this->normalizeJenis($row['jenis'] ?? ($row['jenis_indikator'] ?? null));
            $indikatorText = trim((string) ($row['indikator'] ?? ''));
            $satuan = trim((string) ($row['SATUAN'] ?? ''));
            $target = $this->normalizeTarget($row['TARGET'] ?? null);

            if ($kodeUnit === '' || $kodeUraian === '' || $jenis === '' || $indikatorText === '') {
                $skipped++;
                continue;
            }

            $opd = $opds->get($kodeUnit);
            if (!$opd) {
                $notFound++;
                continue;
            }

            $model = match ($jenis) {
                'program' => Program::query()->where('opd_id', $opd->id)->where('kode_rek', $kodeUraian)->first(),
                'kegiatan' => Kegiatan::query()->where('opd_id', $opd->id)->where('kode_rek', $kodeUraian)->first(),
                'sub_kegiatan' => SubKegiatan::query()->where('opd_id', $opd->id)->where('kode_rek', $kodeUraian)->first(),
                default => null,
            };

            if (!$model) {
                $notFound++;
                continue;
            }

            $indikator = Indikator::updateOrCreate(
                [
                    'opd_id' => $opd->id,
                    'document_type' => 'renja',
                    'jenis_indikator' => $jenis,
                    'uraian' => $indikatorText,
                ],
                [
                    'satuan' => $satuan !== '' ? $satuan : '-',
                ]
            );

            if ($indikator->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            $model->indikator()->syncWithoutDetaching([
                $indikator->id => [
                    'target' => $target,
                    'tahun' => $tahun,
                    'triwulan' => null,
                    'realisasi' => null,
                    'catatan' => null,
                ],
            ]);
        }

        $this->command->info("Indikator RENJA: {$created} ditambahkan, {$updated} diperbarui, {$notFound} tidak ditemukan, {$skipped} dilewati.");
    }

    private function syncKomponenAnggaran(int $tahun): void
    {
        $this->command->info('Sinkronisasi komponen anggaran RENJA dimulai...');

        $programMap = collect();
        $programCreated = 0;
        $programUpdated = 0;

        $programs = Program::query()
            ->with('opd:id,kode,nama')
            ->where('document_type', 'renja')
            ->where('tahun', $tahun)
            ->orderBy('kode_rek')
            ->get();

        foreach ($programs as $program) {
            [$urusan, $bidangUrusan] = $this->extractUrusanCodes((string) $program->kode_rek);

            $komponenProgram = KomponenAnggaran::updateOrCreate(
                [
                    'parent_id' => null,
                    'kode' => $program->kode_rek,
                    'jenis' => 'program',
                    'opd_id' => $program->opd_id,
                    'document_type' => 'renja',
                ],
                [
                    'kode_program' => $program->kode_rek,
                    'sub_unit' => $this->truncateText((string) ($program->opd?->nama ?? $program->opd?->kode ?? '-'), 255),
                    'urusan' => $urusan,
                    'bidang_urusan' => $bidangUrusan,
                    'nama_komponen' => $this->truncateText((string) $program->nama_rincian, 255),
                    'pagu' => 0,
                    'tahun' => $program->tahun ?? $tahun,
                ]
            );

            if ($komponenProgram->wasRecentlyCreated) {
                $programCreated++;
            } else {
                $programUpdated++;
            }

            $programMap->put(($program->opd_id ?? 'null') . '|' . $program->kode_rek, $komponenProgram);
        }

        $kegiatanMap = collect();
        $kegiatanCreated = 0;
        $kegiatanUpdated = 0;

        $kegiatans = Kegiatan::query()
            ->with(['opd:id,kode,nama', 'program:id,opd_id,kode_rek,nama_rincian,tahun'])
            ->where('document_type', 'renja')
            ->where('tahun', $tahun)
            ->orderBy('kode_rek')
            ->get();

        foreach ($kegiatans as $kegiatan) {
            $kodeProgram = $this->extractKodeProgram((string) $kegiatan->kode_rek);
            $programKey = ($kegiatan->opd_id ?? 'null') . '|' . $kodeProgram;

            $komponenProgram = $programMap->get($programKey);
            if (!$komponenProgram) {
                [$prUrusan, $prBidang] = $this->extractUrusanCodes($kodeProgram);
                $fallbackProgramName = $kegiatan->program?->nama_rincian ?? ('Program ' . $kodeProgram . ' (otomatis)');

                $komponenProgram = KomponenAnggaran::updateOrCreate(
                    [
                        'parent_id' => null,
                        'kode' => $kodeProgram,
                        'jenis' => 'program',
                        'opd_id' => $kegiatan->opd_id,
                        'document_type' => 'renja',
                    ],
                    [
                        'kode_program' => $kodeProgram,
                        'sub_unit' => $this->truncateText((string) ($kegiatan->opd?->nama ?? $kegiatan->opd?->kode ?? '-'), 255),
                        'urusan' => $prUrusan,
                        'bidang_urusan' => $prBidang,
                        'nama_komponen' => $this->truncateText((string) $fallbackProgramName, 255),
                        'pagu' => 0,
                        'tahun' => $kegiatan->tahun ?? $tahun,
                    ]
                );

                $programMap->put($programKey, $komponenProgram);
            }

            [$urusan, $bidangUrusan] = $this->extractUrusanCodes((string) $kegiatan->kode_rek);

            $komponenKegiatan = KomponenAnggaran::updateOrCreate(
                [
                    'parent_id' => $komponenProgram->id,
                    'kode' => $kegiatan->kode_rek,
                    'jenis' => 'kegiatan',
                    'opd_id' => $kegiatan->opd_id,
                    'document_type' => 'renja',
                ],
                [
                    'kode_program' => $kodeProgram,
                    'sub_unit' => $this->truncateText((string) ($kegiatan->opd?->nama ?? $kegiatan->opd?->kode ?? '-'), 255),
                    'urusan' => $urusan,
                    'bidang_urusan' => $bidangUrusan,
                    'nama_komponen' => $this->truncateText((string) $kegiatan->nama_rincian, 255),
                    'pagu' => 0,
                    'tahun' => $kegiatan->tahun ?? $tahun,
                ]
            );

            if ($komponenKegiatan->wasRecentlyCreated) {
                $kegiatanCreated++;
            } else {
                $kegiatanUpdated++;
            }

            $kegiatanMap->put(($kegiatan->opd_id ?? 'null') . '|' . $kegiatan->kode_rek, $komponenKegiatan);
        }

        $subCreated = 0;
        $subUpdated = 0;

        $subs = SubKegiatan::query()
            ->with(['opd:id,kode,nama', 'kegiatan:id,opd_id,kode_rek,nama_rincian,tahun'])
            ->where('document_type', 'renja')
            ->where('tahun', $tahun)
            ->orderBy('kode_rek')
            ->get();

        foreach ($subs as $sub) {
            $kodeKegiatan = $this->extractKodeKegiatan((string) $sub->kode_rek);
            $kegiatanKey = ($sub->opd_id ?? 'null') . '|' . $kodeKegiatan;

            $komponenKegiatan = $kegiatanMap->get($kegiatanKey);
            if (!$komponenKegiatan) {
                $kodeProgram = $this->extractKodeProgram((string) $sub->kode_rek);
                $programKey = ($sub->opd_id ?? 'null') . '|' . $kodeProgram;
                $komponenProgram = $programMap->get($programKey);

                if (!$komponenProgram) {
                    [$prUrusan, $prBidang] = $this->extractUrusanCodes($kodeProgram);

                    $komponenProgram = KomponenAnggaran::updateOrCreate(
                        [
                            'parent_id' => null,
                            'kode' => $kodeProgram,
                            'jenis' => 'program',
                            'opd_id' => $sub->opd_id,
                            'document_type' => 'renja',
                        ],
                        [
                            'kode_program' => $kodeProgram,
                            'sub_unit' => $this->truncateText((string) ($sub->opd?->nama ?? $sub->opd?->kode ?? '-'), 255),
                            'urusan' => $prUrusan,
                            'bidang_urusan' => $prBidang,
                            'nama_komponen' => $this->truncateText('Program ' . $kodeProgram . ' (otomatis)', 255),
                            'pagu' => 0,
                            'tahun' => $sub->tahun ?? $tahun,
                        ]
                    );

                    $programMap->put($programKey, $komponenProgram);
                }

                [$kegUrusan, $kegBidang] = $this->extractUrusanCodes($kodeKegiatan);

                $komponenKegiatan = KomponenAnggaran::updateOrCreate(
                    [
                        'parent_id' => $komponenProgram->id,
                        'kode' => $kodeKegiatan,
                        'jenis' => 'kegiatan',
                        'opd_id' => $sub->opd_id,
                        'document_type' => 'renja',
                    ],
                    [
                        'kode_program' => $kodeProgram,
                        'sub_unit' => $this->truncateText((string) ($sub->opd?->nama ?? $sub->opd?->kode ?? '-'), 255),
                        'urusan' => $kegUrusan,
                        'bidang_urusan' => $kegBidang,
                        'nama_komponen' => $this->truncateText((string) ($sub->kegiatan?->nama_rincian ?? ('Kegiatan ' . $kodeKegiatan . ' (otomatis)')), 255),
                        'pagu' => 0,
                        'tahun' => $sub->tahun ?? $tahun,
                    ]
                );

                $kegiatanMap->put($kegiatanKey, $komponenKegiatan);
            }

            [$urusan, $bidangUrusan] = $this->extractUrusanCodes((string) $sub->kode_rek);

            $komponenSub = KomponenAnggaran::updateOrCreate(
                [
                    'parent_id' => $komponenKegiatan->id,
                    'kode' => $sub->kode_rek,
                    'jenis' => 'sub_kegiatan',
                    'opd_id' => $sub->opd_id,
                    'document_type' => 'renja',
                ],
                [
                    'kode_program' => $this->extractKodeProgram((string) $sub->kode_rek),
                    'sub_unit' => $this->truncateText((string) ($sub->opd?->nama ?? $sub->opd?->kode ?? '-'), 255),
                    'urusan' => $urusan,
                    'bidang_urusan' => $bidangUrusan,
                    'nama_komponen' => $this->truncateText((string) $sub->nama_rincian, 255),
                    'pagu' => (int) ($sub->pagu ?? 0),
                    'tahun' => $sub->tahun ?? $tahun,
                ]
            );

            if ($komponenSub->wasRecentlyCreated) {
                $subCreated++;
            } else {
                $subUpdated++;
            }
        }

        $this->command->info("Komponen RENJA - Program: {$programCreated} ditambahkan, {$programUpdated} diperbarui.");
        $this->command->info("Komponen RENJA - Kegiatan: {$kegiatanCreated} ditambahkan, {$kegiatanUpdated} diperbarui.");
        $this->command->info("Komponen RENJA - Sub kegiatan: {$subCreated} ditambahkan, {$subUpdated} diperbarui.");
    }

    private function importIndikatorAnggaran(Collection $opds, int $tahun): void
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

            $opd = $opds->get($kodeUnit);
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

        $this->command->info("Indikator anggaran RENJA: {$created} ditambahkan, {$updated} diperbarui.");
        $this->command->info("Indikator anggaran RENJA tidak cocok (kode_unit/kode_uraian): {$notFound} baris.");

        if ($skipped > 0) {
            $this->command->warn("Indikator anggaran RENJA dilewati: {$skipped} baris.");
        }
    }

    private function extractKodeProgram(string $kode): string
    {
        $parts = array_values(array_filter(explode('.', $kode), fn ($part) => $part !== ''));
        return implode('.', array_slice($parts, 0, 3));
    }

    private function extractKodeKegiatan(string $kode): string
    {
        $parts = array_values(array_filter(explode('.', $kode), fn ($part) => $part !== ''));
        return implode('.', array_slice($parts, 0, 5));
    }

    private function normalizePagu($value): int
    {
        if (is_string($value)) {
            $value = preg_replace('/[^0-9\-]/', '', $value);
        }

        return (int) ($value ?: 0);
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

    private function normalizeTarget($value): float
    {
        if ($value === null) {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9\.\-]/', '', (string) $value);

        return is_numeric($clean) ? (float) $clean : 0.0;
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

    private function extractUrusanCodes(string $kode): array
    {
        $parts = array_values(array_filter(explode('.', $kode), fn ($part) => $part !== ''));
        $urusan = $parts[0] ?? '-';
        $bidang = count($parts) >= 2 ? ($parts[0] . '.' . $parts[1]) : $urusan;

        return [$urusan, $bidang];
    }

    private function truncateText(string $value, int $length): string
    {
        return mb_substr(trim($value), 0, $length);
    }
}
