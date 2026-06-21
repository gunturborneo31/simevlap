<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Indikator;
use App\Models\IndikatorAnggaran;
use App\Models\Kegiatan;
use App\Models\Kepmen;
use App\Models\KomponenAnggaran;
use App\Models\Opd;
use App\Models\Program;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('import:renstra-referensi', function () {
    $basePath = base_path('referensi/renstra');
    $programPath = $basePath . '/program.json';
    $kegiatanPath = $basePath . '/kegiatan.json';
    $indikatorPath = $basePath . '/indikator.json';

    foreach ([$programPath, $kegiatanPath, $indikatorPath] as $path) {
        if (!is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");
            return;
        }
    }

    $programRows = json_decode(file_get_contents($programPath), true) ?? [];
    $kegiatanRows = json_decode(file_get_contents($kegiatanPath), true) ?? [];
    $indikatorRows = json_decode(file_get_contents($indikatorPath), true) ?? [];

    if (!is_array($programRows) || !is_array($kegiatanRows) || !is_array($indikatorRows)) {
        $this->error('Format JSON tidak valid. Pastikan isi file berupa array objek.');
        return;
    }

    $kepmen = Kepmen::query()->orderBy('id')->first();
    if (!$kepmen) {
        $this->error('Data kepmen belum ada. Tambahkan minimal 1 data kepmen terlebih dahulu.');
        return;
    }

    $tahunAwal = 2025;
    $tahunAkhir = 2030;

    $stats = [
        'program_inserted' => 0,
        'program_updated' => 0,
        'program_skipped_no_opd' => 0,
        'program_skipped_invalid' => 0,
        'kegiatan_inserted' => 0,
        'kegiatan_updated' => 0,
        'kegiatan_skipped_no_opd' => 0,
        'kegiatan_skipped_no_program' => 0,
        'indikator_inserted' => 0,
        'indikator_updated' => 0,
        'indikator_skipped_no_opd' => 0,
        'indikator_skipped_invalid' => 0,
    ];

    DB::transaction(function () use (
        $programRows,
        $kegiatanRows,
        $indikatorRows,
        $kepmen,
        $tahunAwal,
        $tahunAkhir,
        &$stats
    ) {
        foreach ($programRows as $row) {
            $kodeRek = trim((string) ($row['KODE_PROGRAM'] ?? ''));
            $namaRincian = trim((string) ($row['NAMA_PROGRAM'] ?? ''));
            $kodeSkpd = trim((string) ($row['KODE_SKPD'] ?? ''));

            if ($kodeRek === '' || $namaRincian === '' || $kodeSkpd === '' || stripos($kodeSkpd, '#N/A') !== false) {
                $stats['program_skipped_invalid']++;
                continue;
            }

            $opd = Opd::withoutGlobalScopes()->where('kode', $kodeSkpd)->first();
            if (!$opd) {
                $stats['program_skipped_no_opd']++;
                continue;
            }

            $isPrioritas = strtolower(trim((string) ($row['prioritas'] ?? ''))) === 'prioritas';

            $program = Program::withoutGlobalScopes()->where([
                'kode_rek' => $kodeRek,
                'opd_id' => $opd->id,
                'document_type' => 'renstra',
            ])->first();

            if ($program) {
                $program->nama_rincian = $namaRincian;
                $program->kepmen_id = $kepmen->id;
                $program->tahun_awal = $tahunAwal;
                $program->tahun_akhir = $tahunAkhir;
                $program->is_prioritas = $isPrioritas;
                $program->save();
                $stats['program_updated']++;
            } else {
                Program::withoutGlobalScopes()->create([
                    'opd_id' => $opd->id,
                    'kepmen_id' => $kepmen->id,
                    'document_type' => 'renstra',
                    'jenis_program' => 'utama',
                    'kode_rek' => $kodeRek,
                    'nama_rincian' => $namaRincian,
                    'pagu' => 0,
                    'tahun_awal' => $tahunAwal,
                    'tahun_akhir' => $tahunAkhir,
                    'is_prioritas' => $isPrioritas,
                ]);
                $stats['program_inserted']++;
            }
        }

        foreach ($kegiatanRows as $row) {
            $kodeRek = trim((string) ($row['KODE_KEGIATAN'] ?? ''));
            $namaRincian = trim((string) ($row['NAMA_KEGIATAN'] ?? ''));
            $kodeSkpd = trim((string) ($row['KODE_SKPD'] ?? ''));

            if ($kodeRek === '' || $namaRincian === '' || $kodeSkpd === '' || stripos($kodeSkpd, '#N/A') !== false) {
                $stats['kegiatan_skipped_no_opd']++;
                continue;
            }

            $opd = Opd::withoutGlobalScopes()->where('kode', $kodeSkpd)->first();
            if (!$opd) {
                $stats['kegiatan_skipped_no_opd']++;
                continue;
            }

            $parts = array_values(array_filter(explode('.', $kodeRek), fn ($p) => $p !== ''));
            $kodeProgram = implode('.', array_slice($parts, 0, 3));

            $program = Program::withoutGlobalScopes()
                ->where('document_type', 'renstra')
                ->where('opd_id', $opd->id)
                ->where('kode_rek', $kodeProgram)
                ->first();

            if (!$program) {
                $stats['kegiatan_skipped_no_program']++;
                continue;
            }

            $kegiatan = Kegiatan::withoutGlobalScopes()
                ->where('document_type', 'renstra')
                ->where('opd_id', $opd->id)
                ->where('kode_rek', $kodeRek)
                ->first();

            if ($kegiatan) {
                $kegiatan->program_id = $program->id;
                $kegiatan->nama_rincian = $namaRincian;
                $kegiatan->kepmen_id = $kepmen->id;
                $kegiatan->tahun_awal = $tahunAwal;
                $kegiatan->tahun_akhir = $tahunAkhir;
                $kegiatan->save();
                $stats['kegiatan_updated']++;
            } else {
                Kegiatan::withoutGlobalScopes()->create([
                    'program_id' => $program->id,
                    'opd_id' => $opd->id,
                    'kepmen_id' => $kepmen->id,
                    'document_type' => 'renstra',
                    'kode_rek' => $kodeRek,
                    'nama_rincian' => $namaRincian,
                    'tahun_awal' => $tahunAwal,
                    'tahun_akhir' => $tahunAkhir,
                ]);
                $stats['kegiatan_inserted']++;
            }
        }

        foreach ($indikatorRows as $row) {
            $uraian = trim((string) ($row['indikator'] ?? ''));
            $kodeSkpd = trim((string) ($row['kode_opd'] ?? ''));
            $satuan = trim((string) ($row['satuan'] ?? ''));
            $kodeIndikator = trim((string) ($row['KODE_INDIKATOR'] ?? ''));

            if ($uraian === '' || $kodeSkpd === '' || stripos($kodeSkpd, '#N/A') !== false) {
                $stats['indikator_skipped_invalid']++;
                continue;
            }

            $opd = Opd::withoutGlobalScopes()->where('kode', $kodeSkpd)->first();
            if (!$opd) {
                $stats['indikator_skipped_no_opd']++;
                continue;
            }

            $targetTahunan = [];
            for ($y = 2025; $y <= 2030; $y++) {
                $targetTahunan[(string) $y] = $row['target_' . $y] ?? null;
            }

            $keteranganPayload = [
                'kode_indikator' => $kodeIndikator,
                'target_tahunan' => $targetTahunan,
                'sumber' => 'referensi/renstra/indikator.json',
            ];

            $indikator = Indikator::withoutGlobalScopes()->where([
                'document_type' => 'renstra',
                'opd_id' => $opd->id,
                'uraian' => $uraian,
            ])->first();

            if ($indikator) {
                $indikator->satuan = $satuan !== '' ? $satuan : ($indikator->satuan ?: '-');
                $indikator->keterangan = json_encode($keteranganPayload, JSON_UNESCAPED_UNICODE);
                $indikator->save();
                $stats['indikator_updated']++;
            } else {
                Indikator::withoutGlobalScopes()->create([
                    'opd_id' => $opd->id,
                    'document_type' => 'renstra',
                    'jenis_indikator' => 'IKK',
                    'uraian' => $uraian,
                    'satuan' => $satuan !== '' ? $satuan : '-',
                    'jenis' => 'outcome',
                    'sifat' => 'maximize',
                    'keterangan' => json_encode($keteranganPayload, JSON_UNESCAPED_UNICODE),
                ]);
                $stats['indikator_inserted']++;
            }
        }
    });

    $this->info('Import referensi RENSTRA selesai.');
    foreach ($stats as $k => $v) {
        $this->line("- {$k}: {$v}");
    }
})->purpose('Import program, kegiatan, indikator dari referensi/renstra ke data master');

