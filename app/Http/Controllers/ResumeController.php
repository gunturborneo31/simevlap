<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\BidangUrusan;
use App\Models\Dokumen;
use App\Models\Opd;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            $tableMetricType = $this->resolveTableMetricType($currentView, $currentTable);

            if (in_array($currentTable, ['tabel-1', 'tabel-2', 'tabel-3', 'tabel-4'], true) && $currentView === 'konsistensi-rpjmd-rkpd') {
                $tableData = $this->getKonsistensiRpjmdRkpd($filterBasis, $selectedYear, $tableMetricType);
            }

            if ($currentView === 'dokumen' && $currentTable === 'monitoring') {
                $tableData = $this->getDokumenMonitoring();
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

    public function export(Request $request)
    {
        $currentView = $request->string('view')->toString();
        $currentTable = $request->string('table')->toString();

        if (!($currentView === 'konsistensi-rpjmd-rkpd' && in_array($currentTable, ['tabel-1', 'tabel-2', 'tabel-3', 'tabel-4'], true))) {
            abort(404);
        }

        $filterBasis = $this->sanitizeFilterBasis($request->string('basis')->toString());
        $availableYears = $this->getAvailableYears();
        $selectedYear = $this->resolveSelectedYear($request->query('year'), $availableYears);
        $tableMetricType = $this->resolveTableMetricType($currentView, $currentTable);
        $tableData = $this->getKonsistensiRpjmdRkpd($filterBasis, $selectedYear, $tableMetricType);

        $basePayload = [
            'viewTitle' => $this->getViewTitle($currentView),
            'tableLabel' => $this->formatCurrentTableLabel($currentTable),
            'entityHeaderLabel' => $filterBasis === 'perangkat-daerah' ? 'Perangkat Daerah' : 'Bidang Urusan',
            'metricLabel' => $tableMetricType === 'indikator' ? 'Indikator Program' : 'Program',
            'selectedYear' => $selectedYear,
        ];

        $template = 'exports.resume_tabel_1';
        $payload = $basePayload + [
            'rows' => $this->buildTabel1ExportRows($tableData, $tableMetricType),
        ];

        if ($currentTable === 'tabel-3') {
            $template = 'exports.resume_tabel_3';
            $payload = $basePayload + [
                'groups' => $this->buildTabel3ExportGroups($tableData),
            ];
        }

        if ($currentTable === 'tabel-4') {
            $template = 'exports.resume_tabel_4';
            $payload = $basePayload + [
                'groups' => $this->buildTabel4ExportGroups($tableData),
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

    private function getAlignedAnggaranRows(array $row): array
    {
        $metricType = 'program';
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

    private function buildTabel4ExportGroups(Collection $tableData): array
    {
        return $tableData->map(function ($row) {
            $rowData = is_array($row) ? $row : [];

            return [
                'no' => (int) ($rowData['no'] ?? 0),
                'entitas' => $this->formatResumeEntityLabel((string) ($rowData['entitas'] ?? '')),
                'lines' => $this->getAlignedAnggaranRows($rowData),
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
