<?php

namespace App\Http\Controllers;

use App\Models\BidangUrusan;
use App\Models\Opd;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        if ($currentView !== '' && $currentTable !== '') {
            $tableData = null;
            $tableMetricType = 'program';

                if (in_array($currentTable, ['tabel-1', 'tabel-2', 'tabel-3', 'tabel-4'], true) && $currentView === 'konsistensi-rpjmd-rkpd') {
                    $tableMetricType = in_array($currentTable, ['tabel-2', 'tabel-3'], true) ? 'indikator' : 'program';
                $tableData = $this->getKonsistensiRpjmdRkpd($filterBasis, $selectedYear, $tableMetricType);
            }

            return Inertia::render('Resume/TableView', [
                'currentView' => $currentView,
                'currentTable' => $currentTable,
                'viewTitle' => $this->getViewTitle($currentView),
                'filterBasis' => $filterBasis,
                'selectedYear' => $selectedYear,
                'availableYears' => $availableYears,
                'tableMetricType' => $tableMetricType,
                'tableData' => $tableData,
            ]);
        }

        return Inertia::render('Resume/Index', [
            'currentView' => $currentView,
            'currentTable' => $currentTable,
        ]);
    }

    private function getViewTitle(string $view): string
    {
        return match ($view) {
            'konsistensi-rpjmd-rkpd' => 'Konsistensi RPJMD - RKPD',
            'konsistensi-rkpd-apbd' => 'Konsistensi RKPD - APBD',
            'hasil-pelaksanaan-rkpd' => 'Hasil Pelaksanaan RKPD',
            'rekap-permasalahan' => 'Rekap Permasalahan',
            'realisasi' => 'Realisasi',
            'kertas-kerja' => 'Kertas Kerja',
            default => 'Resume',
        };
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
            : 'bidang-urusan';
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
}