Artisan::command('sync:renstra-komponen', function () {
    $tahunKeys = ['2025', '2026', '2027', '2028', '2029', '2030'];
    $truncate = static fn ($text, $max = 255) => mb_strlen((string) $text) <= $max
        ? (string) $text
        : mb_substr((string) $text, 0, $max);

    $stats = [
        'program_created' => 0,
        'program_updated' => 0,
        'kegiatan_created' => 0,
        'kegiatan_updated' => 0,
        'kegiatan_skipped_no_parent' => 0,
    ];

    DB::transaction(function () use (&$stats, $tahunKeys, $truncate) {
        $programRows = Program::withoutGlobalScopes()
            ->where('document_type', 'renstra')
            ->orderBy('opd_id')
            ->orderBy('kode_rek')
            ->get();

        foreach ($programRows as $program) {
            $opd = Opd::withoutGlobalScopes()->find($program->opd_id);
            if (!$opd) {
                continue;
            }

            $kode = trim((string) $program->kode_rek);
            $parts = array_values(array_filter(explode('.', $kode), fn ($p) => $p !== ''));
            $urusan = $parts[0] ?? '';
            $bidang = count($parts) >= 2 ? ($parts[0] . '.' . $parts[1]) : $urusan;

            $payload = [
                'parent_id' => null,
                'kode' => $kode,
                'kode_program' => $kode,
                'jenis' => 'program',
                'opd_id' => $opd->id,
                'sub_unit' => $opd->nama,
                'urusan' => $urusan,
                'bidang_urusan' => $bidang,
                'nama_komponen' => $truncate($program->nama_rincian),
                'pagu' => 0,
                'pagu_tahunan' => array_fill_keys($tahunKeys, 0),
                'document_type' => 'renstra',
                'tahun' => 2025,
            ];

            $existing = KomponenAnggaran::query()
                ->where('document_type', 'renstra')
                ->where('opd_id', $opd->id)
                ->where('jenis', 'program')
                ->whereNull('parent_id')
                ->where('kode', $kode)
                ->first();

            if ($existing) {
                $existing->update($payload);
                $stats['program_updated']++;
            } else {
                KomponenAnggaran::query()->create($payload);
                $stats['program_created']++;
            }
        }

        $programMap = KomponenAnggaran::query()
            ->where('document_type', 'renstra')
            ->where('jenis', 'program')
            ->whereNull('parent_id')
            ->get()
            ->keyBy(fn ($r) => $r->opd_id . '|' . $r->kode);

        $kegiatanRows = Kegiatan::withoutGlobalScopes()
            ->where('document_type', 'renstra')
            ->orderBy('opd_id')
            ->orderBy('kode_rek')
            ->get();

        foreach ($kegiatanRows as $kegiatan) {
            $opd = Opd::withoutGlobalScopes()->find($kegiatan->opd_id);
            if (!$opd) {
                continue;
            }

            $kode = trim((string) $kegiatan->kode_rek);
            $parts = array_values(array_filter(explode('.', $kode), fn ($p) => $p !== ''));
            $kodeProgram = implode('.', array_slice($parts, 0, 3));
            $urusan = $parts[0] ?? '';
            $bidang = count($parts) >= 2 ? ($parts[0] . '.' . $parts[1]) : $urusan;

            $parent = $programMap->get($opd->id . '|' . $kodeProgram);
            if (!$parent) {
                $stats['kegiatan_skipped_no_parent']++;
                continue;
            }

            $payload = [
                'parent_id' => $parent->id,
                'kode' => $kode,
                'kode_program' => $kodeProgram,
                'jenis' => 'kegiatan',
                'opd_id' => $opd->id,
                'sub_unit' => $opd->nama,
                'urusan' => $urusan,
                'bidang_urusan' => $bidang,
                'nama_komponen' => $truncate($kegiatan->nama_rincian),
                'pagu' => 0,
                'pagu_tahunan' => array_fill_keys($tahunKeys, 0),
                'document_type' => 'renstra',
                'tahun' => 2025,
            ];

            $existing = KomponenAnggaran::query()
                ->where('document_type', 'renstra')
                ->where('opd_id', $opd->id)
                ->where('jenis', 'kegiatan')
                ->where('parent_id', $parent->id)
                ->where('kode', $kode)
                ->first();

            if ($existing) {
                $existing->update($payload);
                $stats['kegiatan_updated']++;
            } else {
                KomponenAnggaran::query()->create($payload);
                $stats['kegiatan_created']++;
            }
        }
    });

    $this->info('Sinkronisasi RENSTRA ke komponen_anggaran selesai.');
    foreach ($stats as $k => $v) {
        $this->line("- {$k}: {$v}");
    }
})->purpose('Sinkronisasi program/kegiatan RENSTRA ke komponen_anggaran untuk tampilan dokumen RENSTRA');

Artisan::command('backfill:renstra-kegiatan-dari-renja', function () {
    $tahunKeys = ['2025', '2026', '2027', '2028', '2029', '2030'];
    $truncate = static fn ($text, $max = 255) => mb_strlen((string) $text) <= $max
        ? (string) $text
        : mb_substr((string) $text, 0, $max);

    $stats = [
        'program_tanpa_kegiatan' => 0,
        'program_terisi' => 0,
        'kegiatan_dibuat' => 0,
        'program_tanpa_sumber_renja' => 0,
        'program_diisi_default' => 0,
    ];

    DB::transaction(function () use (&$stats, $tahunKeys, $truncate) {
        $programTanpaKegiatan = KomponenAnggaran::query()
            ->where('document_type', 'renstra')
            ->where('jenis', 'program')
            ->whereNull('parent_id')
            ->whereDoesntHave('children', function ($q) {
                $q->where('document_type', 'renstra')->where('jenis', 'kegiatan');
            })
            ->orderBy('opd_id')
            ->orderBy('kode')
            ->get();

        $stats['program_tanpa_kegiatan'] = $programTanpaKegiatan->count();

        foreach ($programTanpaKegiatan as $program) {
            $kodeProgram = trim((string) $program->kode);

            $renjaCandidates = KomponenAnggaran::query()
                ->where('document_type', 'renja')
                ->where('jenis', 'kegiatan')
                ->where('opd_id', $program->opd_id)
                ->where('kode', 'like', $kodeProgram . '.%')
                ->orderBy('kode')
                ->get();

            if ($renjaCandidates->isEmpty()) {
                $renjaMasterCandidates = Kegiatan::withoutGlobalScopes()
                    ->where('document_type', 'renja')
                    ->where('opd_id', $program->opd_id)
                    ->where('kode_rek', 'like', $kodeProgram . '.%')
                    ->orderBy('kode_rek')
                    ->get(['kode_rek as kode', 'nama_rincian as nama_komponen']);

                if ($renjaMasterCandidates->isNotEmpty()) {
                    $renjaCandidates = $renjaMasterCandidates;
                }
            }

            $createdForThisProgram = 0;

            foreach ($renjaCandidates as $candidate) {
                $kode = trim((string) $candidate->kode);
                $parts = array_values(array_filter(explode('.', $kode), fn ($p) => $p !== ''));
                $urusan = $parts[0] ?? $program->urusan;
                $bidang = count($parts) >= 2 ? ($parts[0] . '.' . $parts[1]) : $urusan;

                $existing = KomponenAnggaran::query()
                    ->where('document_type', 'renstra')
                    ->where('jenis', 'kegiatan')
                    ->where('parent_id', $program->id)
                    ->where('kode', $kode)
                    ->first();

                if ($existing) {
                    continue;
                }

                KomponenAnggaran::query()->create([
                    'parent_id' => $program->id,
                    'kode' => $kode,
                    'kode_program' => $kodeProgram,
                    'jenis' => 'kegiatan',
                    'opd_id' => $program->opd_id,
                    'sub_unit' => $program->sub_unit,
                    'urusan' => $urusan,
                    'bidang_urusan' => $bidang,
                    'nama_komponen' => $truncate($candidate->nama_komponen),
                    'pagu' => 0,
                    'pagu_tahunan' => array_fill_keys($tahunKeys, 0),
                    'document_type' => 'renstra',
                    'tahun' => 2025,
                ]);

                $createdForThisProgram++;
                $stats['kegiatan_dibuat']++;
            }

            if ($createdForThisProgram === 0) {
                $stats['program_tanpa_sumber_renja']++;

                $fallbackKode = $kodeProgram . '.2.01';
                $fallbackName = 'Kegiatan hasil sinkronisasi RENSTRA';

                $existsFallback = KomponenAnggaran::query()
                    ->where('document_type', 'renstra')
                    ->where('jenis', 'kegiatan')
                    ->where('parent_id', $program->id)
                    ->where('kode', $fallbackKode)
                    ->exists();

                if (!$existsFallback) {
                    KomponenAnggaran::query()->create([
                        'parent_id' => $program->id,
                        'kode' => $fallbackKode,
                        'kode_program' => $kodeProgram,
                        'jenis' => 'kegiatan',
                        'opd_id' => $program->opd_id,
                        'sub_unit' => $program->sub_unit,
                        'urusan' => $program->urusan,
                        'bidang_urusan' => $program->bidang_urusan,
                        'nama_komponen' => $fallbackName,
                        'pagu' => 0,
                        'pagu_tahunan' => array_fill_keys($tahunKeys, 0),
                        'document_type' => 'renstra',
                        'tahun' => 2025,
                    ]);

                    $createdForThisProgram++;
                    $stats['kegiatan_dibuat']++;
                    $stats['program_diisi_default']++;
                }
            }

            if ($createdForThisProgram > 0) {
                $stats['program_terisi']++;
            }
        }
    });

    $this->info('Backfill kegiatan RENSTRA dari RENJA selesai.');
    foreach ($stats as $k => $v) {
        $this->line("- {$k}: {$v}");
    }
})->purpose('Isi kegiatan RENSTRA dari data RENJA untuk program yang belum punya kegiatan');

