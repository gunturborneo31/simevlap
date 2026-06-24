<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$selectedYear = 2026;
$opdFilterIds = [20];

// Build renjaMap same as backend
$renjaRows = \App\Models\KomponenAnggaran::with(['indikator', 'children.indikator', 'children.children.indikator'])
  ->whereRaw('LOWER(document_type) = ?', ['renja'])
  ->where('tahun', $selectedYear)
  ->orderBy('kode')
  ->get();

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
    'dokumen' => $r->dokumen ?? 'RENJA',
    'indikator' => $indikatorData,
  ];
}

// Direct lookup
echo "=== Direct renjaMap lookup ===\n";
$lookups = [
  'sub_kegiatan|5.03.01.2.01.0001|20',
  'sub_kegiatan|5.03.01.2.01.0002|20',
  'kegiatan|5.03.01.2.01|20',
  'kegiatan|5.03.01.2.02|20',
];

foreach ($lookups as $key) {
  if (isset($renjaMap[$key])) {
    echo "$key => Pagu: {$renjaMap[$key]['pagu']}\n";
  } else {
    echo "$key => NOT IN MAP\n";
  }
}

// Now simulate what backend does:
echo "\n=== Simulating backend DPA-to-Renja matching ===\n";

// Get DPA sub_kegiatan
$dpaSub = \App\Models\KomponenAnggaran::whereRaw('LOWER(document_type) = ?', ['dpa'])
  ->where('tahun', $selectedYear)
  ->where('opd_id', 20)
  ->where('jenis', 'sub_kegiatan')
  ->where('kode', '5.03.01.2.01.0001')
  ->first();

if ($dpaSub) {
  $dpaJenis = $dpaSub->jenis; // 'sub_kegiatan'
  $dpaKode = $dpaSub->kode; // '5.03.01.2.01.0001'
  $opdId = 20;
  
  echo "DPA Item: kode={$dpaKode}, jenis={$dpaJenis}\n";
  
  // Try to find renja match (as backend does)
  $renja_found = null;
  
  // Primary: try exact jenis match
  $rkKey = implode('|', [$dpaJenis, $dpaKode, $opdId]);
  echo "Try exact match: $rkKey => ";
  if (isset($renjaMap[$rkKey])) {
    $renja_found = $renjaMap[$rkKey];
    echo "FOUND {$renja_found['pagu']}\n";
  } else {
    echo "NOT FOUND\n";
    
    // Fallback: try other jenis
    foreach (['program', 'kegiatan', 'sub_kegiatan'] as $jenis) {
      if ($jenis === $dpaJenis) continue;
      $rkKey = implode('|', [$jenis, $dpaKode, $opdId]);
      echo "  Try fallback: $rkKey => ";
      if (isset($renjaMap[$rkKey])) {
        $renja_found = $renjaMap[$rkKey];
        echo "FOUND {$renja_found['pagu']}\n";
        break;
      } else {
        echo "NOT FOUND\n";
      }
    }
  }
  
  if ($renja_found) {
    echo "Result: Matched Renja pagu={$renja_found['pagu']}\n";
  } else {
    echo "Result: No match - will show 0\n";
  }
}
?>
