<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\SubKegiatan;

function normalizeText(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    return trim($text);
}

function tokenSet(string $text): array
{
    $tokens = explode(' ', normalizeText($text));
    $tokens = array_filter($tokens, static function ($t) {
        if ($t === '') return false;
        if (strlen($t) < 3) return false;
        return !in_array($t, [
            'dan', 'yang', 'untuk', 'dengan', 'pada', 'dari', 'belanja', 'jasa', 'barang',
            'kegiatan', 'sub', 'modal', 'pegawai', 'honorarium', 'perjalanan', 'dinas',
        ], true);
    });
    return array_values(array_unique($tokens));
}

function jaccard(array $a, array $b): float
{
    if (empty($a) || empty($b)) return 0.0;
    $ai = array_values(array_unique($a));
    $bi = array_values(array_unique($b));
    $inter = array_intersect($ai, $bi);
    $union = array_unique(array_merge($ai, $bi));
    if (count($union) === 0) return 0.0;
    return count($inter) / count($union);
}

function scoreSimilarity(string $left, string $right): float
{
    $leftNorm = normalizeText($left);
    $rightNorm = normalizeText($right);

    if ($leftNorm === '' || $rightNorm === '') {
        return 0.0;
    }

    similar_text($leftNorm, $rightNorm, $pct);
    $jac = jaccard(tokenSet($leftNorm), tokenSet($rightNorm));

    // Kombinasi simple: 70% similar_text, 30% overlap token.
    return (0.7 * (float) $pct) + (0.3 * ($jac * 100));
}

$opts = getopt('', [
    'file::',
    'tahun::',
    'document_type::',
    'threshold::',
    'approve-threshold::',
    'limit::',
    'export-csv::',
    'export-json::',
    'apply-approved',
]);

$filePath = $opts['file'] ?? (__DIR__ . '/../referensi/realisasi/2026/tw_2.json');
$tahun = isset($opts['tahun']) ? (int) $opts['tahun'] : 2026;
$documentType = (string) ($opts['document_type'] ?? 'dpa');
$threshold = isset($opts['threshold']) ? (float) $opts['threshold'] : 35.0;
$approveThreshold = isset($opts['approve-threshold']) ? (float) $opts['approve-threshold'] : 55.0;
$limit = isset($opts['limit']) ? max(1, (int) $opts['limit']) : 30;
$exportCsvPath = (string) ($opts['export-csv'] ?? '');
$exportJsonPath = (string) ($opts['export-json'] ?? '');
$applyApproved = array_key_exists('apply-approved', $opts);

if (!file_exists($filePath)) {
    echo "File tidak ditemukan: {$filePath}\n";
    exit(1);
}

$rows = json_decode((string) file_get_contents($filePath), true);
if (!is_array($rows)) {
    echo "Format JSON tidak valid: {$filePath}\n";
    exit(1);
}

$opdByKode = Opd::query()->get()->keyBy('kode');
$subsByOpd = SubKegiatan::query()
    ->select(['id', 'opd_id', 'kode_rek', 'nama_rincian', 'document_type', 'tahun'])
    ->where('document_type', $documentType)
    ->where('tahun', $tahun)
    ->get()
    ->groupBy('opd_id');

$missing = [];

foreach ($rows as $idx => $row) {
    if (!is_array($row)) continue;

    $kodeOpd = trim((string) ($row['kode_opd'] ?? $row['kode_skpd'] ?? ''));
    $kodeSub = trim((string) ($row['kode_sub_kegiatan'] ?? ''));
    $uraian = trim((string) ($row['uraian'] ?? ''));

    if ($kodeOpd === '' || $kodeSub === '' || $uraian === '') continue;

    $opd = $opdByKode->get($kodeOpd);
    if (!$opd) continue;

    $existsByCode = SubKegiatan::query()
        ->where('opd_id', $opd->id)
        ->where('kode_rek', $kodeSub)
        ->where('document_type', $documentType)
        ->where('tahun', $tahun)
        ->exists();

    if ($existsByCode) continue;

    $candidates = $subsByOpd->get($opd->id, collect());
    $best = null;

    foreach ($candidates as $cand) {
        $candText = (string) ($cand->nama_rincian ?? '');
        if ($candText === '') continue;

        $score = scoreSimilarity($uraian, $candText);
        if ($best === null || $score > $best['score']) {
            $best = [
                'score' => $score,
                'sub_id' => (int) $cand->id,
                'kode_rek' => (string) $cand->kode_rek,
                'nama_rincian' => $candText,
            ];
        }
    }

    $missing[] = [
        'row_index' => $idx,
        'kode_opd' => $kodeOpd,
        'nama_skpd' => (string) ($row['nama_skpd'] ?? ''),
        'kode_sub_kegiatan' => $kodeSub,
        'uraian' => $uraian,
        'best' => $best,
    ];
}

$totalMissing = count($missing);
$similarCount = 0;
$noCandidateCount = 0;

foreach ($missing as $m) {
    if (!$m['best']) {
        $noCandidateCount++;
        continue;
    }
    if (($m['best']['score'] ?? 0) >= $threshold) {
        $similarCount++;
    }
}

usort($missing, static function ($a, $b) {
    $sa = $a['best']['score'] ?? -1;
    $sb = $b['best']['score'] ?? -1;
    return $sb <=> $sa;
});