Artisan::command('fix:renstra-nama-kegiatan', function () {
    $jsonPath = base_path('referensi/renstra/kegiatan.json');
    if (!is_file($jsonPath)) {
        $this->error('File referensi kegiatan RENSTRA tidak ditemukan.');
        return;
    }

    $rawJson = trim((string) file_get_contents($jsonPath));
    $rows = json_decode($rawJson, true);
    if (!is_array($rows)) {
        $wrapped = '[' . trim($rawJson, "\r\n\t ,") . ']';
        $rows = json_decode($wrapped, true);
    }
    $rows = is_array($rows) ? $rows : [];

    if (empty($rows) && preg_match_all('/\{(?:[^{}]|(?R))*\}/s', $rawJson, $matches)) {
        $parsed = [];
        foreach (($matches[0] ?? []) as $chunk) {
            $obj = json_decode($chunk, true);
            if (is_array($obj)) {
                $parsed[] = $obj;
            }
        }
        if (!empty($parsed)) {
            $rows = $parsed;
        }
    }
    if (!is_array($rows)) {
        $this->error('Format JSON kegiatan RENSTRA tidak valid.');
        return;
    }

    $nameByCodeSkpd = [];
    $nameByCode = [];

    foreach ($rows as $row) {
        $kode = trim((string) ($row['KODE_KEGIATAN'] ?? ''));
        $nama = trim((string) ($row['NAMA_KEGIATAN'] ?? ''));
        $skpd = trim((string) ($row['KODE_SKPD'] ?? ''));

        if ($kode === '' || $nama === '') {
            continue;
        }

        if ($skpd !== '' && stripos($skpd, '#N/A') === false) {
            $nameByCodeSkpd[$kode . '|' . $skpd] = $nama;
        }

        if (!isset($nameByCode[$kode])) {
            $nameByCode[$kode] = $nama;
        }
    }

    $stats = [
        'target_rows' => 0,
        'updated' => 0,
        'skipped_no_match' => 0,
    ];

    DB::transaction(function () use (&$stats, $nameByCodeSkpd, $nameByCode) {
        $targets = KomponenAnggaran::query()
            ->where('document_type', 'renstra')
            ->where('jenis', 'kegiatan')
            ->where('nama_komponen', 'like', '%(otomatis)%')
            ->get();

        $stats['target_rows'] = $targets->count();

        foreach ($targets as $item) {
            $opd = Opd::withoutGlobalScopes()->find($item->opd_id);
            $opdKode = trim((string) ($opd?->kode ?? ''));
            $kode = trim((string) ($item->kode ?? ''));

            $replacement = null;
            if ($opdKode !== '' && isset($nameByCodeSkpd[$kode . '|' . $opdKode])) {
                $replacement = $nameByCodeSkpd[$kode . '|' . $opdKode];
            } elseif (isset($nameByCode[$kode])) {
                $replacement = $nameByCode[$kode];
            }

            if (!$replacement) {
                $stats['skipped_no_match']++;
                continue;
            }

            $item->update([
                'nama_komponen' => mb_strlen($replacement) > 255 ? mb_substr($replacement, 0, 255) : $replacement,
            ]);
            $stats['updated']++;
        }
    });

    $this->info('Perbaikan nama kegiatan RENSTRA selesai.');
    foreach ($stats as $k => $v) {
        $this->line("- {$k}: {$v}");
    }
})->purpose('Ganti nama kegiatan RENSTRA yang masih berlabel otomatis dari referensi JSON');

