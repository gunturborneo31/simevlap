<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KomponenAnggaran;

$selectedYear = 2026;
$opdFilterIds = [20];
$currentView = 'hasil-pelaksanaan-rkpd';
$currentTable = 'tabel-7';

// Check: Are we in the right conditional?
echo "Current view/table: $currentView / $currentTable\n";
echo "Match conditional: " . ($currentView === 'hasil-pelaksanaan-rkpd' && $currentTable === 'tabel-7' ? 'YES' : 'NO') . "\n\n";

// Build renjaMap exactly like backend
echo "=== Building renjaMap ===\n";

$renjaQuery = KomponenAnggaran::with(['indikator', 'children.indikator', 'children.children.indikator'])
  ->whereRaw('LOWER(document_type) = ?', ['renja'])
  ->where('tahun', $selectedYear);

echo "Renja query (before filters): " . $renjaQuery->count() . " records\n";

// The renjaQuery is NOT filtered by opd_id in backend
// It loads all renja records for the year
// But only specific ones are used based on dpa structure

$renjaRows = $renjaQuery->orderBy('kode')->get();
echo "Renja rows loaded: " . count($renjaRows) . "\n";

$renjaMap = [];
foreach ($renjaRows as $r) {
  $jenis = $r->jenis ?? 'program';
  $kode = $r->kode ?? '';
  $opd_id = $r->opd_id ?? '';
  
  $indikatorData = [];
  foreach (($r->indikator ?? []) as $ind) {
    $indikatorData[] = [
      'nama_indikator' => $ind->nama_indikator ?? $ind->nama ?? '',
      'target_indikator' => $ind->target_indikator ?? $ind->target ?? null,
      'satuan' => $ind->satuan ?? null,
    ];
  }
  
  $renjaMapKey = implode('|', [$jenis, $kode, $opd_id]);
  $renjaMap[$renjaMapKey] = [
    'pagu' => $r->pagu ?? 0,
    'pagu_tahunan' => $r->pagu_tahunan ?? null,
    'dokumen' => strtoupper((string) ($r->document_type ?? 'RENJA')),
    'indikator' => $indikatorData,
  ];
}

echo "renjaMap entries: " . count($renjaMap) . "\n";

// Check specific keys for OPD 20
echo "\nLooking for entries with opd_id=20 in renjaMap:\n";
$count = 0;
foreach ($renjaMap as $k => $v) {
  if (str_contains($k, '|20')) {
    echo "  $k => " . ($v['pagu'] ?? 0) . "\n";
    $count++;
    if ($count >= 10) break;
  }
}

// Test matching for kegiatan
echo "\n=== Test: Can we find sub_kegiatan 5.03.01.2.01.0001 for OPD 20? ===\n";
$testKey = 'sub_kegiatan|5.03.01.2.01.0001|20';
echo "Looking for: $testKey\n";
if (isset($renjaMap[$testKey])) {
  echo "FOUND: Pagu=" . $renjaMap[$testKey]['pagu'] . "\n";
} else {
  echo "NOT FOUND\n";
  
  // Check if kegiatan level exists instead
  $kegKey = 'kegiatan|5.03.01.2.01|20';
  echo "\nFallback: Looking for kegiatan $kegKey\n";
  if (isset($renjaMap[$kegKey])) {
    echo "FOUND: Pagu=" . $renjaMap[$kegKey]['pagu'] . "\n";
  } else {
    echo "NOT FOUND\n";
  }
}
?>
