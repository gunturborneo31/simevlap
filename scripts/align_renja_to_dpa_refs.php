<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$threshold = $argv[1] ?? '85';
$apply = in_array('--apply', $argv);
$threshold = (int)$threshold;

echo "Running align_renja_to_dpa_refs (threshold={$threshold}%)" . ($apply?" [apply]":" [dry-run]") . "\n";

$dpaPath = base_path('referensi/apbd/indikator_fix.json');
$renjaPath = base_path('referensi/rkpd/indikator_fix.json');
if (!file_exists($dpaPath) || !file_exists($renjaPath)) {
    echo "Reference files missing.\n";
    exit(1);
}

$dpaRaw = file_get_contents($dpaPath);
$dpa = json_decode($dpaRaw, true);
if ($dpa === null) {
    echo "Failed to decode DPA JSON: " . json_last_error_msg() . "\n";
    exit(1);
}
$renjaRaw = file_get_contents($renjaPath);
$renja = json_decode($renjaRaw, true);
if ($renja === null) {
    echo "Failed to decode RENJA JSON: " . json_last_error_msg() . "\n";
    exit(1);
}

function normalize($s) {
    $s = (string)$s;
    $s = preg_replace('/[[:space:]]+/', ' ', $s);
    $s = trim($s);
    $s = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $s));
    $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

// index DPA by kode_unit
$dpaIndex = [];
foreach ($dpa as $row) {
    $kode = trim((string)($row['kode_unit'] ?? ''));
    $indikator = trim((string)($row['indikator'] ?? ''));
    if ($kode === '' || $indikator === '') continue;
    $dpaIndex[$kode][] = ['raw' => $indikator, 'norm' => normalize($indikator)];
}

$mappings = [];
foreach ($renja as $r) {
    $kode = trim((string)($r['kode_unit'] ?? ''));
    $renjaInd = trim((string)($r['indikator'] ?? ''));
    if ($kode === '' || $renjaInd === '' ) continue;
    $renjaNorm = normalize($renjaInd);

    $candidates = $dpaIndex[$kode] ?? [];
    if (empty($candidates)) {
        // fallback to any DPA
        $all = array_merge(...array_values($dpaIndex));
        $candidates = $all ?: [];
    }

    $best = null; $bestScore = 0;
    foreach ($candidates as $c) {
        similar_text($renjaNorm, $c['norm'], $percent);
        if ($percent > $bestScore) { $bestScore = $percent; $best = $c; }
    }

    if ($best && $bestScore >= $threshold) {
        if ($best['norm'] === $renjaNorm) continue; // already same
        $mappings[$renjaInd] = ['to' => $best['raw'], 'score' => $bestScore, 'kode' => $kode];
    }
}

echo "Proposed mappings: " . count($mappings) . "\n";
$totalChanged = 0;
foreach ($mappings as $from => $info) {
    $to = $info['to'];
    $score = $info['score'];
    $kode = $info['kode'];
    echo "Mapping ({$kode}) [{$score}%]: '{$from}' -> '{$to}'\n";
    if ($apply) {
        // update indikator_anggaran where exact match or where contains
        $count1 = DB::update("UPDATE indikator_anggaran SET nama_indikator = REPLACE(nama_indikator, ?, ?) WHERE nama_indikator LIKE ?", [$from, $to, '%' . $from . '%']);
        echo "  indikator_anggaran rows changed: {$count1}\n";
        $totalChanged += $count1;
        // update indikator table uraian too if exists exact
        $count2 = DB::update("UPDATE indikator SET uraian = REPLACE(uraian, ?, ?) WHERE uraian LIKE ?", [$from, $to, '%' . $from . '%']);
        echo "  indikator rows changed: {$count2}\n";
        $totalChanged += $count2;
    }
}

if ($apply) echo "Done. Total rows changed: {$totalChanged}\n";
else echo "Dry-run complete. No DB changes applied.\n";
