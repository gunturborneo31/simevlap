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

echo "=== renjaMap keys ===\n";
$count = 0;
foreach ($renjaMap as $k => $v) {
  if ($count < 20) {
    echo "$k => Pagu: {$v['pagu']}\n";
    $count++;
  }
}

// Now check kegiatan level
echo "\n=== Looking for kegiatan 5.03.01.2.01 ===\n";
$key1 = '5.03.01.2.01|20';
foreach (['program', 'kegiatan', 'sub_kegiatan'] as $jenis) {
  $k = implode('|', [$jenis, $key1]);
  $found = isset($renjaMap[$k]);
  echo "Try: $k => " . ($found ? $renjaMap[$k]['pagu'] : 'NOT FOUND') . "\n";
}

// Check DPA structure
echo "\n=== DPA program 5.03.01 full structure ===\n";
$dpa = \App\Models\KomponenAnggaran::with(['children.children.children'])
  ->whereRaw('LOWER(document_type) = ?', ['dpa'])
  ->where('tahun', $selectedYear)
  ->where('opd_id', 20)
  ->where('kode', '5.03.01')
  ->first();

echo "\n=== Check Renja OPD 20 for sub_kegiatan ===\n";
$renjaOPD20 = \App\Models\KomponenAnggaran::whereRaw('LOWER(document_type) = ?', ['renja'])
  ->where('tahun', $selectedYear)
  ->where('opd_id', 20)
  ->where('jenis', 'sub_kegiatan')
  ->limit(5)
  ->pluck('kode', 'pagu');
echo "Renja sub_kegiatan for OPD 20:\n";
foreach ($renjaOPD20 as $pagu => $kode) {
  echo "$kode => Pagu: $pagu\n";
}

// Check if Renja kegiatan match with DPA sub
echo "\n=== Why Renja kegiatan instead of sub_kegiatan? ===\n";
$renjaKeg = \App\Models\KomponenAnggaran::whereRaw('LOWER(document_type) = ?', ['renja'])
  ->where('tahun', $selectedYear)
  ->where('opd_id', 20)
  ->where('jenis', 'kegiatan')
  ->where('kode', '5.03.01.2.01')
  ->first();
echo "Renja kegiatan 5.03.01.2.01: " . ($renjaKeg ? $renjaKeg->pagu : 'NOT FOUND') . "\n";

$dpaSubKeg = \App\Models\KomponenAnggaran::whereRaw('LOWER(document_type) = ?', ['dpa'])
  ->where('tahun', $selectedYear)
  ->where('opd_id', 20)
  ->where('jenis', 'sub_kegiatan')
  ->where('kode', '5.03.01.2.01.0001')
  ->first();
echo "DPA sub_kegiatan 5.03.01.2.01.0001: " . ($dpaSubKeg ? $dpaSubKeg->pagu : 'NOT FOUND') . "\n";

// Answer: Renja might be at kegiatan level, but DPA at sub_kegiatan level
// So when we try to match 5.03.01.2.01.0001 (sub_kegiatan), renjaMap has kegiatan 5.03.01.2.01
?>
