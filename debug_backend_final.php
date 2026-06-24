<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate ResumeController logic for tabel-7
use App\Models\KomponenAnggaran;

$selectedYear = 2026;
$opdFilterIds = [20];

// Build renjaMap
$renjaRows = KomponenAnggaran::with(['indikator', 'children.indikator', 'children.children.indikator'])
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

// Get grouped like backend
$dpaRows = KomponenAnggaran::with(['indikator', 'children.indikator', 'children.children.indikator'])
  ->whereRaw('LOWER(document_type) = ?', ['dpa'])
  ->whereNull('parent_id');

if (!empty($opdFilterIds)) {
  $dpaRows->whereIn('opd_id', $opdFilterIds);
}
if ($selectedYear) {
  $dpaRows->where('tahun', $selectedYear);
}

$dpaRows = $dpaRows->orderBy('kode')->get();

// Build grouped manually
$grouped = [];

$addNodeAsDpaProgram = function ($node, $level = 'program') use (&$grouped, $selectedYear) {
  $kode = trim((string) ($node->kode ?? $node->kode_program ?? ''));
  $opd_id = (int) ($node->opd_id ?? 0);
  $key = $opd_id.'|'.$kode;
  if (!isset($grouped[$key])) {
    $grouped[$key] = [
      'kode_rek' => $kode,
      'program_nama' => $node->nama_komponen ?? $node->nama ?? null,
      'opd_id' => $opd_id,
      'dpa_programs' => [],
      'rkpd_programs' => [],
    ];
  }

  $programName = $node->nama_komponen ?? $node->nama ?? null;
  $cleanKode = trim((string) $kode);
  $cleanName = $programName ? preg_replace('/\s+/', ' ', trim((string) $programName)) : '';
  $clientKey = strtoupper($cleanKode) . '|' . strtoupper($cleanName);

  $grouped[$key]['dpa_programs'][] = [
    'kode' => $kode,
    'nama' => $programName,
    'client_key' => $clientKey,
    'opd_id' => $opd_id,
    'tahun' => $node->tahun ?? $selectedYear,
    'pagu' => (int) ($node->pagu ?? 0),
    'pagu_tahunan' => $node->pagu_tahunan ?? null,
    'dokumen' => strtoupper((string) ($node->document_type ?? 'DPA')),
    'indikator' => [],
    'program_nama' => $programName,
    'level' => $level,
  ];
};

foreach ($dpaRows as $root) {
  $addNodeAsDpaProgram($root, 'program');
  foreach (($root->children ?? []) as $keg) {
    $addNodeAsDpaProgram($keg, 'kegiatan');
    foreach (($keg->children ?? []) as $sub) {
      $addNodeAsDpaProgram($sub, 'sub');
    }
  }
}

echo "=== All grouped keys for OPD 20 ===\n";
$groupedFor20 = array_filter($grouped, fn($g) => ($g['opd_id'] ?? null) === 20);
$keys = array_keys($groupedFor20);
echo "Total keys for OPD 20: " . count($keys) . "\n";
echo "First 10 keys:\n";
foreach (array_slice($keys, 0, 10) as $k) {
  $g = $grouped[$k];
  echo "  $k: kode_rek={$g['kode_rek']}, dpa_count=" . count($g['dpa_programs']) . "\n";
}

echo "\n=== Testing rkpd_programs attachment for program 5.03.01 ===\n";
$groupKey = '20|5.03.01';
if (isset($grouped[$groupKey])) {
  $g = &$grouped[$groupKey];
  echo "Group: $groupKey\n";
  echo "dpa_programs: " . count($g['dpa_programs']) . " items\n";
  
  // Now simulate attachment logic
  $opd_id = $g['opd_id'];
  echo "Processing attachment for opd_id=$opd_id\n";
  
  $matched = 0;
  foreach (($g['dpa_programs'] ?? []) as $idx => $dpaItem) {
    $dpaKode = $dpaItem['kode'] ?? null;
    if (!$dpaKode) continue;
    
    $dpaLevel = $dpaItem['level'] ?? 'program';
    $dpaJenis = match($dpaLevel) {
      'kegiatan' => 'kegiatan',
      'sub' => 'sub_kegiatan',
      default => 'program',
    };
    
    $rkKey = implode('|', [$dpaJenis, $dpaKode, $opd_id]);
    $found_in_map = isset($renjaMap[$rkKey]);
    
    if ($found_in_map) {
      $matched++;
    }
    
    if ($idx < 5) { // Show first 5
      $status = $found_in_map ? 'FOUND' : 'NOT FOUND';
      echo "  [{$idx}] {$dpaKode} (level={$dpaLevel} => jenis={$dpaJenis}): Key=$rkKey => $status\n";
    }
  }
  
  echo "Total matched: $matched / " . count($g['dpa_programs']) . "\n";
}
?>