Artisan::command('sync:renstra-indikator-anggaran', function () {
    $tahunKeys = ['2025', '2026', '2027', '2028', '2029', '2030'];

    $toSifatAnggaran = static function (?string $sifatMaster): string {
        return match (strtolower(trim((string) $sifatMaster))) {
            'minimize' => 'negatif',
            'stabilize' => 'akumulatif',
            default => 'positif',
        };
    };

    $kodeHead = static function (?string $kode): string {
        $parts = array_values(array_filter(explode('.', trim((string) $kode)), fn ($p) => $p !== ''));
        if (count($parts) < 2) {
            return '';
        }
        return $parts[0] . '.' . $parts[1];
    };

    $stats = [
        'program_target' => 0,
        'program_inserted' => 0,
        'program_skipped_duplicate' => 0,
        'program_skipped_no_source' => 0,
        'kegiatan_target' => 0,
        'kegiatan_inserted' => 0,
        'kegiatan_skipped_duplicate' => 0,
        'kegiatan_skipped_no_source' => 0,
    ];

    DB::transaction(function () use (&$stats, $tahunKeys, $toSifatAnggaran, $kodeHead) {
        $programKomponen = KomponenAnggaran::query()
            ->where('document_type', 'renstra')
            ->where('jenis', 'program')
            ->get();

        $stats['program_target'] = $programKomponen->count();

        $masterByOpdHead = [];
        $masterRows = Indikator::withoutGlobalScopes()
            ->where('document_type', 'renstra')
            ->get();

        foreach ($masterRows as $master) {
            $keterangan = json_decode((string) ($master->keterangan ?? ''), true);
            $kodeIndikator = trim((string) (($keterangan['kode_indikator'] ?? '')));
            $head = $kodeHead($kodeIndikator);
            if ($head === '') {
                continue;
            }

            $key = (int) $master->opd_id . '|' . $head;
            $masterByOpdHead[$key] = $masterByOpdHead[$key] ?? [];
            $masterByOpdHead[$key][] = $master;
        }

        foreach ($programKomponen as $program) {
            $head = $kodeHead((string) $program->kode);
            $key = (int) $program->opd_id . '|' . $head;
            $sources = $masterByOpdHead[$key] ?? [];

            if (empty($sources)) {
                $stats['program_skipped_no_source']++;
                continue;
            }

            foreach ($sources as $src) {
                $keterangan = json_decode((string) ($src->keterangan ?? ''), true);
                $targetTahunan = [];
                foreach ($tahunKeys as $tahun) {
                    $targetTahunan[$tahun] = $keterangan['target_tahunan'][$tahun] ?? null;
                }

                $exists = IndikatorAnggaran::query()
                    ->where('komponen_anggaran_id', $program->id)
                    ->where('nama_indikator', $src->uraian)
                    ->where('satuan', $src->satuan)
                    ->exists();

                if ($exists) {
                    $stats['program_skipped_duplicate']++;
                    continue;
                }

                IndikatorAnggaran::query()->create([
                    'komponen_anggaran_id' => $program->id,
                    'nama_indikator' => $src->uraian,
                    'sifat_indikator' => $toSifatAnggaran($src->sifat),
                    'target_indikator' => null,
                    'target_tahunan' => $targetTahunan,
                    'satuan' => $src->satuan ?: '-',
                ]);
                $stats['program_inserted']++;
            }
        }

        $renjaKegiatan = KomponenAnggaran::query()
            ->where('document_type', 'renja')
            ->where('jenis', 'kegiatan')
            ->with('indikator')
            ->get()
            ->groupBy(fn ($k) => $k->opd_id . '|' . $k->kode);

        $renstraKegiatan = KomponenAnggaran::query()
            ->where('document_type', 'renstra')
            ->where('jenis', 'kegiatan')
            ->get();

        $stats['kegiatan_target'] = $renstraKegiatan->count();

        foreach ($renstraKegiatan as $target) {
            $key = $target->opd_id . '|' . $target->kode;
            $sources = $renjaKegiatan->get($key);

            if (!$sources || $sources->isEmpty()) {
                $stats['kegiatan_skipped_no_source']++;
                continue;
            }

            $insertedForThisTarget = 0;

            foreach ($sources as $sourceKomponen) {
                foreach (($sourceKomponen->indikator ?? collect()) as $srcInd) {
                    $exists = IndikatorAnggaran::query()
                        ->where('komponen_anggaran_id', $target->id)
                        ->where('nama_indikator', $srcInd->nama_indikator)
                        ->where('satuan', $srcInd->satuan)
                        ->exists();

                    if ($exists) {
                        $stats['kegiatan_skipped_duplicate']++;
                        continue;
                    }

                    IndikatorAnggaran::query()->create([
                        'komponen_anggaran_id' => $target->id,
                        'nama_indikator' => $srcInd->nama_indikator,
                        'sifat_indikator' => $srcInd->sifat_indikator ?: 'positif',
                        'target_indikator' => $srcInd->target_indikator,
                        'target_tahunan' => $srcInd->target_tahunan,
                        'satuan' => $srcInd->satuan ?: '-',
                    ]);
                    $stats['kegiatan_inserted']++;
                    $insertedForThisTarget++;
                }
            }

            if ($insertedForThisTarget === 0 && $sources->isNotEmpty()) {
                $stats['kegiatan_skipped_no_source']++;
            }
        }
    });

    $this->info('Sinkron indikator RENSTRA ke indikator_anggaran selesai.');
    foreach ($stats as $k => $v) {
        $this->line("- {$k}: {$v}");
    }
})->purpose('Isi indikator halaman RENSTRA: program dari master indikator RENSTRA, kegiatan dari indikator RENJA');

Artisan::command('import:renja-indikator-rkpd', function () {
    $jsonPath = base_path('referensi/rkpd/indikator_fix.json');
    if (!is_file($jsonPath)) {
        $this->error('File indikator RKPD tidak ditemukan: referensi/rkpd/indikator_fix.json');
        return;
    }

    $rows = json_decode(file_get_contents($jsonPath), true) ?? [];
    if (!is_array($rows)) {
        $this->error('Format JSON indikator RKPD tidak valid.');
        return;
    }

    $stats = [
        'rows_total' => 0,
        'rows_kegiatan' => 0,
        'skipped_invalid' => 0,
        'skipped_no_opd' => 0,
        'skipped_no_komponen_renja' => 0,
        'inserted' => 0,
        'duplicate' => 0,
    ];

    DB::transaction(function () use ($rows, &$stats) {
        $stats['rows_total'] = count($rows);

        foreach ($rows as $row) {
            $jenis = strtolower(trim((string) ($row['jenis_indikator'] ?? '')));
            if ($jenis !== 'kegiatan') {
                continue;
            }

            $stats['rows_kegiatan']++;

            $kodeOpd = trim((string) ($row['kode_unit'] ?? ''));
            $kodeKegiatan = trim((string) ($row['kode_uraian'] ?? ''));
            $namaIndikator = trim((string) ($row['indikator'] ?? ''));
            $target = $row['TARGET'] ?? null;
            $satuan = trim((string) ($row['SATUAN'] ?? ''));

            if ($kodeOpd === '' || $kodeKegiatan === '' || $namaIndikator === '' || $namaIndikator === '-') {
                $stats['skipped_invalid']++;
                continue;
            }

            $opd = Opd::withoutGlobalScopes()->where('kode', $kodeOpd)->first();
            if (!$opd) {
                $stats['skipped_no_opd']++;
                continue;
            }

            $komponen = KomponenAnggaran::query()
                ->where('document_type', 'renja')
                ->where('jenis', 'kegiatan')
                ->where('opd_id', $opd->id)
                ->where('kode', $kodeKegiatan)
                ->first();

            if (!$komponen) {
                $stats['skipped_no_komponen_renja']++;
                continue;
            }

            $targetStr = ($target === null || $target === '-') ? null : trim((string) $target);
            $satuanFix = $satuan !== '' && $satuan !== '-' ? $satuan : '-';

            $exists = IndikatorAnggaran::query()
                ->where('komponen_anggaran_id', $komponen->id)
                ->where('nama_indikator', $namaIndikator)
                ->where('satuan', $satuanFix)
                ->exists();

            if ($exists) {
                $stats['duplicate']++;
                continue;
            }

            IndikatorAnggaran::query()->create([
                'komponen_anggaran_id' => $komponen->id,
                'nama_indikator' => $namaIndikator,
                'sifat_indikator' => 'positif',
                'target_indikator' => $targetStr,
                'target_tahunan' => null,
                'satuan' => $satuanFix,
            ]);

            $stats['inserted']++;
        }
    });

    $this->info('Import indikator RENJA dari RKPD selesai.');
    foreach ($stats as $k => $v) {
        $this->line("- {$k}: {$v}");
    }
})->purpose('Import indikator kegiatan RENJA dari referensi/rkpd/indikator_fix.json ke indikator_anggaran');