if ($exportCsvPath === '') {
    $exportCsvPath = dirname($filePath) . '/tw_2_missing_sub_similarity.csv';
}
if ($exportJsonPath === '') {
    $exportJsonPath = dirname($filePath) . '/tw_2_missing_sub_similarity.json';
}

$reportRows = [];
foreach ($missing as $m) {
    $best = $m['best'];
    $score = $best['score'] ?? null;
    $reportRows[] = [
        'row_index' => $m['row_index'],
        'kode_opd' => $m['kode_opd'],
        'nama_skpd' => $m['nama_skpd'],
        'source_kode_sub_kegiatan' => $m['kode_sub_kegiatan'],
        'source_uraian' => $m['uraian'],
        'best_score' => $score,
        'best_sub_id' => $best['sub_id'] ?? null,
        'best_kode_rek' => $best['kode_rek'] ?? null,
        'best_nama_rincian' => $best['nama_rincian'] ?? null,
        'approved_auto_map' => ($score !== null && $score >= $approveThreshold),
    ];
}

$reportPayload = [
    'generated_at' => date('c'),
    'source_file' => $filePath,
    'tahun' => $tahun,
    'document_type' => $documentType,
    'similar_threshold' => $threshold,
    'approve_threshold' => $approveThreshold,
    'total_missing_by_kode' => $totalMissing,
    'similar_count' => $similarCount,
    'no_candidate_count' => $noCandidateCount,
    'rows' => $reportRows,
];

file_put_contents(
    $exportJsonPath,
    json_encode($reportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

$csvHandle = fopen($exportCsvPath, 'w');
if ($csvHandle !== false) {
    fputcsv($csvHandle, [
        'row_index',
        'kode_opd',
        'nama_skpd',
        'source_kode_sub_kegiatan',
        'source_uraian',
        'best_score',
        'best_sub_id',
        'best_kode_rek',
        'best_nama_rincian',
        'approved_auto_map',
    ], ',', '"', '\\');

    foreach ($reportRows as $r) {
        fputcsv($csvHandle, [
            $r['row_index'],
            $r['kode_opd'],
            $r['nama_skpd'],
            $r['source_kode_sub_kegiatan'],
            $r['source_uraian'],
            $r['best_score'] !== null ? number_format((float) $r['best_score'], 4, '.', '') : '',
            $r['best_sub_id'],
            $r['best_kode_rek'],
            $r['best_nama_rincian'],
            $r['approved_auto_map'] ? '1' : '0',
        ], ',', '"', '\\');
    }
    fclose($csvHandle);
}

$appliedCount = 0;
$appliedNoChange = 0;
$backupPath = null;
if ($applyApproved) {
    $backupPath = $filePath . '.bak_auto_similarity_' . date('YmdHis');
    file_put_contents($backupPath, (string) file_get_contents($filePath));

    foreach ($reportRows as $r) {
        if (!$r['approved_auto_map']) {
            continue;
        }

        $rowIndex = (int) $r['row_index'];
        if (!isset($rows[$rowIndex]) || !is_array($rows[$rowIndex])) {
            continue;
        }

        $newKode = trim((string) ($r['best_kode_rek'] ?? ''));
        if ($newKode === '') {
            continue;
        }

        $oldKode = trim((string) ($rows[$rowIndex]['kode_sub_kegiatan'] ?? ''));
        if ($oldKode === $newKode) {
            $appliedNoChange++;
            continue;
        }

        $rows[$rowIndex]['kode_sub_kegiatan'] = $newKode;
        $appliedCount++;
    }

    file_put_contents(
        $filePath,
        json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

echo "Audit kemiripan uraian untuk missing SubKegiatan\n";
echo "- File: {$filePath}\n";
echo "- Tahun: {$tahun}\n";
echo "- Document type: {$documentType}\n";
echo "- Total missing by kode: {$totalMissing}\n";
echo "- Ada kandidat mirip (score >= {$threshold}): {$similarCount}\n";
echo "- Tanpa kandidat teks: {$noCandidateCount}\n";
echo "- Auto-approve threshold: {$approveThreshold}\n";
echo "- Export JSON: {$exportJsonPath}\n";
echo "- Export CSV: {$exportCsvPath}\n";

if ($applyApproved) {
    echo "- Apply approved: ya\n";
    echo "- Backup file: {$backupPath}\n";
    echo "- Kode_sub_kegiatan diubah: {$appliedCount}\n";
    echo "- Approved tapi tidak berubah: {$appliedNoChange}\n";
} else {
    echo "- Apply approved: tidak (preview only)\n";
}

echo "\nTop {$limit} kandidat tertinggi:\n";
$printed = 0;
foreach ($missing as $m) {
    if ($printed >= $limit) break;

    $best = $m['best'];
    if (!$best) continue;

    $printed++;
    $score = number_format((float) $best['score'], 2, '.', '');
    echo "\n[{$printed}] row={$m['row_index']} opd={$m['kode_opd']}\n";
    echo "  source kode_sub: {$m['kode_sub_kegiatan']}\n";
    echo "  source uraian  : {$m['uraian']}\n";
    echo "  kandidat kode  : {$best['kode_rek']}\n";
    echo "  kandidat nama  : {$best['nama_rincian']}\n";
    echo "  score          : {$score}\n";
}
