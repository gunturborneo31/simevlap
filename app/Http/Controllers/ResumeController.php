<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\BidangUrusan;
use App\Models\Dokumen;
use App\Models\Opd;
use App\Models\Program;
use App\Models\Iku;
use App\Models\Realisasi;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ResumeController extends Controller
{
    public function index(Request $request): Response
    {
        $currentView = $request->string('view')->toString();
        $currentTable = $request->string('table')->toString();
        $filterBasis = $this->sanitizeFilterBasis($request->string('basis')->toString());
        $availableYears = $this->getAvailableYears();
        $selectedYear = $this->resolveSelectedYear($request->query('year'), $availableYears);
        $selectedTw = $request->query('tw') !== null ? (int) $request->query('tw') : null;
        $selectedBidang = $request->query('bidang') !== null ? (string) $request->query('bidang') : null;

        $bidangUrusans = BidangUrusan::query()
            ->select(['id', 'kode', 'nama'])
            ->orderBy('nama')
            ->get()
            ->map(fn (BidangUrusan $b) => ['id' => $b->id, 'kode' => $b->kode, 'nama' => $b->nama])
            ->values()
            ->all();
        $selectedTw = $request->query('tw') !== null ? (int) $request->query('tw') : null;

        if ($currentView !== '' && $currentTable !== '') {
            // Preserve requested table name. Do not remap `berdasarkan-sasaran` to `berdasarkan-bidang-urusan`.
            // (Annotations migration still runs only when opening the explicit bidang-urusan table below.)

            // When opening the bidang-urusan table, move any existing annotations from dasar sasaran to bidang-urusan
            if ($currentView === 'rekap-permasalahan' && $currentTable === 'berdasarkan-bidang-urusan') {
                try {
                    DB::transaction(function () {
                        DB::table('resume_program_annotations')
                            ->where('view', 'rekap-permasalahan')
                            ->where('table_name', 'berdasarkan-sasaran')
                            ->update(['table_name' => 'berdasarkan-bidang-urusan']);
                    });
                } catch (\Throwable $e) {
                    Log::warning('rekap-permasalahan.migrate_failed', ['error' => $e->getMessage()]);
                }
            }
            $tableData = null;
            $tableMetricType = $this->resolveTableMetricType($currentView, $currentTable);

                if (in_array($currentTable, ['tabel-1', 'tabel-2', 'tabel-3', 'tabel-4', 'tabel-5', 'tabel-6', 'tabel-8', 'tabel-9', 'tabel-10'], true) && $currentView === 'konsistensi-rpjmd-rkpd') {
                $tableData = $this->getKonsistensiRpjmdRkpd($filterBasis, $selectedYear, $tableMetricType);
            }

                if (in_array($currentTable, ['tabel-1', 'tabel-2', 'tabel-3', 'tabel-4', 'tabel-5', 'tabel-6', 'tabel-7','tabel-8','tabel-9','tabel-10'], true) && $currentView === 'konsistensi-rkpd-apbd') {
                $tableData = $this->getKonsistensiRkpdApbd($filterBasis, $selectedYear, $tableMetricType);
            }

            if ($currentView === 'dokumen' && $currentTable === 'monitoring') {
                $tableData = $this->getDokumenMonitoring();
            }

            if ($currentView === 'realisasi' && $currentTable === 'iku') {
                $tableData = $this->getRealisasiIku();
            }

            // Hasil Pelaksanaan RKPD - tabel-1: build rows from Tujuan -> Sasaran -> Indikator (indikatorables)
            if ($currentView === 'hasil-pelaksanaan-rkpd' && $currentTable === 'tabel-1') {
                $rows = [];
                $tujuans = \App\Models\Tujuan::with('sasaran')->orderBy('id')->get();
                $counter = 0;

                foreach ($tujuans as $tujuan) {
                    // Tujuan-level indikatorables for selected year
                    $tujuanIndikators = DB::table('indikatorables as ia')
                        ->join('indikator as i', 'i.id', '=', 'ia.indikator_id')
                        ->where('ia.indicatorable_type', \App\Models\Tujuan::class)
                        ->where('ia.indicatorable_id', $tujuan->id)
                        ->where('ia.tahun', $selectedYear)
                        ->select('i.uraian as indikator', 'i.satuan', 'ia.target', 'ia.realisasi')
                        ->get();

                    foreach ($tujuanIndikators as $indik) {
                        $counter++;
                        $rows[] = [
                            'no' => $counter,
                            'tujuan' => $tujuan->uraian,
                            'sasaran' => null,
                            'indikator' => $indik->indikator,
                            'satuan' => $indik->satuan,
                            'target_rpjmd' => $indik->target,
                            'target_rkpd' => $indik->target,
                            'capaian_tahun' => $indik->realisasi,
                        ];
                    }

                    // Sasaran under tujuan
                    foreach ($tujuan->sasaran as $sasaran) {
                        $sasaranIndikators = DB::table('indikatorables as ia')
                            ->join('indikator as i', 'i.id', '=', 'ia.indikator_id')
                            ->where('ia.indicatorable_type', \App\Models\Sasaran::class)
                            ->where('ia.indicatorable_id', $sasaran->id)
                            ->where('ia.tahun', $selectedYear)
                            ->select('i.uraian as indikator', 'i.satuan', 'ia.target', 'ia.realisasi')
                            ->get();

                        foreach ($sasaranIndikators as $indik) {
                            $counter++;
                            $rows[] = [
                                'no' => $counter,
                                'tujuan' => $tujuan->uraian,
                                'sasaran' => $sasaran->uraian,
                                'indikator' => $indik->indikator,
                                'satuan' => $indik->satuan,
                                'target_rpjmd' => $indik->target,
                                'target_rkpd' => $indik->target,
                                'capaian_tahun' => $indik->realisasi,
                            ];
                        }
                    }
                }

                // compact tujuan: compute rowspan and mark first occurrence so frontend can merge cells
                if (! empty($rows)) {
                    $counts = [];
                    foreach ($rows as $r) {
                        $k = $r['tujuan'] ?? '_';
                        if (! isset($counts[$k])) $counts[$k] = 0;
                        $counts[$k]++;
                    }

                    $seen = [];
                    foreach ($rows as $i => $r) {
                        $k = $r['tujuan'] ?? '_';
                        if (! isset($seen[$k])) {
                            $rows[$i]['tujuan_rowspan'] = $counts[$k];
                            $rows[$i]['tujuan_first'] = true;
                            $seen[$k] = true;
                        } else {
                            $rows[$i]['tujuan_rowspan'] = 0;
                            $rows[$i]['tujuan_first'] = false;
                        }
                    }
                }

                $tableData = $rows;
            }

            // Hasil Pelaksanaan RKPD - tabel-2: Program Aksi Kepala Daerah (top 10)
            if ($currentView === 'hasil-pelaksanaan-rkpd' && $currentTable === 'tabel-2') {
                $rows = [];
                $counter = 0;

                $programs = Program::query()
                    ->where('jenis_program', 'program-aksi')
                    ->orderByDesc('is_prioritas')
                    ->orderBy('id')
                    ->limit(10)
                    ->get();

                foreach ($programs as $program) {
                    // collect indikatorables for this program and selected year
                    $indicators = DB::table('indikatorables as ia')
                        ->join('indikator as i', 'i.id', '=', 'ia.indikator_id')
                        ->where('ia.indicatorable_type', Program::class)
                        ->where('ia.indicatorable_id', $program->id)
                        ->where('ia.tahun', $selectedYear)
                        ->select('i.id as indikator_id', 'i.uraian as indikator', 'i.satuan', 'ia.target', 'ia.realisasi')
                        ->get();

                    if ($indicators->isEmpty()) {
                        $counter++;
                        $rows[] = [
                            'no' => $counter,
                            'program' => $program->nama_rincian ?? $program->kode_rek,
                            'capaian_fisik' => optional($program->realisasi()->where('tahun', $selectedYear)->latest()->first())->realisasi_fisik,
                            'capaian_keuangan' => optional($program->realisasi()->where('tahun', $selectedYear)->latest()->first())->realisasi_keuangan,
                            'prioritas' => $program->is_prioritas ? 'Ya' : '-',
                            'indikator' => null,
                            'target' => null,
                            'satuan' => null,
                            'indikator_capaian' => null,
                        ];
                        continue;
                    }

                    foreach ($indicators as $indik) {
                        // find supporting RPJMD programs that share the same indikator
                        $supporting = DB::table('indikatorables as ia2')
                            ->join('program as p', 'p.id', '=', 'ia2.indicatorable_id')
                            ->where('ia2.indikator_id', $indik->indikator_id)
                            ->where('ia2.indicatorable_type', Program::class)
                            ->where(function ($q) {
                                $q->where('p.is_prioritas', 1)
                                  ->orWhereIn('p.jenis_program', ['program', 'unggulan']);
                            })
                            ->where('ia2.tahun', $selectedYear)
                            ->select('p.id', 'p.nama_rincian', 'ia2.target as rpjmd_target', 'ia2.realisasi as rpjmd_realisasi')
                            ->distinct()
                            ->get();

                        $supportingNames = $supporting->pluck('nama_rincian')->filter()->unique()->values()->all();
                        $supportingNamesStr = empty($supportingNames) ? null : implode(', ', $supportingNames);

                        $rpjmd_indikator = $indik->indikator;
                        $rpjmd_target = $supporting->first()->rpjmd_target ?? null;
                        $rpjmd_satuan = $indik->satuan ?? null;
                        $rpjmd_capaian = $supporting->first()->rpjmd_realisasi ?? null;
                        $counter++;
                        $rows[] = [
                            'no' => $counter,
                            'program' => $program->nama_rincian ?? $program->kode_rek,
                            'capaian_fisik' => $indik->realisasi ?? optional($program->realisasi()->where('tahun', $selectedYear)->latest()->first())->realisasi_fisik,
                            'capaian_keuangan' => optional($program->realisasi()->where('tahun', $selectedYear)->latest()->first())->realisasi_keuangan,
                            'supporting_programs' => $supportingNamesStr,
                            'rpjmd_indikator' => $rpjmd_indikator,
                            'rpjmd_target' => $rpjmd_target,
                            'rpjmd_satuan' => $rpjmd_satuan,
                            'rpjmd_capaian' => $rpjmd_capaian,
                            'indikator' => $indik->indikator,
                            'target' => $indik->target,
                            'satuan' => $indik->satuan,
                            'indikator_capaian' => $indik->realisasi,
                            'is_program_aksi' => true,
                        ];
                    }
                }

                // compute rowspan per program so frontend can merge program name cell
                if (! empty($rows)) {
                    $counts = [];
                    foreach ($rows as $r) {
                        $k = $r['program'] ?? '_';
                        if (! isset($counts[$k])) $counts[$k] = 0;
                        $counts[$k]++;
                    }

                    $seen = [];
                    foreach ($rows as $i => $r) {
                        $k = $r['program'] ?? '_';
                        if (! isset($seen[$k])) {
                            $rows[$i]['program_rowspan'] = $counts[$k];
                            $rows[$i]['program_first'] = true;
                            $seen[$k] = true;
                        } else {
                            $rows[$i]['program_rowspan'] = 0;
                            $rows[$i]['program_first'] = false;
                        }
                    }
                }

                $tableData = $rows;
            }

            // Ensure fallback: attach indikator arrays to rkpd/dpa program items
            if ($tableData !== null) {
                $tableData = $this->attachFallbackIndikatorsToTableData($tableData, $selectedYear);
            }

            // Rekap Permasalahan - berdasarkan sasaran
            if ($currentView === 'rekap-permasalahan' && $currentTable === 'berdasarkan-sasaran') {
                $tableData = $this->getRekapPermasalahanBySasaran($filterBasis, $selectedTw, $selectedBidang, $selectedYear);

                // Fallback: if no rows found via indikator relations, try reading saved annotations
                if (empty($tableData)) {
                    try {
                        $fallback = $this->getRekapPermasalahanByBidangUrusan($filterBasis, $selectedTw, $selectedBidang, $selectedYear);
                        if (!empty($fallback)) {
                            $tableData = $fallback;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('rekap-permasalahan.fallback_failed', ['error' => $e->getMessage()]);
                    }
                }
            }

            // Rekap Permasalahan - berdasarkan bidang urusan (annotations moved here)
            if ($currentView === 'rekap-permasalahan' && $currentTable === 'berdasarkan-bidang-urusan') {
                $tableData = $this->getRekapPermasalahanByBidangUrusan($filterBasis, $selectedTw, $selectedBidang, $selectedYear);
            }

            // If viewing hasil-pelaksanaan-rkpd tabel-2, ensure Program Aksi rows appear first
            if ($tableData !== null && $currentView === 'hasil-pelaksanaan-rkpd' && $currentTable === 'tabel-2' && is_array($tableData)) {
                usort($tableData, function ($a, $b) {
                    $pa = $a['is_program_aksi'] ?? false;
                    $pb = $b['is_program_aksi'] ?? false;
                    if ($pa === $pb) return 0;
                    return $pa ? -1 : 1;
                });
            }

            // temporary debug: log a sample of the tableData so we can inspect shape
            try {
                if ($tableData !== null) {
                    if (is_object($tableData) && method_exists($tableData, 'first')) {
                        $sample = $tableData->first();
                    } elseif (is_array($tableData)) {
                        $sample = $tableData[0] ?? null;
                    } else {
                        $sample = $tableData;
                    }

                    $sampleForLog = null;
                    if (is_object($sample)) {
                        $sampleForLog = json_decode(json_encode($sample), true);
                    } else {
                        $sampleForLog = $sample;
                    }

                    Log::debug('resume.tableData.sample', ['sample' => $sampleForLog]);
                } else {
                    Log::debug('resume.tableData.sample', ['sample' => null]);
                }
            } catch (\Throwable $e) {
                Log::warning('resume.tableData.sample.failed', ['error' => $e->getMessage()]);
            }

            $inertiaExtras = [];
            if ($currentView === 'rekap-permasalahan') {
                try {
                    $inertiaExtras['debugCounts'] = [
                        'sasaran_count' => \App\Models\Sasaran::count(),
                        'program_count' => \App\Models\Program::count(),
                        'indikatorable_sasaran_count' => DB::table('indikatorables')->where('indicatorable_type', \App\Models\Sasaran::class)->when($selectedYear, fn($q) => $q->where('tahun', $selectedYear))->count(),
                        'indikatorable_program_count' => DB::table('indikatorables')->where('indicatorable_type', \App\Models\Program::class)->when($selectedYear, fn($q) => $q->where('tahun', $selectedYear))->count(),
                        'annotations_count' => DB::table('resume_program_annotations')->where('view','rekap-permasalahan')->whereIn('table_name', ['berdasarkan-sasaran','berdasarkan-bidang-urusan'])->when($selectedYear, fn($q) => $q->where('tahun', $selectedYear))->count(),
                    ];
                } catch (\Throwable $e) {
                    Log::warning('rekap-permasalahan.debugcounts_failed', ['error' => $e->getMessage()]);
                }
            }

            return Inertia::render('Resume/TableView', array_merge([
                'currentView' => $currentView,
                'currentTable' => $currentTable,
                'viewTitle' => $this->getViewTitle($currentView),
                'filterBasis' => $filterBasis,
                'selectedYear' => $selectedYear,
                'selectedTw' => $selectedTw,
                'selectedBidang' => $selectedBidang,
                'bidangUrusans' => $bidangUrusans,
                'availableYears' => $availableYears,
                'tableMetricType' => $tableMetricType,
                'tableData' => $tableData,
            ], $inertiaExtras));
        }

        return Inertia::render('Resume/Index', [
            'currentView' => $currentView,
            'currentTable' => $currentTable,
        ]);
    }

    protected function getRekapPermasalahanBySasaran(string $basis = 'perangkat-daerah', ?int $tw = null, ?string $bidang = null, ?int $tahun = null): array
    {
        $rows = [];
        $counter = 0;

        $sasaranQuery = \App\Models\Sasaran::query()->orderBy('id');
        if ($bidang) {
            // try to filter by bidang code or name
            $sasaranQuery->where('uraian', 'like', "%{$bidang}%");
        }

        $sasarans = $sasaranQuery->get();

        foreach ($sasarans as $sasaran) {
            // ensure we always produce at least one row per sasaran
            $addedForThisSasaran = false;

            // collect indikator ids linked to this sasaran for the selected year
            $indikatorIds = DB::table('indikatorables')
                ->where('indicatorable_type', \App\Models\Sasaran::class)
                ->where('indicatorable_id', $sasaran->id)
                ->when($tahun, fn ($q) => $q->where('tahun', $tahun))
                ->pluck('indikator_id')
                ->unique()
                ->values()
                ->all();

            if (empty($indikatorIds)) {
                // No indikatorables linked to this sasaran for the selected year.
                // Try to find any saved annotations that target this sasaran (table `resume_program_annotations`).
                $annRows = \App\Models\ResumeProgramAnnotation::query()
                    ->where('view', 'rekap-permasalahan')
                    ->where('table_name', 'berdasarkan-sasaran')
                    ->when($tahun, fn($q) => $q->where('tahun', $tahun))
                    ->where('entitas', $sasaran->uraian)
                    ->get();

                if ($annRows->isEmpty()) {
                    // nothing to display for this sasaran
                    continue;
                }

                // build rows from annotations for this sasaran
                foreach ($annRows as $ann) {
                    $counter++;
                    $rows[] = [
                        'no' => $counter,
                        'sasaran' => $sasaran->uraian,
                        'program' => $ann->program_nama ?? $ann->program_kode,
                        'renstra' => '-','renja' => '-','dpa' => '-',
                        'faktor_penghambat' => $ann->faktor_penghambat,
                        'faktor_pendorong' => $ann->faktor_pendorong,
                        'faktor_tindak_lanjut' => $ann->faktor_tindak_lanjut,
                        'opd' => null,
                    ];

                    $addedForThisSasaran = true;
                }

                // move to next sasaran
                continue;
            }

            // find programs that share those indikator ids in the same year
            $programIds = DB::table('indikatorables')
                ->whereIn('indikator_id', $indikatorIds)
                ->where('indicatorable_type', \App\Models\Program::class)
                ->when($tahun, fn ($q) => $q->where('tahun', $tahun))
                ->pluck('indicatorable_id')
                ->unique()
                ->values()
                ->all();

            if (empty($programIds)) {
                // If no programs found via indikator linkage, fallback to annotations matching this sasaran
                $annRows = \App\Models\ResumeProgramAnnotation::query()
                    ->where('view', 'rekap-permasalahan')
                    ->where('table_name', 'berdasarkan-sasaran')
                    ->when($tahun, fn($q) => $q->where('tahun', $tahun))
                    ->where('entitas', $sasaran->uraian)
                    ->get();

                if ($annRows->isEmpty()) {
                    continue;
                }

                foreach ($annRows as $ann) {
                    $counter++;
                    $rows[] = [
                        'no' => $counter,
                        'sasaran' => $sasaran->uraian,
                        'program' => $ann->program_nama ?? $ann->program_kode,
                        'renstra' => '-','renja' => '-','dpa' => '-',
                        'faktor_penghambat' => $ann->faktor_penghambat,
                        'faktor_pendorong' => $ann->faktor_pendorong,
                        'faktor_tindak_lanjut' => $ann->faktor_tindak_lanjut,
                        'opd' => null,
                    ];

                    $addedForThisSasaran = true;
                }

                continue;
            }

            $programs = Program::query()->whereIn('id', $programIds)->with('opd')->orderBy('nama_rincian')->get();

            foreach ($programs as $program) {
                $counter++;

                // try to find any saved annotations for this program (permasalahan)
                $annotation = \App\Models\ResumeProgramAnnotation::query()
                    ->where('view', 'rekap-permasalahan')
                    ->where('table_name', 'berdasarkan-sasaran')
                    ->when($tahun, fn ($q) => $q->where('tahun', $tahun))
                    ->where(function ($q) use ($program) {
                        $q->where('program_kode', $program->kode_rek)
                        ->orWhere('program_nama', $program->nama_rincian);
                    })
                    ->first();

                $rows[] = [
                    'no' => $counter,
                    'sasaran' => $sasaran->uraian,
                    'program' => $program->nama_rincian ?? $program->kode_rek,
                    'renstra' => '-',
                    'renja' => '-',
                    'dpa' => '-',
                    'faktor_penghambat' => $annotation->faktor_penghambat ?? null,
                    'faktor_pendorong' => $annotation->faktor_pendorong ?? null,
                    'faktor_tindak_lanjut' => $annotation->faktor_tindak_lanjut ?? null,
                    'opd' => optional($program->opd)->nama ?? null,
                ];

                $addedForThisSasaran = true;
            }
            
            // If nothing was added for this sasaran, add a placeholder empty row so sasaran appears in list
            if (! $addedForThisSasaran) {
                $counter++;
                $rows[] = [
                    'no' => $counter,
                    'sasaran' => $sasaran->uraian,
                    'program' => null,
                    'renstra' => '-',
                    'renja' => '-',
                    'dpa' => '-',
                    'faktor_penghambat' => null,
                    'faktor_pendorong' => null,
                    'faktor_tindak_lanjut' => null,
                    'opd' => null,
                ];
            }
        }

        return $rows;
    }

    protected function getRekapPermasalahanByBidangUrusan(string $basis = 'perangkat-daerah', ?int $tw = null, ?string $bidang = null, ?int $tahun = null): array
    {
        $rows = [];
        $counter = 0;

        $annotations = \App\Models\ResumeProgramAnnotation::query()
            ->where('view', 'rekap-permasalahan')
            ->where('table_name', 'berdasarkan-bidang-urusan')
            ->when($tahun, fn($q) => $q->where('tahun', $tahun))
            ->get();

        foreach ($annotations as $ann) {
            $counter++;
            // Resolve entitas: prefer to fill both `bidang` (BidangUrusan.nama) and `opd` (Opd.nama)
            $entitasRaw = $ann->entitas ?? $ann->basis ?? null;
            $bidangLabel = null;
            $opdLabel = null;
            if ($entitasRaw) {
                try {
                    // try resolving to an OPD first (id, kode, or nama)
                    $opd = null;
                    if (is_numeric($entitasRaw)) {
                        $opd = Opd::find((int) $entitasRaw);
                    }

                    if (empty($opd)) {
                        $opd = Opd::query()
                            ->where('kode', $entitasRaw)
                            ->orWhere('nama', $entitasRaw)
                            ->first();
                    }

                    if (! empty($opd)) {
                        $opdLabel = $opd->nama;
                    }

                    // also try resolving to BidangUrusan (id, kode, or nama)
                    if (is_numeric($entitasRaw)) {
                        $b = BidangUrusan::find((int) $entitasRaw);
                    } else {
                        $b = BidangUrusan::query()
                            ->where('kode', $entitasRaw)
                            ->orWhere('nama', $entitasRaw)
                            ->first();
                    }

                    if (! empty($b)) {
                        $bidangLabel = $b->nama;
                    }
                } catch (\Throwable $e) {
                    Log::debug('rekap-permasalahan.bidang_resolution_failed', ['entitas' => $entitasRaw, 'error' => $e->getMessage()]);
                }
            }

            $rows[] = [
                'no' => $counter,
                'bidang' => $bidangLabel ?? $entitasRaw,
                'program' => $ann->program_nama ?? $ann->program_kode,
                'renstra' => '-',
                'renja' => '-',
                'dpa' => '-',
                'faktor_penghambat' => $ann->faktor_penghambat,
                'faktor_pendorong' => $ann->faktor_pendorong,
                'faktor_tindak_lanjut' => $ann->faktor_tindak_lanjut,
                'opd' => $opdLabel,
            ];
        }

        return $rows;
    }

    public function export(Request $request)
    {
        $currentView = $request->string('view')->toString();
        $currentTable = $request->string('table')->toString();

        $allowedTables = ['tabel-1', 'tabel-2', 'tabel-3', 'tabel-4', 'tabel-5', 'tabel-6', 'tabel-7', 'tabel-10', 'iku'];
        $allowedForViews = ['konsistensi-rpjmd-rkpd', 'konsistensi-rkpd-apbd'];
        // Allow export for IKU realisasi as well
        if (!((in_array($currentView, $allowedForViews, true) && in_array($currentTable, $allowedTables, true)) || ($currentView === 'realisasi' && $currentTable === 'iku'))) {
            abort(404);
        }

        $filterBasis = $this->sanitizeFilterBasis($request->string('basis')->toString());
        $availableYears = $this->getAvailableYears();
        $selectedYear = $this->resolveSelectedYear($request->query('year'), $availableYears);
        $tableMetricType = $this->resolveTableMetricType($currentView, $currentTable);
        if ($currentView === 'konsistensi-rpjmd-rkpd') {
            $tableData = $this->getKonsistensiRpjmdRkpd($filterBasis, $selectedYear, $tableMetricType);
        } else {
            $tableData = $this->getKonsistensiRkpdApbd($filterBasis, $selectedYear, $tableMetricType);
        }

        $basePayload = [
            'viewTitle' => $this->getViewTitle($currentView),
            'tableLabel' => $this->formatCurrentTableLabel($currentTable),
            'entityHeaderLabel' => $filterBasis === 'perangkat-daerah' ? 'Perangkat Daerah' : 'Bidang Urusan',
            'metricLabel' => $tableMetricType === 'indikator' ? 'Indikator Program' : ($tableMetricType === 'kegiatan' ? 'Kegiatan' : 'Program'),
            'selectedYear' => $selectedYear,
            'selectedTw' => $request->query('tw') !== null ? (int) $request->query('tw') : null,
            'selectedBidang' => $request->query('bidang') !== null ? (string) $request->query('bidang') : null,
        ];

        if ($currentView === 'konsistensi-rpjmd-rkpd') {
            $template = 'exports.resume_tabel_1';
            $payload = $basePayload + [
                'rows' => $this->buildTabel1ExportRows($tableData, $tableMetricType),
            ];
        } else {
            if ($tableMetricType === 'indikator') {
                $template = 'exports.resume_tabel_rkpd_apbd_indikator';
                $payload = $basePayload + [
                    'groups' => $this->buildTabel1ExportRowsRkpdApbd($tableData, $tableMetricType),
                ];
            } else {
                $template = 'exports.resume_tabel_rkpd_apbd';
                $payload = $basePayload + [
                    'rows' => $this->buildTabel1ExportRowsRkpdApbd($tableData, $tableMetricType),
                ];
            }
        }

        // No special handling for tabel-3: reuse table-1/tabel-2 RKPD-APBD export format

        if (in_array($currentTable, ['tabel-4', 'tabel-5', 'tabel-6', 'tabel-7'], true)) {
            $template = 'exports.resume_tabel_4';
            $payload = $basePayload + [
                'groups' => $this->buildTabel4ExportGroups($tableData, $tableMetricType),
            ];
        }

        if ($currentView === 'realisasi' && $currentTable === 'iku') {
            $template = 'exports.realisasi_iku';
            $payload = $basePayload + [
                'rows' => is_array($tableData) ? $tableData : $tableData->toArray(),
            ];
        }

        $format = strtolower((string) $request->query('format', 'pdf'));
        $timestamp = now()->format('Ymd_His');
        $safeTable = str_replace(' ', '_', strtolower($this->formatCurrentTableLabel($currentTable)));

        if ($format === 'excel') {
            $html = view($template, $payload + ['exportFormat' => 'excel'])->render();

            return response($html, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="resume_'.$safeTable.'_'.$timestamp.'.xls"',
            ]);
        }

        // Tabel 3 bisa sangat besar saat dirender ke PDF; naikkan limit hanya untuk proses ini.
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $pdf = Pdf::loadView($template, $payload + ['exportFormat' => 'pdf'])
            ->setPaper('a4', 'landscape');

        return $pdf->download('resume_'.$safeTable.'_'.$timestamp.'.pdf');
    }

    public function viewDokumen(Dokumen $dokumen)
    {
        if (!Storage::disk('public')->exists($dokumen->file_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($dokumen->file_path));
    }

    private function getViewTitle(string $view): string
    {
        return match ($view) {
            'konsistensi-rpjmd-rkpd' => 'Konsistensi RPJMD - RKPD',
            'konsistensi-rkpd-apbd' => 'Konsistensi RKPD - APBD',
            'hasil-pelaksanaan-rkpd' => 'Hasil Pelaksanaan RKPD',
            'rekap-permasalahan' => 'Rekap Permasalahan',
            'realisasi' => 'Realisasi',
            'dokumen' => 'Dokumen',
            'kertas-kerja' => 'Kertas Kerja',
            default => 'Resume',
        };
    }

    private function getDokumenMonitoring(): array
    {
        $years = [2026, 2027, 2028, 2029, 2030];

        $dokumenRows = Dokumen::withoutGlobalScopes()
            ->select(['id', 'opd_id', 'document_type', 'tahun', 'judul', 'file_path', 'created_at'])
            ->whereIn('document_type', ['renstra', 'renja', 'dpa'])
            ->orderByDesc('created_at')
            ->get();

        $grouped = [];
        foreach ($dokumenRows as $dokumen) {
            $opdId = (int) $dokumen->opd_id;
            $documentType = (string) $dokumen->document_type;
            $tahun = (int) $dokumen->tahun;

            if ($documentType === 'renstra') {
                $grouped[$opdId]['renstra'] ??= $this->transformDokumenCell($dokumen, '2026 - 2030');
                continue;
            }

            if (!in_array($tahun, $years, true)) {
                continue;
            }

            $grouped[$opdId]['years'][$tahun][$documentType] ??= $this->transformDokumenCell($dokumen, (string) $tahun);
        }

        return Opd::query()
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'singkatan'])
            ->map(function (Opd $opd, int $index) use ($grouped, $years) {
                $data = $grouped[$opd->id] ?? [];

                $yearCells = [];
                foreach ($years as $year) {
                    $yearCells[$year] = [
                        'renja' => $data['years'][$year]['renja'] ?? $this->emptyDokumenCell(),
                        'dpa' => $data['years'][$year]['dpa'] ?? $this->emptyDokumenCell(),
                    ];
                }

                return [
                    'no' => $index + 1,
                    'opd' => $opd->singkatan ?: $opd->nama,
                    'renstra' => $data['renstra'] ?? $this->emptyDokumenCell(),
                    'years' => $yearCells,
                ];
            })
            ->values()
            ->all();
    }

    private function transformDokumenCell(Dokumen $dokumen, string $label): array
    {
        return [
            'has_file' => true,
            'label' => $label,
            'judul' => $dokumen->judul,
            'view_url' => route('resume.dokumen.view', $dokumen),
        ];
    }

    private function emptyDokumenCell(): array
    {
        return [
            'has_file' => false,
            'label' => null,
            'judul' => null,
            'view_url' => null,
        ];
    }

    private function resolveTableMetricType(string $currentView, string $currentTable): string
    {
        if ($currentView === 'konsistensi-rpjmd-rkpd' && in_array($currentTable, ['tabel-2', 'tabel-3'], true)) {
            return 'indikator';
        }

        // For RKPD-APBD view, tabel-2 compares kegiatan instead of program
        if ($currentView === 'konsistensi-rkpd-apbd' && $currentTable === 'tabel-2') {
            return 'kegiatan';
        }

        // For RKPD-APBD, tabel-8 should use kegiatan data (same as tabel-2)
        if ($currentView === 'konsistensi-rkpd-apbd' && $currentTable === 'tabel-8') {
            return 'kegiatan';
        }

        // For RKPD-APBD, default to program for tabel-1 and tabel-4 (use program data)
        if ($currentView === 'konsistensi-rkpd-apbd' && in_array($currentTable, ['tabel-1', 'tabel-4'], true)) {
            return 'program';
        }

        // Use kegiatan for tabel-5 on RKPD-APBD
        if ($currentView === 'konsistensi-rkpd-apbd' && $currentTable === 'tabel-5') {
            return 'kegiatan';
        }

        // Use sub_kegiatan for tabel-6 on RKPD-APBD
        if ($currentView === 'konsistensi-rkpd-apbd' && $currentTable === 'tabel-6') {
            return 'sub_kegiatan';
        }

        if ($currentView === 'konsistensi-rkpd-apbd' && in_array($currentTable, ['tabel-3', 'tabel-9'], true)) {
            return 'sub_kegiatan';
        }

        return 'program';
    }

    private function buildTabel1ExportRows(Collection $tableData, string $metricType): array
    {
        return $tableData->map(function ($row) use ($metricType) {
            $rowData = is_array($row) ? $row : [];

            $rpjmdPrograms = $this->buildUniqueComparableItems((array) ($rowData['rpjmd_programs'] ?? []), $metricType);
            $renstraPrograms = $this->buildUniqueComparableItems((array) ($rowData['renstra_programs'] ?? []), $metricType);
            $rkpdPrograms = $this->buildUniqueComparableItems((array) ($rowData['rkpd_programs'] ?? []), $metricType);

            $sameRpjmdRenstra = $this->countSameComparableItems($rpjmdPrograms, $renstraPrograms, $metricType);
            $sameRpjmdRkpd = $this->countSameComparableItems($rpjmdPrograms, $rkpdPrograms, $metricType);
            $sameRenstraRkpd = $this->countSameComparableItems($renstraPrograms, $rkpdPrograms, $metricType);

            $totalRpjmd = count($rpjmdPrograms);
            $totalRenstra = count($renstraPrograms);
            $totalRkpd = count($rkpdPrograms);

            return [
                'no' => (int) ($rowData['no'] ?? 0),
                'entitas' => $this->formatResumeEntityLabel((string) ($rowData['entitas'] ?? '')),
                'rpjmd_total' => $totalRpjmd,
                'renstra_total' => $totalRenstra,
                'rkpd_total' => $totalRkpd,
                'same_rpjmd_renstra' => $sameRpjmdRenstra,
                'diff_rpjmd_renstra' => max($totalRpjmd - $sameRpjmdRenstra, 0),
                'same_rpjmd_rkpd' => $sameRpjmdRkpd,
                'diff_rpjmd_rkpd' => max($totalRpjmd - $sameRpjmdRkpd, 0),
                'same_renstra_rkpd' => $sameRenstraRkpd,
                'diff_renstra_rkpd' => max($totalRenstra - $sameRenstraRkpd, 0),
            ];
        })->values()->all();
    }

    private function buildUniqueComparableItems(array $items, string $metricType): array
    {
        $unique = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = $this->buildComparableKey($item, $metricType);
            if ($key === '' || $key === '|') {
                continue;
            }

            if (!isset($unique[$key])) {
                $unique[$key] = $item;
            }
        }

        return array_values($unique);
    }

    private function countSameComparableItems(array $leftItems, array $rightItems, string $metricType): int
    {
        $rightKeys = [];
        foreach ($rightItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $rightKeys[$this->buildComparableKey($item, $metricType)] = true;
        }

        $count = 0;
        foreach ($leftItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = $this->buildComparableKey($item, $metricType);
            if ($key !== '' && isset($rightKeys[$key])) {
                $count++;
            }
        }

        return $count;
    }

    private function buildComparableKey(array $item, string $metricType): string
    {
        if ($metricType === 'indikator') {
            $name = $this->normalizeComparableText((string) ($item['nama'] ?? ''));

            return preg_replace('/[^A-Z0-9]/', '', $name) ?? '';
        }

        $kode = $this->normalizeComparableText((string) ($item['kode'] ?? ''));
        $nama = $this->normalizeComparableText((string) ($item['nama'] ?? ''));

        return $kode.'|'.$nama;
    }

    private function normalizeComparableText(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return strtoupper($normalized);
    }

    private function formatResumeEntityLabel(string $value): string
    {
        $normalized = preg_replace('/^\s*URUSAN\s+PEMERINTAHAN\s+BIDANG\s+/i', '', $value) ?? $value;

        return trim($normalized);
    }

    /**
     * Resolve a better program/kegiatan name when the provided name is empty or a placeholder.
     * Attempts DB lookups (komponen_anggaran, program, kegiatan) and falls back to reference JSON files.
     */
    private function resolvePreferredProgramName(?int $opdId, string $kode, string $currentName, string $dokumen): string
    {
        $trimmed = trim((string) $currentName);
        if ($trimmed !== '' && stripos($trimmed, '(otomatis)') === false) {
            return $trimmed;
        }

        $kode = trim((string) $kode);
        if ($kode === '') return $currentName;

        try {
            // Prepare candidate codes: full kode and program-level kode (first 3 segments)
            $candidates = [$kode];
            $parts = array_values(array_filter(explode('.', $kode), fn($s) => $s !== ''));
            if (count($parts) >= 3) {
                $programKode = $parts[0].'.'.$parts[1].'.'.$parts[2];
                if ($programKode !== $kode) $candidates[] = $programKode;
            }

            // helper to recursively search JSON arrays for a matching kode_program/kode
            $findInJson = function ($json, $code) {
                if (!is_array($json)) return null;
                $stack = $json;
                while (!empty($stack)) {
                    $item = array_shift($stack);
                    if (is_array($item)) {
                        if ((isset($item['kode']) && trim((string)$item['kode']) === $code) || (isset($item['kode_program']) && trim((string)$item['kode_program']) === $code)) {
                            return $item['nama'] ?? $item['nama_komponen'] ?? $item['nama_program'] ?? null;
                        }
                        foreach ($item as $v) {
                            if (is_array($v)) $stack[] = $v;
                        }
                    }
                }
                return null;
            };

            foreach ($candidates as $candidate) {
                // komponen_anggaran with opd filter
                // Prefer komponen_anggaran rows that are program-level when available,
                // otherwise fall back to other komponen matches (e.g. sub_kegiatan).
                $komQuery = DB::table('komponen_anggaran')
                    ->when($opdId !== null, fn($q) => $q->where('opd_id', $opdId))
                    ->where(function ($q) use ($candidate) {
                        $q->where('kode_program', $candidate)->orWhere('kode', $candidate);
                    })
                    ->when($dokumen, fn($q) => $q->where('document_type', strtolower($dokumen)));

                // Order so that program-level entries appear first, then by pagu.
                $kom = $komQuery->orderByRaw("(jenis = 'program') DESC")->orderByDesc('pagu')->first();

                if ($kom && !empty(trim((string) ($kom->nama_komponen ?? '')))) {
                    return trim((string) $kom->nama_komponen);
                }

                // Try program table with optional opd filter
                $progQuery = DB::table('program')->where('kode_rek', $candidate);
                if ($opdId !== null) $progQuery->where('opd_id', $opdId);
                $prog = $progQuery->first();
                if ($prog && !empty(trim((string) ($prog->nama_rincian ?? '')))) {
                    return trim((string) $prog->nama_rincian);
                }

                // Try kegiatan table with optional opd filter
                $kegQuery = DB::table('kegiatan')->where('kode_rek', $candidate);
                if ($opdId !== null) $kegQuery->where('opd_id', $opdId);
                $keg = $kegQuery->first();
                if ($keg && !empty(trim((string) ($keg->nama_rincian ?? '')))) {
                    return trim((string) $keg->nama_rincian);
                }

                // Try sub_kegiatan table as an additional fallback
                try {
                    $subQuery = DB::table('sub_kegiatan')->where('kode_rek', $candidate);
                    if ($opdId !== null) $subQuery->where('opd_id', $opdId);
                    $sub = $subQuery->first();
                    if ($sub && !empty(trim((string) ($sub->nama_rincian ?? '')))) {
                        return trim((string) $sub->nama_rincian);
                    }
                } catch (\Throwable $e) {
                    // ignore and continue
                }

                // As another fallback, search komponen_anggaran without document_type filter
                try {
                    $kom2 = DB::table('komponen_anggaran')
                        ->when($opdId !== null, fn($q) => $q->where('opd_id', $opdId))
                        ->where(function ($q) use ($candidate) {
                            $q->where('kode_program', $candidate)->orWhere('kode', $candidate);
                        })
                        ->orderByRaw("(jenis = 'program') DESC, (jenis = 'kegiatan') DESC")
                        ->orderByDesc('pagu')
                        ->first();

                    if ($kom2 && !empty(trim((string) ($kom2->nama_komponen ?? '')))) {
                        return trim((string) $kom2->nama_komponen);
                    }
                } catch (\Throwable $e) {
                    // ignore and continue
                }

                // Try komponen_renstra.json and pagu_program.json with recursive search
                $paths = [public_path('komponen_renstra.json'), base_path('referensi/renstra/pagu_program.json')];
                foreach ($paths as $p) {
                    if (!file_exists($p)) continue;
                    $json = json_decode(file_get_contents($p), true);
                    if (!is_array($json)) continue;
                    $foundName = $findInJson($json, $candidate);
                    if ($foundName) return trim((string) $foundName);
                }
            }
        } catch (\Throwable $e) {
            Log::debug('resolvePreferredProgramName.failed', ['error' => $e->getMessage(), 'kode' => $kode, 'opd_id' => $opdId]);
        }

        // fallback: if the current name contains the placeholder marker '(otomatis)',
        // strip the marker to present a cleaner label. Otherwise keep given name.
        $trimmedCurrent = trim((string) $currentName);
        if ($trimmedCurrent !== '') {
            if (stripos($trimmedCurrent, '(otomatis)') !== false) {
                return trim(preg_replace('/\s*\(otomatis\)\s*/i', '', $trimmedCurrent));
            }
            return $trimmedCurrent;
        }

        // Final fallback: show a readable label using the kode
        return "Kegiatan {$kode}";
    }

    private function formatCurrentTableLabel(string $table): string
    {
        if (str_starts_with($table, 'tabel-')) {
            return str_replace('tabel-', 'Tabel ', $table);
        }

        return $table;
    }

    private function buildUniqueProgramMap(array $programs, string $metricType): array
    {
        $map = [];
        foreach ($this->buildUniqueComparableItems($programs, $metricType) as $item) {
            $key = $this->buildComparableKey($item, $metricType);
            if ($key !== '') {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    private function formatIndicatorTarget(mixed $target): string
    {
        if ($target === null) {
            return '-';
        }

        $text = trim((string) $target);

        return $text === '' ? '-' : $text;
    }

    private function toNumber(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value) ?? '';

        return is_numeric($clean) ? (float) $clean : 0;
    }

    private function getRowProgramName(array $row): string
    {
        $lists = [
            (array) ($row['rpjmd_programs'] ?? []),
            (array) ($row['renstra_programs'] ?? []),
            (array) ($row['rkpd_programs'] ?? []),
        ];

        foreach ($lists as $list) {
            foreach ($list as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $programName = trim((string) ($item['program_nama'] ?? ''));
                if ($programName !== '') {
                    return $programName;
                }
            }
        }

        return '-';
    }

    private function getAlignedIndicatorRows(array $row): array
    {
        $metricType = 'indikator';
        $rpjmdMap = $this->buildUniqueProgramMap((array) ($row['rpjmd_programs'] ?? []), $metricType);
        $renstraMap = $this->buildUniqueProgramMap((array) ($row['renstra_programs'] ?? []), $metricType);
        $rkpdMap = $this->buildUniqueProgramMap((array) ($row['rkpd_programs'] ?? []), $metricType);

        $matchedKeys = [];

        foreach ($rpjmdMap as $key => $_value) {
            if (isset($renstraMap[$key]) || isset($rkpdMap[$key])) {
                $matchedKeys[] = $key;
            }
        }

        foreach ($renstraMap as $key => $_value) {
            if (isset($rkpdMap[$key]) && !in_array($key, $matchedKeys, true)) {
                $matchedKeys[] = $key;
            }
        }

        if (count($matchedKeys) === 0) {
            $matchedKeys = [''];
        }

        $rows = [];
        foreach ($matchedKeys as $key) {
            $rpjmdItem = $rpjmdMap[$key] ?? null;
            $renstraItem = $renstraMap[$key] ?? null;
            $rkpdItem = $rkpdMap[$key] ?? null;

            $rows[] = [
                'rpjmd_name' => (string) ($rpjmdItem['nama'] ?? ''),
                'rpjmd_target' => $this->formatIndicatorTarget($rpjmdItem['target'] ?? null),
                'renstra_name' => (string) ($renstraItem['nama'] ?? ''),
                'renstra_target' => $this->formatIndicatorTarget($renstraItem['target'] ?? null),
                'rkpd_name' => (string) ($rkpdItem['nama'] ?? ''),
                'rkpd_target' => $this->formatIndicatorTarget($rkpdItem['target'] ?? null),
                'status_rpjmd_renstra' => $rpjmdItem === null ? '-' : (isset($renstraMap[$key]) ? 'Konsisten' : 'Tidak Konsisten'),
                'status_rpjmd_rkpd' => $rpjmdItem === null ? '-' : (isset($rkpdMap[$key]) ? 'Konsisten' : 'Tidak Konsisten'),
                'status_renstra_rkpd' => $renstraItem === null ? '-' : (isset($rkpdMap[$key]) ? 'Konsisten' : 'Tidak Konsisten'),
            ];
        }

        return $rows;
    }

    private function getAlignedAnggaranRows(array $row, string $metricType = 'program'): array
    {
        $rpjmdMap = $this->buildUniqueProgramMap((array) ($row['rpjmd_programs'] ?? []), $metricType);
        $renstraMap = $this->buildUniqueProgramMap((array) ($row['renstra_programs'] ?? []), $metricType);
        $rkpdMap = $this->buildUniqueProgramMap((array) ($row['rkpd_programs'] ?? []), $metricType);

        $matchedKeys = [];

        foreach ($rpjmdMap as $key => $_value) {
            if (isset($renstraMap[$key]) || isset($rkpdMap[$key])) {
                $matchedKeys[] = $key;
            }
        }

        foreach ($renstraMap as $key => $_value) {
            if (isset($rkpdMap[$key]) && !in_array($key, $matchedKeys, true)) {
                $matchedKeys[] = $key;
            }
        }

        if (count($matchedKeys) === 0) {
            $matchedKeys = [''];
        }

        $rows = [];
        foreach ($matchedKeys as $key) {
            $rpjmdItem = $rpjmdMap[$key] ?? null;
            $renstraItem = $renstraMap[$key] ?? null;
            $rkpdItem = $rkpdMap[$key] ?? null;

            $rpjmdPagu = $this->toNumber($rpjmdItem['pagu'] ?? null);
            $renstraPagu = $this->toNumber($renstraItem['pagu'] ?? null);
            $rkpdPagu = $this->toNumber($rkpdItem['pagu'] ?? null);

            $diffRpjmdRenstra = abs($rpjmdPagu - $renstraPagu);
            $diffRpjmdRkpd = abs($rpjmdPagu - $rkpdPagu);
            $diffRenstraRkpd = abs($renstraPagu - $rkpdPagu);

            $rows[] = [
                'program_name' => (string) ($rpjmdItem['nama'] ?? $renstraItem['nama'] ?? $rkpdItem['nama'] ?? '-'),
                'rpjmd_pagu' => (int) round($rpjmdPagu),
                'renstra_pagu' => (int) round($renstraPagu),
                'rkpd_pagu' => (int) round($rkpdPagu),
                'diff_rpjmd_renstra' => (int) round($diffRpjmdRenstra),
                'diff_rpjmd_rkpd' => (int) round($diffRpjmdRkpd),
                'diff_renstra_rkpd' => (int) round($diffRenstraRkpd),
                'status_rpjmd_renstra' => $diffRpjmdRenstra === 0.0 ? 'Konsisten' : 'Tidak Konsisten',
                'status_rpjmd_rkpd' => $diffRpjmdRkpd === 0.0 ? 'Konsisten' : 'Tidak Konsisten',
                'status_renstra_rkpd' => $diffRenstraRkpd === 0.0 ? 'Konsisten' : 'Tidak Konsisten',
            ];
        }

        return $rows;
    }

    private function buildTabel3ExportGroups(Collection $tableData): array
    {
        return $tableData->map(function ($row) {
            $rowData = is_array($row) ? $row : [];

            return [
                'no' => (int) ($rowData['no'] ?? 0),
                'entitas' => $this->formatResumeEntityLabel((string) ($rowData['entitas'] ?? '')),
                'program_name' => $this->getRowProgramName($rowData),
                'lines' => $this->getAlignedIndicatorRows($rowData),
            ];
        })->values()->all();
    }

    private function getKonsistensiRkpdApbdIndikator(string $filterBasis, ?int $selectedYear): Collection
    {
        $query = DB::table('indikator_anggaran as ia')
            ->join('komponen_anggaran as ka', 'ka.id', '=', 'ia.komponen_anggaran_id')
            ->leftJoin('program as p', function ($join) {
                $join->on('p.opd_id', '=', 'ka.opd_id')
                    ->on('p.kode_rek', '=', 'ka.kode_program');
            })
            ->select([
                'ka.opd_id',
                'ka.document_type',
                'ia.id as indikator_id',
                'ia.nama_indikator as indikator_uraian',
                'ka.kode_program as program_kode',
                'p.nama_rincian as program_nama',
                'ia.target_indikator as indikator_target',
                DB::raw("concat('anggaran_', ka.jenis) as indikator_jenis"),
                'ka.tahun',
                DB::raw('case when p.id is null then 0 else 1 end as is_prioritas'),
            ])
            ->whereIn('ka.document_type', ['rkpd', 'renja', 'dpa']);

        if ($selectedYear !== null) {
            $query->where(function ($sub) use ($selectedYear) {
                $sub->where('ka.document_type', 'rkpd')
                    ->orWhere(function ($q2) use ($selectedYear) {
                        $q2->where('ka.document_type', 'renja')
                            ->where('ka.tahun', $selectedYear);
                    })
                    ->orWhere(function ($q3) use ($selectedYear) {
                        $q3->where('ka.document_type', 'dpa')
                            ->where('ka.tahun', $selectedYear);
                    });
            });
        }

        $indikatorRows = $query->get();

        if ($filterBasis === 'perangkat-daerah') {
            return $this->aggregateIndikatorRkpdApbdByOpd($indikatorRows, $selectedYear);
        }

        return $this->aggregateIndikatorRkpdApbdByBidang($indikatorRows, $selectedYear);
    }

    private function aggregateIndikatorRkpdApbdByOpd(Collection $indikatorRows, ?int $selectedYear): Collection
    {
        $opds = Opd::query()
            ->select(['id', 'nama'])
            ->get()
            ->sortBy('nama')
            ->values();

        $aggregates = [];

        foreach ($indikatorRows as $row) {
            $opdId = $row->opd_id ?? null;
            if (!isset($aggregates[$opdId])) {
                $aggregates[$opdId] = [
                    'rkpd_programs' => [],
                    'dpa_programs' => [],
                    'rkpd_programs_keys' => [],
                    'dpa_programs_keys' => [],
                ];
            }

            $matchesSelectedYear = $selectedYear === null || (int) $row->tahun === $selectedYear;

            if (in_array($row->document_type, ['rkpd', 'renja'], true) && $matchesSelectedYear) {
                $this->appendIndikator($aggregates[$opdId], 'rkpd_programs', $row);
            }

            if ($row->document_type === 'dpa' && $matchesSelectedYear) {
                $this->appendIndikator($aggregates[$opdId], 'dpa_programs', $row);
            }
        }

        return $opds->map(function (Opd $opd, int $index) use ($aggregates) {
            $counts = $aggregates[$opd->id] ?? ['rkpd_programs' => [], 'dpa_programs' => []];

            // build aligned lines per indikator key
            $lines = $this->buildAlignedIndicatorLines((array) ($counts['rkpd_programs'] ?? []), (array) ($counts['dpa_programs'] ?? []));

            return [
                'no' => $index + 1,
                'entitas' => $opd->nama,
                'program_name' => $this->getRowProgramName((array) $counts),
                'rkpd_count' => count($counts['rkpd_programs']),
                'dpa_count' => count($counts['dpa_programs']),
                'rkpd_programs' => array_values($counts['rkpd_programs']),
                'dpa_programs' => array_values($counts['dpa_programs']),
                'lines' => $lines,
            ];
        });
    }

    /**
     * Load referensi RKPD items from local JSON files when DB is empty.
     * Returns collection of rows matching same shape as DB queries.
     */
    private function loadReferensiRkpdItems(string $metricType, ?int $selectedYear = null): \Illuminate\Support\Collection
    {
        $base = base_path('referensi/rkpd');

        try {
            if ($metricType === 'kegiatan') {
                $file = $base.'/kegaiatan.json';
                $content = @file_get_contents($file);
                $items = $content ? json_decode($content, true) ?? [] : [];

                $rows = collect($items)->map(function ($it) {
                    // map KODE_KEGIATAN -> kode, and NAMA_KEGIATAN -> nama
                    return [
                        'opd_id' => $this->findOpdIdByKode((string) ($it['KODE_SKPD'] ?? '')),
                        'kode' => (string) ($it['KODE_KEGIATAN'] ?? ''),
                        'nama' => (string) ($it['NAMA_KEGIATAN'] ?? ''),
                        'dokumen' => 'RENJA',
                        'tahun' => $selectedYear,
                    ];
                })->filter(fn($r) => $r['opd_id'] !== null);

                return $rows;
            }

            if ($metricType === 'sub_kegiatan') {
                $file = $base.'/sub_kegiatan.json';
                $content = @file_get_contents($file);
                $items = $content ? json_decode($content, true) ?? [] : [];

                $rows = collect($items)->map(function ($it) {
                    return [
                        'opd_id' => $this->findOpdIdByKode((string) ($it['KODE_SKPD'] ?? '')),
                        'kode' => (string) ($it['KODE_SUB_KEGIATAN'] ?? ''),
                        'nama' => (string) ($it['NAMA_SUB_KEGIATAN'] ?? ''),
                        'dokumen' => 'RENJA',
                        'tahun' => $it['TAHUN'] ?? null,
                    ];
                })->filter(fn($r) => $r['opd_id'] !== null);

                return $rows;
            }

            // default: program
            $file = $base.'/program.json';
            $content = @file_get_contents($file);
            $items = $content ? json_decode($content, true) ?? [] : [];

            $rows = collect($items)->map(function ($it) {
                return [
                    'opd_id' => $this->findOpdIdByKode((string) ($it['KODE_SKPD'] ?? '')),
                    'kode' => (string) ($it['KODE_PROGRAM'] ?? ''),
                    'nama' => (string) ($it['NAMA_PROGRAM'] ?? ''),
                    'dokumen' => 'RENJA',
                    'tahun' => $it['TAHUN'] ?? null,
                ];
            })->filter(fn($r) => $r['opd_id'] !== null);

            return $rows;
        } catch (\Throwable $e) {
            return collect([]);
        }
    }

    private function findOpdIdByKode(string $kode)
    {
        $k = trim($kode);
        if ($k === '') return null;

        $opd = Opd::where('kode', $k)->first();
        return $opd ? $opd->id : null;
    }

    private function aggregateIndikatorRkpdApbdByBidang(Collection $indikatorRows, ?int $selectedYear): Collection
    {
        $bidangUrusans = BidangUrusan::query()
            ->select(['id', 'kode', 'nama'])
            ->get()
            ->unique(fn (BidangUrusan $bidang) => $bidang->kode.'|'.$bidang->nama)
            ->sortBy('kode')
            ->values();

        $opdBidangById = Opd::query()
            ->select(['id', 'kode'])
            ->get()
            ->mapWithKeys(fn (Opd $opd) => [
                $opd->id => $this->extractBidangKode((string) $opd->kode),
            ]);

        $aggregates = [];

        foreach ($indikatorRows as $row) {
            $bidangKode = $opdBidangById[$row->opd_id] ?? null;
            if (!$bidangKode) continue;

            $matchesSelectedYear = $selectedYear === null || (int) $row->tahun === $selectedYear;

            if (!isset($aggregates[$bidangKode])) {
                $aggregates[$bidangKode] = [
                    'rkpd_programs' => [],
                    'dpa_programs' => [],
                    'rkpd_programs_keys' => [],
                    'dpa_programs_keys' => [],
                ];
            }

            if (in_array($row->document_type, ['rkpd', 'renja'], true) && $matchesSelectedYear) {
                $this->appendIndikator($aggregates[$bidangKode], 'rkpd_programs', $row);
            }

            if ($row->document_type === 'dpa' && $matchesSelectedYear) {
                $this->appendIndikator($aggregates[$bidangKode], 'dpa_programs', $row);
            }
        }

        return $bidangUrusans->values()->map(function (BidangUrusan $bidang, int $index) use ($aggregates) {
            $counts = $aggregates[$bidang->kode] ?? ['rkpd_programs' => [], 'dpa_programs' => []];

            $lines = $this->buildAlignedIndicatorLines((array) ($counts['rkpd_programs'] ?? []), (array) ($counts['dpa_programs'] ?? []));

            return [
                'no' => $index + 1,
                'entitas' => $bidang->nama,
                'program_name' => $this->getRowProgramName((array) $counts),
                'rkpd_count' => count($counts['rkpd_programs']),
                'dpa_count' => count($counts['dpa_programs']),
                'rkpd_programs' => array_values($counts['rkpd_programs']),
                'dpa_programs' => array_values($counts['dpa_programs']),
                'lines' => $lines,
            ];
        });
    }

    private function buildAlignedIndicatorLines(array $rkpdPrograms, array $dpaPrograms): array
    {
        // Instead of deduping, accumulate counts per comparable key then compare counts.
        $rkpdCounts = [];
        $rkpdSamples = [];
        foreach ($rkpdPrograms as $it) {
            if (!is_array($it)) continue;
            $key = $this->buildComparableKey($it, 'indikator');
            if ($key === '' || $key === '|' ) continue;
            $rkpdCounts[$key] = ($rkpdCounts[$key] ?? 0) + 1;
            $rkpdSamples[$key] = $it;
        }

        $dpaCounts = [];
        $dpaSamples = [];
        foreach ($dpaPrograms as $it) {
            if (!is_array($it)) continue;
            $key = $this->buildComparableKey($it, 'indikator');
            if ($key === '' || $key === '|' ) continue;
            $dpaCounts[$key] = ($dpaCounts[$key] ?? 0) + 1;
            $dpaSamples[$key] = $it;
        }

        $keys = array_values(array_unique(array_merge(array_keys($rkpdCounts), array_keys($dpaCounts))));
        if (count($keys) === 0) {
            $keys = [''];
        }

        $lines = [];
        foreach ($keys as $k) {
            $r = $rkpdSamples[$k] ?? null;
            $d = $dpaSamples[$k] ?? null;
            $rkpdCount = $rkpdCounts[$k] ?? 0;
            $dpaCount = $dpaCounts[$k] ?? 0;

            $lines[] = [
                'program_name' => (string) ($r['program_nama'] ?? $d['program_nama'] ?? '-'),
                'rkpd_name' => (string) ($r['nama'] ?? ''),
                'dpa_name' => (string) ($d['nama'] ?? ''),
                'rkpd_count' => $rkpdCount,
                'dpa_count' => $dpaCount,
                'status' => ($rkpdCount > 0 && $rkpdCount === $dpaCount) ? 'Konsisten' : 'Tidak Konsisten',
            ];
        }

        return $lines;
    }

    private function buildTabel4ExportGroups(Collection $tableData, string $metricType = 'program'): array
    {
        return $tableData->map(function ($row) use ($metricType) {
            $rowData = is_array($row) ? $row : [];

            return [
                'no' => (int) ($rowData['no'] ?? 0),
                'entitas' => $this->formatResumeEntityLabel((string) ($rowData['entitas'] ?? '')),
                'lines' => $this->getAlignedAnggaranRows($rowData, $metricType),
            ];
        })->values()->all();
    }
    
    private function getKonsistensiRpjmdRkpd(string $filterBasis, ?int $selectedYear, string $metricType = 'program'): Collection
    {
        if ($metricType === 'indikator') {
            return $this->getKonsistensiRpjmdRkpdIndikator($filterBasis, $selectedYear);
        }

        $programs = Program::query()
            ->select(['opd_id', 'document_type', 'is_prioritas', 'tahun', 'kode_rek', 'nama_rincian', 'pagu'])
            ->whereNotNull('opd_id')
            ->get();

        $programs = $this->attachEffectiveProgramPagu($programs);

        if ($filterBasis === 'perangkat-daerah') {
            return $this->aggregateByOpd($programs, $selectedYear);
        }

        return $this->aggregateByBidangUrusan($programs, $selectedYear);
    }

    private function attachEffectiveProgramPagu(Collection $programs): Collection
    {
        if ($programs->isEmpty()) {
            return $programs;
        }

        $opdIds = $programs
            ->pluck('opd_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $kodePrograms = $programs
            ->pluck('kode_rek')
            ->filter(fn ($kode) => trim((string) $kode) !== '')
            ->unique()
            ->values()
            ->all();

        if (count($opdIds) === 0 || count($kodePrograms) === 0) {
            return $programs;
        }

        $paguByYear = [];
        $paguByDoc = [];

        DB::table('komponen_anggaran')
            ->select([
                'opd_id',
                'kode_program',
                'document_type',
                'tahun',
                DB::raw('SUM(COALESCE(pagu, 0)) as total_pagu'),
            ])
            ->where('jenis', 'sub_kegiatan')
            ->whereIn('document_type', ['rpjmd', 'renstra', 'rkpd', 'renja'])
            ->whereIn('opd_id', $opdIds)
            ->whereIn('kode_program', $kodePrograms)
            ->groupBy('opd_id', 'kode_program', 'document_type', 'tahun')
            ->get()
            ->each(function ($row) use (&$paguByYear, &$paguByDoc) {
                $opdId = (int) ($row->opd_id ?? 0);
                $kode = trim((string) ($row->kode_program ?? ''));
                $documentType = trim((string) ($row->document_type ?? ''));
                $tahun = $row->tahun !== null ? (int) $row->tahun : null;
                $total = (int) ($row->total_pagu ?? 0);

                if ($opdId === 0 || $kode === '' || $documentType === '') {
                    return;
                }

                if ($tahun !== null) {
                    $paguByYear[$opdId.'|'.$kode.'|'.$documentType.'|'.$tahun] = $total;
                }

                $docKey = $opdId.'|'.$kode.'|'.$documentType;
                $paguByDoc[$docKey] = ($paguByDoc[$docKey] ?? 0) + $total;
            });

        return $programs->map(function (Program $program) use ($paguByYear, $paguByDoc) {
            $basePagu = (int) ($program->pagu ?? 0);
            if ($basePagu > 0) {
                $program->effective_pagu = $basePagu;
                return $program;
            }

            $opdId = (int) ($program->opd_id ?? 0);
            $kode = trim((string) ($program->kode_rek ?? ''));
            $documentType = trim((string) ($program->document_type ?? ''));
            $tahun = $program->tahun !== null ? (int) $program->tahun : null;

            $byYearKey = $tahun !== null ? $opdId.'|'.$kode.'|'.$documentType.'|'.$tahun : null;
            $byDocKey = $opdId.'|'.$kode.'|'.$documentType;

            $program->effective_pagu = $byYearKey !== null && isset($paguByYear[$byYearKey])
                ? (int) $paguByYear[$byYearKey]
                : (int) ($paguByDoc[$byDocKey] ?? 0);

            return $program;
        });
    }

    private function getKonsistensiRpjmdRkpdIndikator(string $filterBasis, ?int $selectedYear): Collection
    {
        $indikatorRows = Program::query()
            ->select([
                'program.opd_id',
                'program.document_type',
                'program.is_prioritas',
                'program.tahun',
                'program.kode_rek as program_kode',
                'program.nama_rincian as program_nama',
                'indikator.id as indikator_id',
                'indikator.uraian as indikator_uraian',
                'indikator.jenis_indikator as indikator_jenis',
                'indikatorables.target as indikator_target',
            ])
            ->join('indikatorables', function ($join) {
                $join->on('indikatorables.indicatorable_id', '=', 'program.id')
                    ->where('indikatorables.indicatorable_type', Program::class);
            })
            ->join('indikator', 'indikator.id', '=', 'indikatorables.indikator_id')
            ->whereNotNull('program.opd_id')
            ->get();

        // Fallback: pada beberapa data lama pivot indikatorables belum terisi.
        // Supaya tabel-2 tetap berisi, ambil langsung dari master indikator per OPD/dokumen.
        if ($indikatorRows->isEmpty()) {
            return $this->getKonsistensiRpjmdRkpdIndikatorFallback($filterBasis, $selectedYear);
        }

        if ($filterBasis === 'perangkat-daerah') {
            return $this->aggregateIndikatorByOpd($indikatorRows, $selectedYear);
        }

        return $this->aggregateIndikatorByBidangUrusan($indikatorRows, $selectedYear);
    }

    private function getKonsistensiRpjmdRkpdIndikatorFallback(string $filterBasis, ?int $selectedYear): Collection
    {
        $indikatorRows = DB::table('indikator')
            ->select([
                'opd_id',
                'document_type',
                'id as indikator_id',
                'uraian as indikator_uraian',
                'jenis_indikator as indikator_jenis',
                DB::raw('null as program_kode'),
                DB::raw('null as indikator_target'),
                DB::raw('null as program_nama'),
            ])
            ->whereNotNull('opd_id')
            ->get();

        $anggaranIndicatorRows = $this->getIndikatorAnggaranRows($selectedYear);

        if ($filterBasis === 'perangkat-daerah') {
            return $this->aggregateIndikatorFallbackByOpd($indikatorRows, $anggaranIndicatorRows, $selectedYear);
        }

        return $this->aggregateIndikatorFallbackByBidangUrusan($indikatorRows, $anggaranIndicatorRows, $selectedYear);
    }

    private function aggregateIndikatorFallbackByBidangUrusan(Collection $indikatorRows, Collection $anggaranIndicatorRows, ?int $selectedYear): Collection
    {
        $bidangUrusans = BidangUrusan::query()
            ->select(['id', 'kode', 'nama'])
            ->get()
            ->unique(fn (BidangUrusan $bidang) => $bidang->kode.'|'.$bidang->nama)
            ->sortBy('kode')
            ->values();

        $opdBidangById = Opd::query()
            ->select(['id', 'kode'])
            ->get()
            ->mapWithKeys(fn (Opd $opd) => [
                $opd->id => $this->extractBidangKode((string) $opd->kode),
            ]);

        $aggregates = [];

        foreach ($indikatorRows as $indikatorRow) {
            $bidangKode = $opdBidangById[$indikatorRow->opd_id] ?? null;
            if (!$bidangKode) {
                continue;
            }

            if (!isset($aggregates[$bidangKode])) {
                $aggregates[$bidangKode] = [
                    'rpjmd_programs' => [],
                    'renstra_programs' => [],
                    'rkpd_programs' => [],
                    'rpjmd_programs_keys' => [],
                    'renstra_programs_keys' => [],
                    'rkpd_programs_keys' => [],
                ];
            }

            if ($indikatorRow->document_type === 'rpjmd') {
                $this->appendIndikator($aggregates[$bidangKode], 'rpjmd_programs', $indikatorRow);
            }

            if ($indikatorRow->document_type === 'renstra') {
                $this->appendIndikator($aggregates[$bidangKode], 'renstra_programs', $indikatorRow);

                if ((bool) ($indikatorRow->is_prioritas ?? false)) {
                    $this->appendIndikator($aggregates[$bidangKode], 'rpjmd_programs', $indikatorRow);
                }
            }

            if ($indikatorRow->document_type === 'renja') {
                $this->appendIndikator($aggregates[$bidangKode], 'rkpd_programs', $indikatorRow);
            }
        }

        foreach ($anggaranIndicatorRows as $indikatorRow) {
            $bidangKode = $opdBidangById[$indikatorRow->opd_id] ?? null;
            if (!$bidangKode) {
                continue;
            }

            if (!isset($aggregates[$bidangKode])) {
                $aggregates[$bidangKode] = [
                    'rpjmd_programs' => [],
                    'renstra_programs' => [],
                    'rkpd_programs' => [],
                    'rpjmd_programs_keys' => [],
                    'renstra_programs_keys' => [],
                    'rkpd_programs_keys' => [],
                ];
            }

            if ($indikatorRow->document_type === 'renstra') {
                $this->appendIndikator($aggregates[$bidangKode], 'renstra_programs', $indikatorRow);

                if ((bool) ($indikatorRow->is_prioritas ?? false)) {
                    $this->appendIndikator($aggregates[$bidangKode], 'rpjmd_programs', $indikatorRow);
                }
            }

            if ($indikatorRow->document_type === 'renja') {
                $this->appendIndikator($aggregates[$bidangKode], 'rkpd_programs', $indikatorRow);
            }
        }

        return $bidangUrusans->values()->map(function (BidangUrusan $bidang, int $index) use ($aggregates) {
            $counts = $aggregates[$bidang->kode] ?? [
                'rpjmd_programs' => [],
                'renstra_programs' => [],
                'rkpd_programs' => [],
            ];

            return [
                'no' => $index + 1,
                'entitas' => $bidang->nama,
                'rpjmd_count' => count($counts['rpjmd_programs']),
                'renstra_count' => count($counts['renstra_programs']),
                'rkpd_count' => count($counts['rkpd_programs']),
                'rpjmd_programs' => array_values($counts['rpjmd_programs']),
                'renstra_programs' => array_values($counts['renstra_programs']),
                'rkpd_programs' => array_values($counts['rkpd_programs']),
            ];
        });
    }

    private function aggregateIndikatorFallbackByOpd(Collection $indikatorRows, Collection $anggaranIndicatorRows, ?int $selectedYear): Collection
    {
        $opds = Opd::query()
            ->select(['id', 'nama'])
            ->get()
            ->sortBy('nama')
            ->values();

        $aggregates = [];

        foreach ($indikatorRows as $indikatorRow) {
            if (!isset($aggregates[$indikatorRow->opd_id])) {
                $aggregates[$indikatorRow->opd_id] = [
                    'rpjmd_programs' => [],
                    'renstra_programs' => [],
                    'rkpd_programs' => [],
                    'rpjmd_programs_keys' => [],
                    'renstra_programs_keys' => [],
                    'rkpd_programs_keys' => [],
                ];
            }

            if ($indikatorRow->document_type === 'rpjmd') {
                $this->appendIndikator($aggregates[$indikatorRow->opd_id], 'rpjmd_programs', $indikatorRow);
            }

            if ($indikatorRow->document_type === 'renstra') {
                $this->appendIndikator($aggregates[$indikatorRow->opd_id], 'renstra_programs', $indikatorRow);

                if ((bool) ($indikatorRow->is_prioritas ?? false)) {
                    $this->appendIndikator($aggregates[$indikatorRow->opd_id], 'rpjmd_programs', $indikatorRow);
                }
            }

            if ($indikatorRow->document_type === 'renja') {
                $this->appendIndikator($aggregates[$indikatorRow->opd_id], 'rkpd_programs', $indikatorRow);
            }
        }

        foreach ($anggaranIndicatorRows as $indikatorRow) {
            if (!isset($aggregates[$indikatorRow->opd_id])) {
                $aggregates[$indikatorRow->opd_id] = [
                    'rpjmd_programs' => [],
                    'renstra_programs' => [],
                    'rkpd_programs' => [],
                    'rpjmd_programs_keys' => [],
                    'renstra_programs_keys' => [],
                    'rkpd_programs_keys' => [],
                ];
            }

            if ($indikatorRow->document_type === 'renstra') {
                $this->appendIndikator($aggregates[$indikatorRow->opd_id], 'renstra_programs', $indikatorRow);

                if ((bool) ($indikatorRow->is_prioritas ?? false)) {
                    $this->appendIndikator($aggregates[$indikatorRow->opd_id], 'rpjmd_programs', $indikatorRow);
                }
            }

            if ($indikatorRow->document_type === 'renja') {
                $this->appendIndikator($aggregates[$indikatorRow->opd_id], 'rkpd_programs', $indikatorRow);
            }
        }

        return $opds->map(function (Opd $opd, int $index) use ($aggregates) {
            $counts = $aggregates[$opd->id] ?? [
                'rpjmd_programs' => [],
                'renstra_programs' => [],
                'rkpd_programs' => [],
            ];

            return [
                'no' => $index + 1,
                'entitas' => $opd->nama,
                'rpjmd_count' => count($counts['rpjmd_programs']),
                'renstra_count' => count($counts['renstra_programs']),
                'rkpd_count' => count($counts['rkpd_programs']),
                'rpjmd_programs' => array_values($counts['rpjmd_programs']),
                'renstra_programs' => array_values($counts['renstra_programs']),
                'rkpd_programs' => array_values($counts['rkpd_programs']),
            ];
        });
    }

    private function getIndikatorAnggaranRows(?int $selectedYear): Collection
    {
        $query = DB::table('indikator_anggaran as ia')
            ->join('komponen_anggaran as ka', 'ka.id', '=', 'ia.komponen_anggaran_id')
            ->leftJoin('program as p', function ($join) {
                $join->on('p.opd_id', '=', 'ka.opd_id')
                    ->on('p.kode_rek', '=', 'ka.kode_program')
                    ->where('p.document_type', 'renstra')
                    ->where('p.is_prioritas', 1);
            })
            ->select([
                'ka.opd_id',
                'ka.document_type',
                'ia.id as indikator_id',
                'ia.nama_indikator as indikator_uraian',
                'ka.kode_program as program_kode',
                'p.nama_rincian as program_nama',
                'ia.target_indikator as indikator_target',
                DB::raw("concat('anggaran_', ka.jenis) as indikator_jenis"),
                'ka.tahun',
                DB::raw('case when p.id is null then 0 else 1 end as is_prioritas'),
            ])
            ->whereIn('ka.document_type', ['renstra', 'renja']);

        if ($selectedYear !== null) {
            $query->where(function ($subQuery) use ($selectedYear) {
                $subQuery->where('ka.document_type', 'renstra')
                    ->orWhere(function ($renjaQuery) use ($selectedYear) {
                        $renjaQuery->where('ka.document_type', 'renja')
                            ->where('ka.tahun', $selectedYear);
                    });
            });
        }

        return $query->get();
    }

    private function aggregateIndikatorByBidangUrusan(Collection $indikatorRows, ?int $selectedYear): Collection
    {
        $bidangUrusans = BidangUrusan::query()
            ->select(['id', 'kode', 'nama'])
            ->get()
            ->unique(fn (BidangUrusan $bidang) => $bidang->kode.'|'.$bidang->nama)
            ->sortBy('kode')
            ->values();

        $opdBidangById = Opd::query()
            ->select(['id', 'kode'])
            ->get()
            ->mapWithKeys(fn (Opd $opd) => [
                $opd->id => $this->extractBidangKode((string) $opd->kode),
            ]);

        $aggregates = [];

        foreach ($indikatorRows as $indikatorRow) {
            $bidangKode = $opdBidangById[$indikatorRow->opd_id] ?? null;
            if (!$bidangKode) {
                continue;
            }

            $matchesSelectedYear = $selectedYear === null || (int) $indikatorRow->tahun === $selectedYear;

            if (!isset($aggregates[$bidangKode])) {
                $aggregates[$bidangKode] = [
                    'rpjmd_programs' => [],
                    'renstra_programs' => [],
                    'rkpd_programs' => [],
                    'rpjmd_programs_keys' => [],
                    'renstra_programs_keys' => [],
                    'rkpd_programs_keys' => [],
                ];
            }

            if ($indikatorRow->document_type === 'renstra') {
                $this->appendIndikator($aggregates[$bidangKode], 'renstra_programs', $indikatorRow);

                if ((bool) $indikatorRow->is_prioritas) {
                    $this->appendIndikator($aggregates[$bidangKode], 'rpjmd_programs', $indikatorRow);
                }
            }

            if (in_array($indikatorRow->document_type, ['rkpd', 'renja'], true) && $matchesSelectedYear) {
                $this->appendIndikator($aggregates[$bidangKode], 'rkpd_programs', $indikatorRow);
            }
        }

        return $bidangUrusans->values()->map(function (BidangUrusan $bidang, int $index) use ($aggregates) {
            $counts = $aggregates[$bidang->kode] ?? [
                'rpjmd_programs' => [],
                'renstra_programs' => [],
                'rkpd_programs' => [],
            ];

            return [
                'no' => $index + 1,
                'entitas' => $bidang->nama,
                'rpjmd_count' => count($counts['rpjmd_programs']),
                'renstra_count' => count($counts['renstra_programs']),
                'rkpd_count' => count($counts['rkpd_programs']),
                'rpjmd_programs' => array_values($counts['rpjmd_programs']),
                'renstra_programs' => array_values($counts['renstra_programs']),
                'rkpd_programs' => array_values($counts['rkpd_programs']),
            ];
        });
    }

    private function aggregateIndikatorByOpd(Collection $indikatorRows, ?int $selectedYear): Collection
    {
        $opds = Opd::query()
            ->select(['id', 'nama'])
            ->get()
            ->sortBy('nama')
            ->values();

        $aggregates = [];

        foreach ($indikatorRows as $indikatorRow) {
            if (!isset($aggregates[$indikatorRow->opd_id])) {
                $aggregates[$indikatorRow->opd_id] = [
                    'rpjmd_programs' => [],
                    'renstra_programs' => [],
                    'rkpd_programs' => [],
                    'rpjmd_programs_keys' => [],
                    'renstra_programs_keys' => [],
                    'rkpd_programs_keys' => [],
                ];
            }

            $matchesSelectedYear = $selectedYear === null || (int) $indikatorRow->tahun === $selectedYear;

            if ($indikatorRow->document_type === 'renstra') {
                $this->appendIndikator($aggregates[$indikatorRow->opd_id], 'renstra_programs', $indikatorRow);

                if ((bool) $indikatorRow->is_prioritas) {
                    $this->appendIndikator($aggregates[$indikatorRow->opd_id], 'rpjmd_programs', $indikatorRow);
                }
            }

            if (in_array($indikatorRow->document_type, ['rkpd', 'renja'], true) && $matchesSelectedYear) {
                $this->appendIndikator($aggregates[$indikatorRow->opd_id], 'rkpd_programs', $indikatorRow);
            }
        }

        return $opds->map(function (Opd $opd, int $index) use ($aggregates) {
            $counts = $aggregates[$opd->id] ?? [
                'rpjmd_programs' => [],
                'renstra_programs' => [],
                'rkpd_programs' => [],
            ];

            return [
                'no' => $index + 1,
                'entitas' => $opd->nama,
                'rpjmd_count' => count($counts['rpjmd_programs']),
                'renstra_count' => count($counts['renstra_programs']),
                'rkpd_count' => count($counts['rkpd_programs']),
                'rpjmd_programs' => array_values($counts['rpjmd_programs']),
                'renstra_programs' => array_values($counts['renstra_programs']),
                'rkpd_programs' => array_values($counts['rkpd_programs']),
            ];
        });
    }

    private function aggregateByBidangUrusan(Collection $programs, ?int $selectedYear): Collection
    {
        $bidangUrusans = BidangUrusan::query()
            ->select(['id', 'kode', 'nama'])
            ->get()
            ->unique(fn (BidangUrusan $bidang) => $bidang->kode.'|'.$bidang->nama)
            ->sortBy('kode')
            ->values();

        $opdBidangById = Opd::query()
            ->select(['id', 'kode'])
            ->get()
            ->mapWithKeys(fn (Opd $opd) => [
                $opd->id => $this->extractBidangKode((string) $opd->kode),
            ]);

        $aggregates = [];

        foreach ($programs as $program) {
            $bidangKode = $opdBidangById[$program->opd_id] ?? null;
            if (!$bidangKode) {
                continue;
            }

            $matchesSelectedYear = $selectedYear === null || (int) $program->tahun === $selectedYear;

            if (!isset($aggregates[$bidangKode])) {
                $aggregates[$bidangKode] = [
                    'rpjmd_programs' => [],
                    'renstra_programs' => [],
                    'rkpd_programs' => [],
                    'rpjmd_programs_keys' => [],
                    'renstra_programs_keys' => [],
                    'rkpd_programs_keys' => [],
                ];
            }

            if ($program->document_type === 'renstra') {
                $this->appendProgram($aggregates[$bidangKode], 'renstra_programs', $program);

                if ((bool) $program->is_prioritas) {
                    // Definisi user: RPJMD di resume ini berasal dari program prioritas di Renstra.
                    $this->appendProgram($aggregates[$bidangKode], 'rpjmd_programs', $program);
                }
            }

            if (in_array($program->document_type, ['rkpd', 'renja'], true) && $matchesSelectedYear) {
                $this->appendProgram($aggregates[$bidangKode], 'rkpd_programs', $program);
            }
        }

        return $bidangUrusans->values()->map(function (BidangUrusan $bidang, int $index) use ($aggregates) {
            $counts = $aggregates[$bidang->kode] ?? [
                'rpjmd_programs' => [],
                'renstra_programs' => [],
                'rkpd_programs' => [],
            ];

            return [
                'no' => $index + 1,
                'entitas' => $bidang->nama,
                'rpjmd_count' => count($counts['rpjmd_programs']),
                'renstra_count' => count($counts['renstra_programs']),
                'rkpd_count' => count($counts['rkpd_programs']),
                'rpjmd_programs' => array_values($counts['rpjmd_programs']),
                'renstra_programs' => array_values($counts['renstra_programs']),
                'rkpd_programs' => array_values($counts['rkpd_programs']),
            ];
        });
    }

    private function aggregateByOpd(Collection $programs, ?int $selectedYear): Collection
    {
        $opds = Opd::query()
            ->select(['id', 'nama'])
            ->get()
            ->sortBy('nama')
            ->values();

        $aggregates = [];

        foreach ($programs as $program) {
            if (!isset($aggregates[$program->opd_id])) {
                $aggregates[$program->opd_id] = [
                    'rpjmd_programs' => [],
                    'renstra_programs' => [],
                    'rkpd_programs' => [],
                    'rpjmd_programs_keys' => [],
                    'renstra_programs_keys' => [],
                    'rkpd_programs_keys' => [],
                ];
            }

            $matchesSelectedYear = $selectedYear === null || (int) $program->tahun === $selectedYear;

            if ($program->document_type === 'renstra') {
                $this->appendProgram($aggregates[$program->opd_id], 'renstra_programs', $program);

                if ((bool) $program->is_prioritas) {
                    $this->appendProgram($aggregates[$program->opd_id], 'rpjmd_programs', $program);
                }
            }

            if (in_array($program->document_type, ['rkpd', 'renja'], true) && $matchesSelectedYear) {
                $this->appendProgram($aggregates[$program->opd_id], 'rkpd_programs', $program);
            }
        }

        return $opds->map(function (Opd $opd, int $index) use ($aggregates) {
            $counts = $aggregates[$opd->id] ?? [
                'rpjmd_programs' => [],
                'renstra_programs' => [],
                'rkpd_programs' => [],
            ];

            return [
                'no' => $index + 1,
                'entitas' => $opd->nama,
                'rpjmd_count' => count($counts['rpjmd_programs']),
                'renstra_count' => count($counts['renstra_programs']),
                'rkpd_count' => count($counts['rkpd_programs']),
                'rpjmd_programs' => array_values($counts['rpjmd_programs']),
                'renstra_programs' => array_values($counts['renstra_programs']),
                'rkpd_programs' => array_values($counts['rkpd_programs']),
            ];
        });
    }

    private function getKonsistensiRkpdApbd(string $filterBasis, ?int $selectedYear, string $metricType = 'program'): Collection
    {
        if ($metricType === 'indikator') {
            return $this->getKonsistensiRkpdApbdIndikator($filterBasis, $selectedYear);
        }
        // RKPD/Renja items: could be programs or kegiatan depending on metricType
        if ($metricType === 'kegiatan') {
            $rkpdPrograms = \App\Models\Kegiatan::query()
                ->select(['opd_id', 'document_type', 'kode_rek', 'nama_rincian', 'tahun'])
                ->whereIn('document_type', ['rkpd', 'renja'])
                ->get()
                ->map(function ($p) {
                    return [
                        'opd_id' => $p->opd_id,
                        'kode' => (string) ($p->kode_rek ?? ''),
                        'nama' => (string) ($p->nama_rincian ?? ''),
                        'dokumen' => strtoupper((string) ($p->document_type ?? '')),
                        'tahun' => $p->tahun !== null ? (int) $p->tahun : null,
                    ];
                });
        } elseif ($metricType === 'sub_kegiatan') {
            $rkpdPrograms = \App\Models\SubKegiatan::query()
                ->select(['opd_id', 'document_type', 'kode_rek', 'nama_rincian', 'tahun'])
                ->whereIn('document_type', ['rkpd', 'renja'])
                ->get()
                ->map(function ($p) {
                    return [
                        'opd_id' => $p->opd_id,
                        'kode' => (string) ($p->kode_rek ?? ''),
                        'nama' => (string) ($p->nama_rincian ?? ''),
                        'dokumen' => strtoupper((string) ($p->document_type ?? '')),
                        'tahun' => $p->tahun !== null ? (int) $p->tahun : null,
                    ];
                });
        } else {
            $rkpdPrograms = Program::query()
                ->select(['opd_id', 'document_type', 'kode_rek', 'nama_rincian', 'tahun'])
                ->whereIn('document_type', ['rkpd', 'renja'])
                ->get()
                ->map(function ($p) {
                    return [
                        'opd_id' => $p->opd_id,
                        'kode' => (string) ($p->kode_rek ?? ''),
                        'nama' => (string) ($p->nama_rincian ?? ''),
                        'dokumen' => strtoupper((string) ($p->document_type ?? '')),
                        'tahun' => $p->tahun !== null ? (int) $p->tahun : null,
                    ];
                });
        }

        // Normalize RKPD/Renja program names: replace placeholder '(otomatis)' using references
        $rkpdPrograms = collect($rkpdPrograms)->map(function ($r) {
            $r['nama'] = $this->resolvePreferredProgramName($r['opd_id'] ?? null, $r['kode'] ?? '', $r['nama'] ?? '', $r['dokumen'] ?? '');
            return $r;
        });

        // Also include RENJA items stored in komponen_anggaran (used by RenjaController UI)
        try {
            $komponenRows = DB::table('komponen_anggaran')
                ->select(['opd_id', 'document_type', 'kode', DB::raw('nama_komponen as nama'), 'tahun', 'jenis'])
                ->where('document_type', 'renja')
                ->whereIn('jenis', ['program', 'kegiatan', 'sub_kegiatan'])
                ->get()
                ->map(function ($r) {
                    return [
                        'opd_id' => $r->opd_id,
                        'kode' => (string) ($r->kode ?? ''),
                        'nama' => (string) ($r->nama ?? ''),
                        'dokumen' => strtoupper((string) ($r->document_type ?? '')),
                        'tahun' => $r->tahun !== null ? (int) $r->tahun : null,
                        'jenis' => (string) ($r->jenis ?? ''),
                    ];
                });

            if ($komponenRows && count($komponenRows) > 0) {
                // Keep only komponen rows that match the requested metric type
                $filteredKomponen = collect($komponenRows)->filter(function ($item) use ($metricType) {
                    if ($metricType === 'kegiatan') return ($item['jenis'] ?? '') === 'kegiatan';
                    if ($metricType === 'sub_kegiatan') return ($item['jenis'] ?? '') === 'sub_kegiatan';
                    return ($item['jenis'] ?? '') === 'program';
                })->map(function ($item) {
                    // remove 'jenis' to match the shape of other program items
                    unset($item['jenis']);
                    return $item;
                })->values();

                $rkpdPrograms = collect($rkpdPrograms)->merge($filteredKomponen->all());
                // dedupe by opd_id|kode|dokumen|tahun
                $rkpdPrograms = $rkpdPrograms->unique(function ($item) {
                    return ($item['opd_id'] ?? '') . '|' . trim((string) ($item['kode'] ?? '')) . '|' . ($item['dokumen'] ?? '') . '|' . ($item['tahun'] ?? '');
                })->values();
            }
        } catch (\Throwable $e) {
            // if komponen_anggaran table missing or query fails, ignore and continue
        }

        // If no RKPD/Renja rows found in DB, try loading referensi files as a fallback
        if ($rkpdPrograms->isEmpty()) {
            $rkpdPrograms = $this->loadReferensiRkpdItems($metricType, $selectedYear);
        }

        // Build indikator query to collect per-komponen indicators/targets so we can attach targets
        // to RKPD/DPA items for kegiatan/sub_kegiatan/program metrics.
        try {
            $indikatorQuery = DB::table('indikator_anggaran as ia')
                ->join('komponen_anggaran as ka', 'ka.id', '=', 'ia.komponen_anggaran_id')
                ->select([
                    'ka.opd_id',
                    'ka.document_type',
                    'ka.kode as komponen_kode',
                    'ia.nama_indikator as indikator_uraian',
                    'ia.target_indikator as indikator_target',
                    'ka.tahun',
                    DB::raw("concat('anggaran_', ka.jenis) as indikator_jenis"),
                ])
                ->whereIn('ka.document_type', ['rkpd', 'renja', 'dpa']);

            // restrict komponen jenis to match requested metricType when possible
            if ($metricType === 'kegiatan') {
                $indikatorQuery->where('ka.jenis', 'kegiatan');
            } elseif ($metricType === 'sub_kegiatan') {
                $indikatorQuery->where('ka.jenis', 'sub_kegiatan');
            } else {
                $indikatorQuery->where('ka.jenis', 'program');
            }

            if ($selectedYear !== null) {
                $indikatorQuery->where(function ($sub) use ($selectedYear) {
                    $sub->where('ka.document_type', 'rkpd')
                        ->orWhere(function ($q2) use ($selectedYear) {
                            $q2->where('ka.document_type', 'renja')
                                ->where('ka.tahun', $selectedYear);
                        })
                        ->orWhere(function ($q3) use ($selectedYear) {
                            $q3->where('ka.document_type', 'dpa')
                                ->where('ka.tahun', $selectedYear);
                        });
                });
            }
        } catch (\Throwable $e) {
            // leave indikatorQuery undefined if the table doesn't exist or query fails
        }

        // If indikatorQuery wasn't built (due to earlier fallback), ensure we have an empty collection
        if (!isset($indikatorQuery)) {
            $indikatorRows = collect([]);
        } else {
            $indikatorRows = $indikatorQuery->get();
        }
        foreach ($indikatorRows as $ir) {
            $opdId = $ir->opd_id ?? '';
            $kode = trim((string) ($ir->program_kode ?? $ir->komponen_kode ?? ''));
            $dok = strtoupper((string) ($ir->document_type ?? ''));
            $tahun = $ir->tahun ?? '';
            $key = $opdId.'|'.$kode.'|'.$dok.'|'.$tahun;
            $nama = trim((string) ($ir->indikator_uraian ?? ''));
            if ($nama === '') continue;
            if (!isset($indikatorMap[$key])) $indikatorMap[$key] = [];
            // store as object with name and target so frontend can extract per-indicator targets
            $entry = ['nama_indikator' => $nama, 'target_indikator' => ($ir->indikator_target ?? null)];
            $exists = false;
            foreach ($indikatorMap[$key] as $e) {
                if (is_array($e) && ($e['nama_indikator'] ?? '') === $nama) { $exists = true; break; }
                if ($e === $nama) { $exists = true; break; }
            }
            if (! $exists) $indikatorMap[$key][] = $entry;
        }

        // APBD (DPA) items from komponen_anggaran; jenis varies by metricType
        // komponen_anggaran stores keys differently; use existing columns
        if ($metricType === 'kegiatan') {
            $dpaQuery = DB::table('komponen_anggaran')
                ->select(['opd_id', 'kode as kode', 'nama_komponen as nama', 'pagu', 'document_type', 'tahun'])
                ->where('document_type', 'dpa')
                ->where('jenis', 'kegiatan');
        } elseif ($metricType === 'sub_kegiatan') {
            $dpaQuery = DB::table('komponen_anggaran')
                ->select(['opd_id', 'kode as kode', 'nama_komponen as nama', 'pagu', 'document_type', 'tahun'])
                ->where('document_type', 'dpa')
                ->where('jenis', 'sub_kegiatan');
        } else {
            $dpaQuery = DB::table('komponen_anggaran')
                ->select(['opd_id', 'kode_program as kode', 'nama_komponen as nama', 'pagu', 'document_type', 'tahun'])
                ->where('document_type', 'dpa')
                ->where('jenis', 'program');
        }

        $dpaPrograms = $dpaQuery->get()->map(function ($r) {
            return [
                'opd_id' => $r->opd_id,
                'kode' => (string) ($r->kode ?? ''),
                'nama' => (string) ($r->nama ?? ''),
                'pagu' => (int) ($r->pagu ?? 0),
                'dokumen' => strtoupper((string) ($r->document_type ?? '')),
                'tahun' => $r->tahun !== null ? (int) $r->tahun : null,
            ];
        });

        // Normalize program names: prefer real names from komponen_anggaran / program / kegiatan
        $dpaPrograms = $dpaPrograms->map(function ($r) {
            $r['nama'] = $this->resolvePreferredProgramName($r['opd_id'] ?? null, $r['kode'] ?? '', $r['nama'] ?? '', $r['dokumen'] ?? '');
            return $r;
        })->all();

        if ($filterBasis === 'perangkat-daerah') {
            $opds = Opd::query()->select(['id', 'nama'])->get()->sortBy('nama')->values();

            $aggregates = [];

            foreach ($rkpdPrograms as $row) {
                if ($selectedYear !== null && isset($row['tahun']) && ($row['tahun'] ?? null) !== $selectedYear) {
                    continue;
                }

                $opdId = $row['opd_id'] ?? null;
                if (!isset($aggregates[$opdId])) {
                    $aggregates[$opdId] = [
                        'rkpd_programs' => [],
                        'dpa_programs' => [],
                        'rkpd_programs_keys' => [],
                        'dpa_programs_keys' => [],
                    ];
                }

                $key = trim($row['kode'] ?? '') . '|' . trim($row['nama'] ?? '') . '|' . ($row['dokumen'] ?? '') . '|' . ($row['tahun'] ?? '');
                if (!isset($aggregates[$opdId]['rkpd_programs_keys'][$key])) {
                    $aggregates[$opdId]['rkpd_programs_keys'][$key] = true;
                    $ikey = ($opdId ?? '').'|'.trim((string) ($row['kode'] ?? '')).'|'.trim((string) ($row['dokumen'] ?? '')).'|'.($row['tahun'] ?? '');
                    $r = $row;
                    $r['indikator'] = $indikatorMap[$ikey] ?? [];
                    $aggregates[$opdId]['rkpd_programs'][] = $r;
                }
            }

            foreach ($dpaPrograms as $row) {
                if ($selectedYear !== null && isset($row['tahun']) && ($row['tahun'] ?? null) !== $selectedYear) {
                    continue;
                }

                $opdId = $row['opd_id'] ?? null;
                if (!isset($aggregates[$opdId])) {
                    $aggregates[$opdId] = [
                        'rkpd_programs' => [],
                        'dpa_programs' => [],
                        'rkpd_programs_keys' => [],
                        'dpa_programs_keys' => [],
                    ];
                }

                $key = trim($row['kode'] ?? '') . '|' . trim($row['nama'] ?? '') . '|' . ($row['dokumen'] ?? '') . '|' . ($row['tahun'] ?? '');
                if (!isset($aggregates[$opdId]['dpa_programs_keys'][$key])) {
                    $aggregates[$opdId]['dpa_programs_keys'][$key] = true;
                    $ikey = ($opdId ?? '').'|'.trim((string) ($row['kode'] ?? '')).'|'.trim((string) ($row['dokumen'] ?? '')).'|'.($row['tahun'] ?? '');
                    $r = $row;
                    $r['indikator'] = $indikatorMap[$ikey] ?? [];
                    $aggregates[$opdId]['dpa_programs'][] = $r;
                }
            }

            return $opds->map(function (Opd $opd, int $index) use ($aggregates) {
                $counts = $aggregates[$opd->id] ?? ['rkpd_programs' => [], 'dpa_programs' => []];

                return [
                    'no' => $index + 1,
                    'entitas' => $opd->nama,
                    'rkpd_count' => count($counts['rkpd_programs']),
                    'dpa_count' => count($counts['dpa_programs']),
                    'rkpd_programs' => array_values($counts['rkpd_programs']),
                    'dpa_programs' => array_values($counts['dpa_programs']),
                ];
            })->values();
        }

        // by bidang urusan
        $bidangUrusans = BidangUrusan::query()
            ->select(['id', 'kode', 'nama'])
            ->get()
            ->unique(fn (BidangUrusan $bidang) => $bidang->kode.'|'.$bidang->nama)
            ->sortBy('kode')
            ->values();

        $opdBidangById = Opd::query()
            ->select(['id', 'kode'])
            ->get()
            ->mapWithKeys(fn (Opd $opd) => [
                $opd->id => $this->extractBidangKode((string) $opd->kode),
            ]);

        $aggregates = [];

        foreach ($rkpdPrograms as $row) {
            $bidangKode = $opdBidangById[$row['opd_id']] ?? null;
            if (!$bidangKode) continue;
            if ($selectedYear !== null && ($row['tahun'] ?? null) !== $selectedYear) continue;

            if (!isset($aggregates[$bidangKode])) {
                $aggregates[$bidangKode] = [
                    'rkpd_programs' => [],
                    'dpa_programs' => [],
                    'rkpd_programs_keys' => [],
                    'dpa_programs_keys' => [],
                ];
            }

            $key = trim($row['kode'] ?? '') . '|' . trim($row['nama'] ?? '') . '|' . ($row['dokumen'] ?? '') . '|' . ($row['tahun'] ?? '');
            if (!isset($aggregates[$bidangKode]['rkpd_programs_keys'][$key])) {
                $aggregates[$bidangKode]['rkpd_programs_keys'][$key] = true;
                $ikey = ($row['opd_id'] ?? '').'|'.trim((string) ($row['kode'] ?? '')).'|'.trim((string) ($row['dokumen'] ?? '')).'|'.($row['tahun'] ?? '');
                $r = $row;
                $r['indikator'] = $indikatorMap[$ikey] ?? [];
                $aggregates[$bidangKode]['rkpd_programs'][] = $r;
            }
        }

        foreach ($dpaPrograms as $row) {
            $bidangKode = $opdBidangById[$row['opd_id']] ?? null;
            if (!$bidangKode) continue;
            if ($selectedYear !== null && ($row['tahun'] ?? null) !== $selectedYear) continue;

            if (!isset($aggregates[$bidangKode])) {
                $aggregates[$bidangKode] = [
                    'rkpd_programs' => [],
                    'dpa_programs' => [],
                    'rkpd_programs_keys' => [],
                    'dpa_programs_keys' => [],
                ];
            }

            $key = trim($row['kode'] ?? '') . '|' . trim($row['nama'] ?? '') . '|' . ($row['dokumen'] ?? '') . '|' . ($row['tahun'] ?? '');
            if (!isset($aggregates[$bidangKode]['dpa_programs_keys'][$key])) {
                $aggregates[$bidangKode]['dpa_programs_keys'][$key] = true;
                $ikey = ($row['opd_id'] ?? '').'|'.trim((string) ($row['kode'] ?? '')).'|'.trim((string) ($row['dokumen'] ?? '')).'|'.($row['tahun'] ?? '');
                $r = $row;
                $r['indikator'] = $indikatorMap[$ikey] ?? [];
                $aggregates[$bidangKode]['dpa_programs'][] = $r;
            }
        }

        return $bidangUrusans->values()->map(function (BidangUrusan $bidang, int $index) use ($aggregates) {
            $counts = $aggregates[$bidang->kode] ?? ['rkpd_programs' => [], 'dpa_programs' => []];

            return [
                'no' => $index + 1,
                'entitas' => $bidang->nama,
                'rkpd_count' => count($counts['rkpd_programs']),
                'dpa_count' => count($counts['dpa_programs']),
                'rkpd_programs' => array_values($counts['rkpd_programs']),
                'dpa_programs' => array_values($counts['dpa_programs']),
            ];
        });
    }

    private function buildTabel1ExportRowsRkpdApbd(Collection $tableData, string $metricType = 'program'): array
    {
        // For indikator mode, the frontend/export expects grouped lines per entitas with indicators and status.
        if ($metricType === 'indikator') {
            // $tableData already contains 'lines' prepared by getKonsistensiRkpdApbdIndikator
            return $tableData->map(function ($row) {
                $rowData = is_array($row) ? $row : [];

                return [
                    'no' => (int) ($rowData['no'] ?? 0),
                    'entitas' => $this->formatResumeEntityLabel((string) ($rowData['entitas'] ?? '')),
                    'program_name' => $this->getRowProgramName($rowData),
                    'lines' => (array) ($rowData['lines'] ?? []),
                ];
            })->values()->all();
        }

        return $tableData->map(function ($row) {
            $rowData = is_array($row) ? $row : [];
            $rkpdPrograms = (array) ($rowData['rkpd_programs'] ?? []);
            $dpaPrograms = (array) ($rowData['dpa_programs'] ?? []);

            $same = $this->countSameComparableItems($rkpdPrograms, $dpaPrograms, in_array($metricType, ['kegiatan', 'sub_kegiatan'], true) ? 'program' : $metricType);
            $totalRkpd = count($rkpdPrograms);
            $totalDpa = count($dpaPrograms);

            return [
                'no' => (int) ($rowData['no'] ?? 0),
                'entitas' => $this->formatResumeEntityLabel((string) ($rowData['entitas'] ?? '')),
                'rkpd_total' => $totalRkpd,
                'dpa_total' => $totalDpa,
                'same_rkpd_dpa' => $same,
                'diff_rkpd_dpa' => max($totalRkpd - $same, 0),
            ];
        })->values()->all();
    }

    private function getRealisasiIku(): Collection
    {
        $ikus = \App\Models\Iku::query()->select(['id', 'indikator', 'satuan', 'capaian_2024', 'target_2025', 'target_2026'])->get();

        $rows = $ikus->map(function ($iku, $index) {
            $last = \App\Models\Realisasi::query()
                ->where('realisaseable_type', Iku::class)
                ->where('realisaseable_id', $iku->id)
                ->orderByDesc('tahun')
                ->first();

            return [
                'no' => $index + 1,
                'indikator' => (string) ($iku->indikator ?? ''),
                'satuan' => (string) ($iku->satuan ?? ''),
                'target_2026' => $iku->target_2026 ?? null,
                'realisasi_tahun' => $last ? (int) $last->tahun : null,
                'realisasi_fisik' => $last ? $last->realisasi_fisik : null,
                'realisasi_keuangan' => $last ? $last->realisasi_keuangan : null,
            ];
        });

        return $rows;
    }

    private function appendProgram(array &$aggregateBucket, string $listKey, Program $program): void
    {
        $item = $this->buildProgramItem($program);
        $itemKey = $item['kode'].'|'.$item['nama'].'|'.$item['dokumen'].'|'.($item['tahun'] ?? '');
        $itemMapKey = $listKey.'_keys';

        if (isset($aggregateBucket[$itemMapKey][$itemKey])) {
            return;
        }

        $aggregateBucket[$itemMapKey][$itemKey] = true;
        $aggregateBucket[$listKey][] = $item;
    }

    private function buildProgramItem(Program $program): array
    {
        return [
            'kode' => (string) ($program->kode_rek ?? '-'),
            'nama' => (string) ($program->nama_rincian ?? '-'),
            'opd_id' => $program->opd_id !== null ? (int) $program->opd_id : null,
            'pagu' => (int) ($program->effective_pagu ?? $program->pagu ?? 0),
            'dokumen' => strtoupper((string) ($program->document_type ?? '-')),
            'tahun' => $program->tahun !== null ? (int) $program->tahun : null,
        ];
    }

    private function appendIndikator(array &$aggregateBucket, string $listKey, object $indikatorRow): void
    {
        $item = $this->buildIndikatorItem($indikatorRow);
        $itemKey = $item['kode'].'|'.$item['nama'].'|'.$item['dokumen'];
        $itemMapKey = $listKey.'_keys';

        if (isset($aggregateBucket[$itemMapKey][$itemKey])) {
            return;
        }

        $aggregateBucket[$itemMapKey][$itemKey] = true;
        $aggregateBucket[$listKey][] = $item;
    }

    private function buildIndikatorItem(object $indikatorRow): array
    {
        $tahun = property_exists($indikatorRow, 'tahun') && $indikatorRow->tahun !== null
            ? (int) $indikatorRow->tahun
            : null;

        return [
            'kode' => (string) ($indikatorRow->indikator_id ?? '-'),
            'nama' => (string) ($indikatorRow->indikator_uraian ?? '-'),
            'program_kode' => (string) ($indikatorRow->program_kode ?? ''),
            'program_nama' => (string) ($indikatorRow->program_nama ?? ''),
            'target' => $indikatorRow->indikator_target ?? null,
            'dokumen' => strtoupper((string) ($indikatorRow->document_type ?? '-')),
            'tahun' => $tahun,
            'jenis' => (string) ($indikatorRow->indikator_jenis ?? '-'),
        ];
    }

    private function sanitizeFilterBasis(string $basis): string
    {
        return in_array($basis, ['bidang-urusan', 'perangkat-daerah'], true)
            ? $basis
            : 'perangkat-daerah';
    }

    private function getAvailableYears(): array
    {
        $years = Program::query()
            ->whereIn('document_type', ['rkpd', 'renja'])
            ->whereNotNull('tahun')
            ->pluck('tahun')
            ->map(fn ($year) => (int) $year)
            ->filter(fn (int $year) => $year > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (count($years) === 0) {
            return [(int) date('Y')];
        }

        return $years;
    }

    private function resolveSelectedYear(mixed $yearInput, array $availableYears): ?int
    {
        if ($yearInput !== null && $yearInput !== '' && is_numeric($yearInput)) {
            $year = (int) $yearInput;
            if (in_array($year, $availableYears, true)) {
                return $year;
            }
        }

        return count($availableYears) > 0 ? (int) end($availableYears) : null;
    }

    private function extractBidangKode(string $opdKode): ?string
    {
        $segments = array_values(array_filter(explode('.', $opdKode), fn ($segment) => $segment !== ''));
        if (count($segments) < 2) {
            return null;
        }

        return $segments[0].'.'.$segments[1];
    }

    /**
     * Bulk-fetch indikator_anggaran names for program-like items present in tableData
     * and attach them to each program entry under key 'indikator' when missing.
     * Accepts Collection or array and returns array for Inertia payload.
     */
    private function attachFallbackIndikatorsToTableData(mixed $tableData, ?int $selectedYear): array
    {
        // normalize to array of arrays
        $rows = [];
        if ($tableData instanceof \Illuminate\Support\Collection) {
            $rows = $tableData->map(fn($r) => is_array($r) ? $r : (is_object($r) ? json_decode(json_encode($r), true) : (array) $r))->all();
        } elseif (is_array($tableData)) {
            $rows = array_map(function ($r) {
                return is_array($r) ? $r : (is_object($r) ? json_decode(json_encode($r), true) : (array) $r);
            }, $tableData);
        } else {
            return [];
        }

        $keys = [];
        $opdIds = [];
        $kodes = [];
        $doks = [];
        $tahuns = [];

        // Load referensi indikator file (optional). Build map kode_uraian => [indikator,...]
        $referensiMap = [];
        try {
            $refPath = base_path('referensi/rkpd/indikator_fix.json');
            if (file_exists($refPath)) {
                $raw = @file_get_contents($refPath);
                $decoded = @json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $it) {
                        if (!empty($it['kode_uraian']) && !empty($it['indikator'])) {
                            $k = (string) $it['kode_uraian'];
                            $referensiMap[$k] ??= [];
                            $referensiMap[$k][] = trim((string) $it['indikator']);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug('resume.referensi_load_failed', ['error' => $e->getMessage()]);
        }

        foreach ($rows as $row) {
            foreach (['rkpd_programs', 'dpa_programs'] as $listKey) {
                $list = (array) ($row[$listKey] ?? []);
                foreach ($list as $p) {
                    if (!is_array($p)) continue;
                    $opdId = $p['opd_id'] ?? $row['opd_id'] ?? null;
                    $kode = trim((string) ($p['kode'] ?? $p['program_kode'] ?? ''));
                    $dok = strtoupper(trim((string) ($p['dokumen'] ?? '')));
                    $tahun = $p['tahun'] ?? $selectedYear ?? null;

                    if ($opdId === null || $kode === '') continue;

                    $key = $opdId.'|'.$kode.'|'.$dok.'|'.($tahun ?? '');
                    $keys[$key] = ['opd_id' => $opdId, 'kode' => $kode, 'dok' => $dok, 'tahun' => $tahun];
                    $opdIds[$opdId] = true;
                    $kodes[$kode] = true;
                    if ($dok !== '') $doks[$dok] = true;
                    if ($tahun !== null) $tahuns[$tahun] = true;
                }
            }
        }

        if (count($keys) === 0) {
            return $rows;
        }

        // Query indikator_anggaran for matching komponen_anggaran rows
        $query = DB::table('indikator_anggaran as ia')
            ->join('komponen_anggaran as ka', 'ka.id', '=', 'ia.komponen_anggaran_id')
            ->select([
                'ka.opd_id',
                'ka.kode_program',
                'ka.kode as komponen_kode',
                'ka.document_type',
                'ka.tahun',
                'ia.nama_indikator',
            ]);

        if (count($opdIds) > 0) $query->whereIn('ka.opd_id', array_keys($opdIds));
        if (count($kodes) > 0) $query->whereIn('ka.kode_program', array_keys($kodes));
        if (count($doks) > 0) $query->whereIn('ka.document_type', array_map('strtolower', array_keys($doks)));
        // Do not restrict tahun strictly to avoid missing matches; filter later

        $indikatorRows = $query->get();

        // Build map of OPD -> bidang kode to allow cross-OPD (same bidang) lookup
        $opdBidangById = Opd::query()
            ->select(['id', 'kode'])
            ->get()
            ->mapWithKeys(fn (Opd $o) => [$o->id => $this->extractBidangKode((string) $o->kode)])
            ->all();

        $map = [];
        foreach ($indikatorRows as $ir) {
            $opdId = $ir->opd_id ?? '';
            $kode = trim((string) ($ir->kode_program ?? $ir->komponen_kode ?? ''));
            $dok = strtoupper(trim((string) ($ir->document_type ?? '')));
            $tahun = $ir->tahun ?? '';
            $nama = trim((string) ($ir->nama_indikator ?? ''));
            if ($nama === '') continue;
            $k = $opdId.'|'.$kode.'|'.$dok.'|'.($tahun ?? '');
            $map[$k] ??= [];
            if (!in_array($nama, $map[$k], true)) $map[$k][] = $nama;
        }

        // Attach indikator arrays back to program entries when missing
        foreach ($rows as &$row) {
            foreach (['rkpd_programs', 'dpa_programs'] as $listKey) {
                if (!isset($row[$listKey]) || !is_array($row[$listKey])) continue;
                foreach ($row[$listKey] as &$p) {
                    if (!is_array($p)) continue;
                    if (!isset($p['indikator']) || !is_array($p['indikator']) || count($p['indikator']) === 0) {
                        $opdId = $p['opd_id'] ?? $row['opd_id'] ?? null;
                        $kode = trim((string) ($p['kode'] ?? $p['program_kode'] ?? ''));
                        $dok = strtoupper(trim((string) ($p['dokumen'] ?? '')));
                        $tahun = $p['tahun'] ?? $selectedYear ?? null;
                        if ($opdId === null || $kode === '') {
                            $p['indikator'] = [];
                            continue;
                        }

                        $k = $opdId.'|'.$kode.'|'.$dok.'|'.($tahun ?? '');
                        $alt = $map[$k] ?? [];

                        // try looser match by ignoring tahun
                        if (empty($alt)) {
                            foreach ($map as $mk => $vals) {
                                if (strpos($mk, $opdId.'|'.$kode.'|'.$dok.'|') === 0) {
                                    $alt = array_merge($alt, $vals);
                                }
                            }
                        }

                        // If still empty, try matching indikator from other OPD that belong to the same bidang/urusan
                        if (empty($alt)) {
                            $bidangKode = $opdBidangById[$opdId] ?? null;
                            if ($bidangKode) {
                                foreach ($opdBidangById as $otherOpdId => $otherBidang) {
                                    if ($otherOpdId == $opdId) continue;
                                    if ($otherBidang !== $bidangKode) continue;

                                    // exact match with other OPD
                                    $otherKey = $otherOpdId.'|'.$kode.'|'.$dok.'|'.($tahun ?? '');
                                    if (isset($map[$otherKey])) {
                                        $alt = array_merge($alt, $map[$otherKey]);
                                        continue;
                                    }

                                    // looser match ignoring year for other OPD
                                    foreach ($map as $mk2 => $vals2) {
                                        if (strpos($mk2, $otherOpdId.'|'.$kode.'|'.$dok.'|') === 0) {
                                            $alt = array_merge($alt, $vals2);
                                        }
                                    }
                                }
                            }
                        }

                        // If still empty, try to fetch from generic `indikator` table via program kode match
                        if (empty($alt)) {
                            $indRows = DB::table('indikator as i')
                                ->join('indikatorables as ia', function ($join) {
                                    $join->on('ia.indikator_id', '=', 'i.id')
                                         ->where('ia.indicatorable_type', '=', \App\Models\Program::class);
                                })
                                ->join('program as p', 'p.id', '=', 'ia.indicatorable_id')
                                ->where('p.kode_rek', $kode)
                                ->where(function($q) use ($opdId) {
                                    if ($opdId !== null) $q->where('p.opd_id', $opdId);
                                })
                                ->select('i.uraian')
                                ->get()
                                ->pluck('uraian')
                                ->filter()
                                ->unique()
                                ->values()
                                ->all();

                            if (!empty($indRows)) {
                                $alt = array_merge($alt, $indRows);
                            }
                        }

                        // If still empty, try referensi mapping file (referensi/rkpd/indikator_fix.json)
                        if (empty($alt) && !empty($referensiMap) && isset($referensiMap[$kode])) {
                            $alt = array_merge($alt, $referensiMap[$kode]);
                        }

                        $p['indikator'] = array_values(array_unique(array_filter($alt, fn($v) => trim((string) $v) !== '')));
                    }
                }
                unset($p);
            }
        }
        unset($row);

        return $rows;
    }
}