Artisan::command('import:renstra-pagu-program', function () {
    $preferredPath = base_path('referensi/pagu_program.json');
    $fallbackPath = base_path('referensi/renstra/pagu_program.json');
    $jsonPath = is_file($preferredPath) ? $preferredPath : $fallbackPath;

    if (!is_file($jsonPath)) {
        $this->error('File pagu program RENSTRA tidak ditemukan. Cek referensi/pagu_program.json atau referensi/renstra/pagu_program.json');
        return;
    }

    $rawJson = trim((string) file_get_contents($jsonPath));
    $rows = json_decode($rawJson, true);

    if (!is_array($rows)) {
        $wrapped = '[' . trim($rawJson, "\r\n\t ,") . ']';
        $rows = json_decode($wrapped, true);
    }

    $rows = is_array($rows) ? $rows : [];

    if (empty($rows) && preg_match_all('/\{(?:[^{}]|(?R))*\}/s', $rawJson, $matches)) {
        $parsed = [];
        foreach (($matches[0] ?? []) as $chunk) {
            $obj = json_decode($chunk, true);
            if (is_array($obj)) {
                $parsed[] = $obj;
            }
        }
        if (!empty($parsed)) {
            $rows = $parsed;
        }
    }

    if (empty($rows)) {
        $this->error('Data pagu program tidak bisa diparsing dari file sumber.');
        return;
    }

    $normalizeCode = static function (?string $code): string {
        $parts = array_values(array_filter(explode('.', trim((string) $code)), fn ($p) => $p !== ''));
        if (count($parts) === 0) {
            return '';
        }

        if (count($parts) >= 4) {
            $head1 = $parts[0];
            $head2 = $parts[1];
            $tailRaw = preg_replace('/\D+/', '', (string) ($parts[2] . $parts[3]));
            $tail = $tailRaw === '' ? strtoupper((string) ($parts[2] . $parts[3])) : str_pad((string) ((int) $tailRaw), 2, '0', STR_PAD_LEFT);
            return strtoupper($head1 . '.' . $head2 . '.' . $tail);
        }

        if (count($parts) === 3) {
            $head1 = $parts[0];
            $head2 = $parts[1];
            $tailRaw = preg_replace('/\D+/', '', (string) $parts[2]);
            $tail = $tailRaw === '' ? strtoupper((string) $parts[2]) : str_pad((string) ((int) $tailRaw), 2, '0', STR_PAD_LEFT);
            return strtoupper($head1 . '.' . $head2 . '.' . $tail);
        }

        return strtoupper(trim((string) $code));
    };

    $normalizeName = static function (?string $name): string {
        $raw = strtoupper(trim((string) $name));
        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('/\s+/u', ' ', $raw);
        $raw = str_replace(['\n', '\r', '\t'], ' ', $raw);
        $raw = preg_replace('/[^A-Z0-9 ]+/u', ' ', $raw);
        return preg_replace('/\s+/u', '', trim((string) $raw));
    };

    $toNullableInt = static function ($value): ?int {
        if ($value === null) return null;
        $raw = trim((string) $value);
        if ($raw === '' || $raw === '-' || strcasecmp($raw, 'null') === 0) return null;
        $digits = preg_replace('/[^0-9\-]/', '', $raw);
        if ($digits === '' || $digits === '-') return null;
        return (int) $digits;
    };

    $stats = [
        'rows_total' => 0,
        'rows_valid' => 0,
        'updated_program' => 0,
        'updated_program_master' => 0,
        'skipped_invalid_row' => 0,
        'skipped_no_opd' => 0,
        'mapped_opd_by_name' => 0,
        'skipped_no_program' => 0,
        'fallback_2026_checked' => 0,
        'fallback_2026_filled' => 0,
        'fallback_2026_filled_by_name' => 0,
        'fallback_2026_filled_from_ref_opd_name' => 0,
        'fallback_2026_filled_from_ref_code' => 0,
        'fallback_2026_filled_from_ref_neighbor' => 0,
        'fallback_2026_no_source' => 0,
    ];

    DB::transaction(function () use (&$stats, $rows, $normalizeCode, $normalizeName, $toNullableInt) {
        $stats['rows_total'] = count($rows);

        $opdCollection = Opd::withoutGlobalScopes()->get(['id', 'kode', 'nama']);
        $opdMap = $opdCollection->keyBy('kode');
        $opdByName = $opdCollection->keyBy(function ($opd) use ($normalizeName) {
            return $normalizeName((string) ($opd->nama ?? ''));
        });
        $opdNameById = $opdCollection->mapWithKeys(function ($opd) use ($normalizeName) {
            return [$opd->id => $normalizeName((string) ($opd->nama ?? ''))];
        })->all();

        $programMap = KomponenAnggaran::query()
            ->where('document_type', 'renstra')
            ->where('jenis', 'program')
            ->get()
            ->keyBy(fn ($r) => $r->opd_id . '|' . $normalizeCode((string) $r->kode));

        $source2026ByOpdNameAndProgramName = [];
        $source2026ByCode = [];
        foreach ($rows as $row) {
            $nilai2026 = $toNullableInt($row['pagu_2026'] ?? null);
            if ($nilai2026 === null || $nilai2026 <= 0) {
                continue;
            }

            $refKode = $normalizeCode((string) ($row['kode_program'] ?? ''));
            if ($refKode !== '' && !isset($source2026ByCode[$refKode])) {
                $source2026ByCode[$refKode] = (int) $nilai2026;
            }

            $refOpdName = $normalizeName((string) ($row['opd'] ?? ''));
            $refProgramName = $normalizeName((string) ($row['nama_kegiatan'] ?? ''));
            if ($refOpdName !== '' && $refProgramName !== '') {
                $refKey = $refOpdName . '|' . $refProgramName;
                if (!isset($source2026ByOpdNameAndProgramName[$refKey])) {
                    $source2026ByOpdNameAndProgramName[$refKey] = (int) $nilai2026;
                }
            }
        }

        foreach ($rows as $row) {
            $kodeOpd = trim((string) ($row['kode_opd'] ?? ''));
            $kodeProgramRaw = trim((string) ($row['kode_program'] ?? ''));
            if ($kodeOpd === '' || $kodeProgramRaw === '' || stripos($kodeOpd, '#N/A') !== false) {
                $stats['skipped_invalid_row']++;
                continue;
            }

            $opd = $opdMap->get($kodeOpd);
            if (!$opd) {
                $opdNameKey = $normalizeName((string) ($row['opd'] ?? ''));
                if ($opdNameKey !== '') {
                    $opd = $opdByName->get($opdNameKey);
                    if ($opd) {
                        $stats['mapped_opd_by_name']++;
                    }
                }
            }
            if (!$opd) {
                $stats['skipped_no_opd']++;
                continue;
            }

            $kodeProgram = $normalizeCode($kodeProgramRaw);
            $target = $programMap->get($opd->id . '|' . $kodeProgram);
            if (!$target) {
                $stats['skipped_no_program']++;
                continue;
            }

            $stats['rows_valid']++;

            $current = $target->pagu_tahunan ?? [];
            foreach (['2025', '2026', '2027', '2028', '2029', '2030'] as $tahun) {
                $nilai = $toNullableInt($row['pagu_' . $tahun] ?? null);
                if ($nilai !== null) {
                    $current[$tahun] = $nilai;
                }
            }

            $nilaiPagu = (int) ($current['2026'] ?? 0);
            $target->update([
                'pagu_tahunan' => $current,
                'pagu' => $nilaiPagu,
            ]);

            $updatedMaster = Program::withoutGlobalScopes()
                ->where('document_type', 'renstra')
                ->where('opd_id', $opd->id)
                ->whereRaw('REPLACE(kode_rek, ".", "") = ?', [str_replace('.', '', $kodeProgram)])
                ->update(['pagu' => $nilaiPagu]);

            $stats['updated_program_master'] += (int) $updatedMaster;
            $stats['updated_program']++;
        }

        $nonPrioritas = Program::withoutGlobalScopes()
            ->where('document_type', 'renstra')
            ->where('is_prioritas', false)
            ->get(['opd_id', 'kode_rek']);

        foreach ($nonPrioritas as $masterProgram) {
            $kode = $normalizeCode((string) $masterProgram->kode_rek);
            $target = $programMap->get($masterProgram->opd_id . '|' . $kode);
            if (!$target) {
                continue;
            }

            $stats['fallback_2026_checked']++;

            $current = $target->pagu_tahunan ?? [];
            $current2026 = $toNullableInt($current['2026'] ?? null);
            if ($current2026 !== null && $current2026 > 0) {
                if ((int) ($target->pagu ?? 0) <= 0) {
                    $target->update(['pagu' => (int) $current2026]);
                }

                Program::withoutGlobalScopes()
                    ->where('document_type', 'renstra')
                    ->where('opd_id', $target->opd_id)
                    ->whereRaw('REPLACE(kode_rek, ".", "") = ?', [str_replace('.', '', $kode)])
                    ->update(['pagu' => (int) $current2026]);
                continue;
            }

            $renjaSource = KomponenAnggaran::query()
                ->where('document_type', 'renja')
                ->where('jenis', 'program')
                ->where('opd_id', $target->opd_id)
                ->whereRaw('REPLACE(kode, ".", "") = ?', [str_replace('.', '', $kode)])
                ->where('tahun', 2026)
                ->where('pagu', '>', 0)
                ->orderByDesc('id')
                ->value('pagu');

            if (!$renjaSource || (int) $renjaSource <= 0) {
                $renjaMaster = Program::withoutGlobalScopes()
                    ->where('document_type', 'renja')
                    ->where('opd_id', $target->opd_id)
                    ->whereRaw('REPLACE(kode_rek, ".", "") = ?', [str_replace('.', '', $kode)])
                    ->where('tahun', 2026)
                    ->where('pagu', '>', 0)
                    ->orderByDesc('id')
                    ->value('pagu');

                $renjaSource = $renjaMaster ?: null;
            }

            $filledByName = false;
            $filledFromRefOpdName = false;
            $filledFromRefCode = false;
            $filledFromRefNeighbor = false;
            if (!$renjaSource || (int) $renjaSource <= 0) {
                $targetNama = $normalizeName((string) ($target->nama_komponen ?? ''));

                if ($targetNama !== '') {
                    $renjaByNamaKomponen = KomponenAnggaran::query()
                        ->where('document_type', 'renja')
                        ->where('jenis', 'program')
                        ->where('opd_id', $target->opd_id)
                        ->where('tahun', 2026)
                        ->where('pagu', '>', 0)
                        ->get(['nama_komponen', 'pagu'])
                        ->first(function ($item) use ($normalizeName, $targetNama) {
                            return $normalizeName((string) ($item->nama_komponen ?? '')) === $targetNama;
                        });

                    if ($renjaByNamaKomponen && (int) ($renjaByNamaKomponen->pagu ?? 0) > 0) {
                        $renjaSource = (int) $renjaByNamaKomponen->pagu;
                        $filledByName = true;
                    }
                }

                if ((!$renjaSource || (int) $renjaSource <= 0) && $targetNama !== '') {
                    $renjaByNamaMaster = Program::withoutGlobalScopes()
                        ->where('document_type', 'renja')
                        ->where('opd_id', $target->opd_id)
                        ->where('tahun', 2026)
                        ->where('pagu', '>', 0)
                        ->get(['nama_rincian', 'pagu'])
                        ->first(function ($item) use ($normalizeName, $targetNama) {
                            return $normalizeName((string) ($item->nama_rincian ?? '')) === $targetNama;
                        });

                    if ($renjaByNamaMaster && (int) ($renjaByNamaMaster->pagu ?? 0) > 0) {
                        $renjaSource = (int) $renjaByNamaMaster->pagu;
                        $filledByName = true;
                    }
                }

                if ((!$renjaSource || (int) $renjaSource <= 0) && $targetNama !== '') {
                    $targetOpdName = $opdNameById[$target->opd_id] ?? '';
                    if ($targetOpdName !== '') {
                        $refKey = $targetOpdName . '|' . $targetNama;
                        $refSourceByOpdName = $source2026ByOpdNameAndProgramName[$refKey] ?? null;
                        if ($refSourceByOpdName && (int) $refSourceByOpdName > 0) {
                            $renjaSource = (int) $refSourceByOpdName;
                            $filledByName = true;
                            $filledFromRefOpdName = true;
                        }
                    }
                }
            }

            if ((!$renjaSource || (int) $renjaSource <= 0)) {
                $refSourceByCode = $source2026ByCode[$kode] ?? null;
                if ($refSourceByCode && (int) $refSourceByCode > 0) {
                    $renjaSource = (int) $refSourceByCode;
                    $filledFromRefCode = true;
                }
            }

            if ((!$renjaSource || (int) $renjaSource <= 0) && preg_match('/^(\d+\.\d+)\.(\d{1,2})$/', $kode, $m)) {
                $prefix = $m[1];
                $tail = (int) $m[2];
                if ($tail > 0) {
                    $prevKey = $prefix . '.' . str_pad((string) ($tail - 1), 2, '0', STR_PAD_LEFT);
                    $nextKey = $prefix . '.' . str_pad((string) ($tail + 1), 2, '0', STR_PAD_LEFT);
                    $prevVal = $source2026ByCode[$prevKey] ?? null;
                    $nextVal = $source2026ByCode[$nextKey] ?? null;

                    if ($prevVal && $nextVal) {
                        $renjaSource = (int) round((((int) $prevVal) + ((int) $nextVal)) / 2);
                        $filledFromRefNeighbor = true;
                    } elseif ($prevVal) {
                        $renjaSource = (int) $prevVal;
                        $filledFromRefNeighbor = true;
                    } elseif ($nextVal) {
                        $renjaSource = (int) $nextVal;
                        $filledFromRefNeighbor = true;
                    }
                }
            }

            if ($renjaSource && (int) $renjaSource > 0) {
                $current['2026'] = (int) $renjaSource;
                $target->update([
                    'pagu_tahunan' => $current,
                    'pagu' => (int) $renjaSource,
                ]);

                Program::withoutGlobalScopes()
                    ->where('document_type', 'renstra')
                    ->where('opd_id', $target->opd_id)
                    ->whereRaw('REPLACE(kode_rek, ".", "") = ?', [str_replace('.', '', $kode)])
                    ->update(['pagu' => (int) $renjaSource]);

                $stats['fallback_2026_filled']++;
                if ($filledByName) {
                    $stats['fallback_2026_filled_by_name']++;
                }
                if ($filledFromRefOpdName) {
                    $stats['fallback_2026_filled_from_ref_opd_name']++;
                }
                if ($filledFromRefCode) {
                    $stats['fallback_2026_filled_from_ref_code']++;
                }
                if ($filledFromRefNeighbor) {
                    $stats['fallback_2026_filled_from_ref_neighbor']++;
                }
            } else {
                $stats['fallback_2026_no_source']++;
            }
        }
    });

    $this->info('Import pagu RENSTRA program selesai.');
    $this->line('Sumber file: ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $jsonPath));
    foreach ($stats as $k => $v) {
        $this->line("- {$k}: {$v}");
    }
})->purpose('Import pagu RENSTRA program dari referensi dan isi fallback pagu 2026 non-prioritas dari RENJA');

Artisan::command('import:pusat-ikk', function () {
    $jsonPath = base_path('referensi/pusat/ikk.json');
    if (!is_file($jsonPath)) {
        $this->error('File IKK tidak ditemukan: referensi/pusat/ikk.json');
        return;
    }

    $rows = json_decode(file_get_contents($jsonPath), true) ?? [];
    if (!is_array($rows)) {
        $this->error('Format JSON IKK tidak valid. Pastikan isi file berupa array objek.');
        return;
    }

    $normalize = static function (?string $value): string {
        $text = strtoupper(trim((string) $value));
        $text = preg_replace('/[^A-Z0-9\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return trim((string) $text);
    };

    $opdByName = Opd::withoutGlobalScopes()
        ->where('is_active', true)
        ->get(['id', 'nama'])
        ->keyBy(fn ($opd) => $normalize((string) $opd->nama));

    $resolveOpdName = static function (string $urusan2, string $indikator) use ($normalize): ?string {
        $u = $normalize($urusan2);
        $i = $normalize($indikator);

        if (str_contains($u, 'KECAMATAN LONG APARAI')) return 'Kecamatan Long Apari';
        if (str_contains($u, 'KECAMATAN LONG PAHANGAI')) return 'Kecamatan Long Pahangai';
        if (str_contains($u, 'KECAMATAN LONG BAGUN')) return 'Kecamatan Long Bagun';
        if (str_contains($u, 'KECAMATAN LONG HUBUNG')) return 'Kecamatan Long Hubung';
        if (str_contains($u, 'KECAMATAN LONG LAHAM') || str_contains($u, 'KECAMATAN LAHAM')) return 'Kecamatan Laham';

        if (str_contains($u, 'SEKRETARIAT DPRD')) return 'Sekretariat DPRD';
        if (str_contains($u, 'SEKRETARIAT DAERAH')) return 'Sekretariat Daerah';
        if (str_contains($u, 'PERENCANAAN') || str_contains($u, 'PENELITIAN') || str_contains($u, 'PENGEMBANGAN')) {
            return 'Badan Perencanaan Pembangunan, Penelitian dan Pengembangan Daerah';
        }
        if (str_contains($u, 'KEUANGAN')) return 'Badan Pengelola Keuangan dan Aset Daerah';
        if (str_contains($u, 'KEPEGAWAIAN') || str_contains($u, 'PENDIDIKAN DAN PELATIHAN')) {
            return 'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia';
        }
        if (str_contains($u, 'PERBATASAN')) return 'Badan Pengelola Perbatasan Daerah';
        if (str_contains($u, 'INSPEKTORA') || str_contains($u, 'INSPEKTORAT')) return 'Inspektorat';
        if (str_contains($u, 'KESATUAN BANGSA') || str_contains($u, 'POLITIK')) return 'Badan Kesatuan Bangsa dan Politik';

        if (str_contains($u, 'PENDIDIKAN') || str_contains($u, 'KEBUDAYAAN') || str_contains($u, 'PERPUSTAKAAN') || str_contains($u, 'KEARSIPAN')) {
            return 'Dinas Pendidikan dan Kebudayaan';
        }
        if (str_contains($u, 'KESEHATAN') || str_contains($u, 'KELUARGA BERENCANA') || str_contains($u, 'PENGENDALIAN PENDUDUK')) {
            return 'Dinas Kesehatan, Pengendalian Penduduk dan KB';
        }
        if (str_contains($u, 'PEKERJAAN UMUM') || str_contains($u, 'PENATAAN RUANG') || str_contains($u, 'PERUMAHAN') || str_contains($u, 'KAWASAN PEMUKIMAN')) {
            return 'Dinas Pekerjaan Umum dan Penataan Ruang, Perumahan dan Kawasan Pemukiman';
        }
        if (str_contains($u, 'KETENTERAMAN') || str_contains($u, 'KETERTIBAN')) return 'Satuan Polisi Pamong Praja';
        if (str_contains($u, 'BENCANA')) return 'Badan Penanggulangan Bencana Daerah';
        if (str_contains($u, 'SOSIAL') || str_contains($u, 'PEREMPUAN') || str_contains($u, 'ANAK')) {
            return 'Dinas Sosial, Pemberdayaan Perempuan Perlindungan Anak';
        }
        if (str_contains($u, 'PANGAN') || str_contains($u, 'PERTANIAN') || str_contains($u, 'KELAUTAN') || str_contains($u, 'PERIKANAN')) {
            return 'Dinas Ketahanan Pangan dan Pertanian';
        }
        if (str_contains($u, 'LINGKUNGAN HIDUP')) return 'Dinas Lingkungan Hidup';
        if (str_contains($u, 'KEPENDUDUKAN') || str_contains($u, 'PENCATATAN SIPIL')) return 'Dinas Kependudukan dan Pencatatan Sipil';
        if (str_contains($u, 'PEMBERDAYAAN MASYARAKAT') || str_contains($u, 'DESA') || str_contains($u, 'KAMPUNG')) {
            return 'Dinas Pemberdayaan Masyarakat dan Pemerintahan Kampung';
        }
        if (str_contains($u, 'PERHUBUNGAN')) return 'Dinas Perhubungan';
        if (str_contains($u, 'KOMUNIKASI') || str_contains($u, 'INFORMATIKA') || str_contains($u, 'STATISTIK') || str_contains($u, 'PERSANDIAN')) {
            return 'Dinas Komunikasi dan Informatika, Statistik, dan Persandian';
        }
        if (str_contains($u, 'PENANAMAN MODAL')) return 'Dinas Penanaman Modal dan Pelayanan Perijinan Terpadu';
        if (str_contains($u, 'PARIWISATA') || str_contains($u, 'KEPEMUDAAN') || str_contains($u, 'OLAHRAGA')) {
            return 'Dinas Pariwisata, Pemuda dan Olahraga';
        }
        if (str_contains($u, 'KOPERASI') || str_contains($u, 'USAHA KECIL') || str_contains($u, 'MENENGAH') || str_contains($u, 'PERDAGANGAN') || str_contains($u, 'PERINDUSTRIAN')) {
            return 'Bagian Perekonomian dan Sumber Daya Alam';
        }

        if (str_contains($i, 'KESEHATAN') || str_contains($i, 'HIDUP SEHAT') || str_contains($i, 'POSYANDU') || str_contains($i, 'BALITA') || str_contains($i, 'IMUNISASI')) {
            return 'Dinas Kesehatan, Pengendalian Penduduk dan KB';
        }
        if (str_contains($i, 'FAKIR MISKIN') || str_contains($i, 'LANSIA') || str_contains($i, 'PENYANDANG DISABILITAS') || str_contains($i, 'GELANDANG')) {
            return 'Dinas Sosial, Pemberdayaan Perempuan Perlindungan Anak';
        }

        return null;
    };

    $stats = [
        'rows_total' => count($rows),
        'rows_mapped_opd' => 0,
        'rows_unmapped_opd' => 0,
        'inserted' => 0,
        'updated' => 0,
    ];

    $unmapped = [];

    DB::transaction(function () use ($rows, $opdByName, $normalize, $resolveOpdName, &$stats, &$unmapped) {
        foreach ($rows as $index => $row) {
            $uraian = trim((string) ($row['indikator'] ?? ''));
            if ($uraian === '') {
                continue;
            }

            $urusan1 = trim((string) ($row['urusan_1'] ?? ''));
            $urusan2 = trim((string) ($row['urusan_2'] ?? ''));
            $satuan = trim((string) ($row['satuan'] ?? ''));

            $namaOpd = $resolveOpdName($urusan2, $uraian);
            $opd = $namaOpd ? ($opdByName->get($normalize($namaOpd)) ?? null) : null;

            if ($opd) {
                $stats['rows_mapped_opd']++;
            } else {
                $stats['rows_unmapped_opd']++;
                $unmapped[] = [
                    'row' => $index + 1,
                    'urusan_2' => $urusan2,
                    'indikator' => $uraian,
                ];
            }

            $targetTahunan = [];
            foreach (['2025', '2026', '2027', '2028', '2029', '2030'] as $tahun) {
                $targetTahunan[$tahun] = $row['target_' . $tahun] ?? null;
            }

            $payload = [
                'opd_id' => $opd?->id,
                'document_type' => 'rpjmd',
                'jenis_indikator' => 'IKK',
                'uraian' => $uraian,
                'satuan' => $satuan !== '' ? $satuan : '-',
                'jenis' => 'outcome',
                'sifat' => 'maximize',
                'keterangan' => json_encode([
                    'sumber' => 'referensi/pusat/ikk.json',
                    'urusan_1' => $urusan1,
                    'urusan_2' => $urusan2,
                    'target_tahunan' => $targetTahunan,
                ], JSON_UNESCAPED_UNICODE),
            ];

            $existing = Indikator::withoutGlobalScopes()
                ->where('jenis_indikator', 'IKK')
                ->where('uraian', $uraian)
                ->first();

            if ($existing) {
                $existing->update($payload);
                $stats['updated']++;
            } else {
                Indikator::withoutGlobalScopes()->create($payload);
                $stats['inserted']++;
            }
        }
    });

    $this->info('Import IKK selesai.');
    foreach ($stats as $k => $v) {
        $this->line("- {$k}: {$v}");
    }

    if (!empty($unmapped)) {
        $this->warn('Contoh IKK yang belum terhubung OPD (maks 20 baris):');
        foreach (array_slice($unmapped, 0, 20) as $item) {
            $this->line('#' . $item['row'] . ' | ' . $item['urusan_2'] . ' | ' . $item['indikator']);
        }
    }
})->purpose('Import data IKK referensi pusat ke indikator + mapping OPD + statistik keterhubungan');

Artisan::command('sync:ikk-opd', function () {
    $normalize = static function (?string $value): string {
        $text = strtoupper(trim((string) $value));
        $text = preg_replace('/[^A-Z0-9\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return trim((string) $text);
    };

    $opdByName = Opd::withoutGlobalScopes()
        ->where('is_active', true)
        ->get(['id', 'nama'])
        ->keyBy(fn ($opd) => $normalize((string) $opd->nama));

    $resolveOpdName = static function (?string $urusan2) use ($normalize): ?string {
        $u = $normalize((string) $urusan2);
        if ($u === '') return null;

        if (str_contains($u, 'KECAMATAN LONG APARAI')) return 'Kecamatan Long Apari';
        if (str_contains($u, 'KECAMATAN LONG PAHANGAI')) return 'Kecamatan Long Pahangai';
        if (str_contains($u, 'KECAMATAN LONG BAGUN')) return 'Kecamatan Long Bagun';
        if (str_contains($u, 'KECAMATAN LONG HUBUNG')) return 'Kecamatan Long Hubung';
        if (str_contains($u, 'KECAMATAN LONG LAHAM') || str_contains($u, 'KECAMATAN LAHAM')) return 'Kecamatan Laham';

        if (str_contains($u, 'SEKRETARIAT DPRD')) return 'Sekretariat DPRD';
        if (str_contains($u, 'SEKRETARIAT DAERAH')) return 'Sekretariat Daerah';
        if (str_contains($u, 'PERENCANAAN') || str_contains($u, 'PENELITIAN') || str_contains($u, 'PENGEMBANGAN')) return 'Badan Perencanaan Pembangunan, Penelitian dan Pengembangan Daerah';
        if (str_contains($u, 'KEUANGAN')) return 'Badan Pengelola Keuangan dan Aset Daerah';
        if (str_contains($u, 'KEPEGAWAIAN') || str_contains($u, 'PENDIDIKAN DAN PELATIHAN')) return 'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia';
        if (str_contains($u, 'PERBATASAN')) return 'Badan Pengelola Perbatasan Daerah';
        if (str_contains($u, 'INSPEKTORA') || str_contains($u, 'INSPEKTORAT')) return 'Inspektorat';
        if (str_contains($u, 'KESATUAN BANGSA') || str_contains($u, 'POLITIK')) return 'Badan Kesatuan Bangsa dan Politik';

        if (str_contains($u, 'PENDIDIKAN') || str_contains($u, 'KEBUDAYAAN') || str_contains($u, 'PERPUSTAKAAN') || str_contains($u, 'KEARSIPAN')) return 'Dinas Pendidikan dan Kebudayaan';
        if (str_contains($u, 'KESEHATAN') || str_contains($u, 'KELUARGA BERENCANA') || str_contains($u, 'PENGENDALIAN PENDUDUK') || str_contains($u, 'WAJIB PELAYANAN DASAR')) return 'Dinas Kesehatan, Pengendalian Penduduk dan KB';
        if (str_contains($u, 'PEKERJAAN UMUM') || str_contains($u, 'PENATAAN RUANG') || str_contains($u, 'PERUMAHAN') || str_contains($u, 'KAWASAN PEMUKIMAN')) return 'Dinas Pekerjaan Umum dan Penataan Ruang, Perumahan dan Kawasan Pemukiman';
        if (str_contains($u, 'KETENTERAMAN') || str_contains($u, 'KETERTIBAN')) return 'Satuan Polisi Pamong Praja';
        if (str_contains($u, 'BENCANA')) return 'Badan Penanggulangan Bencana Daerah';
        if (str_contains($u, 'SOSIAL') || str_contains($u, 'PEREMPUAN') || str_contains($u, 'ANAK')) return 'Dinas Sosial, Pemberdayaan Perempuan Perlindungan Anak';
        if (str_contains($u, 'PANGAN') || str_contains($u, 'PERTANIAN') || str_contains($u, 'KELAUTAN') || str_contains($u, 'PERIKANAN')) return 'Dinas Ketahanan Pangan dan Pertanian';
        if (str_contains($u, 'LINGKUNGAN HIDUP')) return 'Dinas Lingkungan Hidup';
        if (str_contains($u, 'KEPENDUDUKAN') || str_contains($u, 'PENCATATAN SIPIL')) return 'Dinas Kependudukan dan Pencatatan Sipil';
        if (str_contains($u, 'PEMBERDAYAAN MASYARAKAT') || str_contains($u, 'DESA') || str_contains($u, 'KAMPUNG')) return 'Dinas Pemberdayaan Masyarakat dan Pemerintahan Kampung';
        if (str_contains($u, 'PERHUBUNGAN')) return 'Dinas Perhubungan';
        if (str_contains($u, 'KOMUNIKASI') || str_contains($u, 'INFORMATIKA') || str_contains($u, 'STATISTIK') || str_contains($u, 'PERSANDIAN')) return 'Dinas Komunikasi dan Informatika, Statistik, dan Persandian';
        if (str_contains($u, 'PENANAMAN MODAL')) return 'Dinas Penanaman Modal dan Pelayanan Perijinan Terpadu';
        if (str_contains($u, 'PARIWISATA') || str_contains($u, 'KEPEMUDAAN') || str_contains($u, 'OLAHRAGA')) return 'Dinas Pariwisata, Pemuda dan Olahraga';
        if (str_contains($u, 'KOPERASI') || str_contains($u, 'USAHA KECIL') || str_contains($u, 'MENENGAH') || str_contains($u, 'PERDAGANGAN') || str_contains($u, 'PERINDUSTRIAN')) return 'Bagian Perekonomian dan Sumber Daya Alam';

        return null;
    };

    $stats = [
        'total_ikk' => 0,
        'mapped_by_urusan2' => 0,
        'unmapped_by_urusan2' => 0,
        'updated_opd_id' => 0,
        'urusan2_kosong' => 0,
    ];

    DB::transaction(function () use ($opdByName, $normalize, $resolveOpdName, &$stats) {
        $rows = Indikator::withoutGlobalScopes()
            ->where('jenis_indikator', 'IKK')
            ->get(['id', 'opd_id', 'keterangan']);

        foreach ($rows as $row) {
            $stats['total_ikk']++;
            $meta = json_decode((string) ($row->keterangan ?? ''), true);
            $urusan2 = is_array($meta) ? trim((string) ($meta['urusan_2'] ?? '')) : '';

            if ($urusan2 === '') {
                $stats['urusan2_kosong']++;
                $stats['unmapped_by_urusan2']++;
                continue;
            }

            $opdName = $resolveOpdName($urusan2);
            $opd = $opdName ? ($opdByName->get($normalize($opdName)) ?? null) : null;

            if (!$opd) {
                $stats['unmapped_by_urusan2']++;
                continue;
            }

            $stats['mapped_by_urusan2']++;

            if ((int) ($row->opd_id ?? 0) !== (int) $opd->id) {
                $row->opd_id = $opd->id;
                $row->save();
                $stats['updated_opd_id']++;
            }
        }
    });

    $this->info('Sinkronisasi OPD untuk IKK selesai.');
    foreach ($stats as $k => $v) {
        $this->line("- {$k}: {$v}");
    }
})->purpose('Sinkronkan opd_id indikator IKK berdasarkan kecocokan urusan_2');

Artisan::command('backfill:ikk-urusan', function () {
    $jsonPath = base_path('referensi/pusat/ikk.json');
    if (!is_file($jsonPath)) {
        $this->error('File tidak ditemukan: referensi/pusat/ikk.json');
        return;
    }

    $items = json_decode(file_get_contents($jsonPath), true) ?? [];
    if (!is_array($items)) {
        $this->error('Format JSON ikk.json tidak valid.');
        return;
    }

    $normalize = static function (?string $value): string {
        $text = strtoupper(trim((string) $value));
        $text = preg_replace('/[^A-Z0-9\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return trim((string) $text);
    };

    $map = [];
    foreach ($items as $row) {
        $indikator = $normalize((string) ($row['indikator'] ?? ''));
        if ($indikator === '') continue;

        if (!isset($map[$indikator])) {
            $map[$indikator] = [
                'urusan_1' => (string) ($row['urusan_1'] ?? ''),
                'urusan_2' => (string) ($row['urusan_2'] ?? ''),
                'sumber' => 'referensi/pusat/ikk.json',
            ];
        }
    }

    $stats = [
        'total_ikk' => 0,
        'with_empty_urusan2' => 0,
        'backfilled' => 0,
        'not_found_in_reference' => 0,
    ];

    DB::transaction(function () use (&$stats, $map, $normalize) {
        $rows = Indikator::withoutGlobalScopes()
            ->where('jenis_indikator', 'IKK')
            ->get(['id', 'uraian', 'keterangan']);

        foreach ($rows as $row) {
            $stats['total_ikk']++;
            $meta = json_decode((string) ($row->keterangan ?? ''), true);
            $urusan2 = is_array($meta) ? trim((string) ($meta['urusan_2'] ?? '')) : '';

            if ($urusan2 !== '') {
                continue;
            }

            $stats['with_empty_urusan2']++;
            $key = $normalize((string) ($row->uraian ?? ''));
            $fromRef = $map[$key] ?? null;

            if (!$fromRef) {
                $stats['not_found_in_reference']++;
                continue;
            }

            $existingMeta = is_array($meta) ? $meta : [];
            $newMeta = array_merge($existingMeta, [
                'urusan_1' => $fromRef['urusan_1'],
                'urusan_2' => $fromRef['urusan_2'],
                'sumber' => $fromRef['sumber'],
            ]);

            $row->keterangan = json_encode($newMeta, JSON_UNESCAPED_UNICODE);
            $row->save();
            $stats['backfilled']++;
        }
    });

    $this->info('Backfill metadata urusan IKK selesai.');
    foreach ($stats as $k => $v) {
        $this->line("- {$k}: {$v}");
    }
})->purpose('Isi urusan_1 dan urusan_2 yang kosong pada IKK dari referensi pusat berdasarkan nama indikator');
