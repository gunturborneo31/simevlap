<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Query Renja data
$renja = \App\Models\KomponenAnggaran::where('document_type', 'renja')
  ->where('opd_id', 20)
  ->where('tahun', 2026)
  ->whereNull('parent_id')
  ->select('id', 'jenis', 'kode', 'pagu', 'tahun')
  ->first();

echo "=== Renja Query for opd_id=20, tahun=2026 ===\n";
if ($renja) {
  echo "Found: Jenis=" . $renja->jenis . ", Kode=" . $renja->kode . ", Pagu=" . $renja->pagu . "\n";
} else {
  echo "No Renja data found\n";
}

// Check total Renja records
$totalRenja = \App\Models\KomponenAnggaran::where('document_type', 'renja')
  ->where('tahun', 2026)
  ->count();
echo "\nTotal Renja records for tahun 2026: $totalRenja\n";

// Check OPDs with Renja data
$opdsWithRenja = \App\Models\KomponenAnggaran::where('document_type', 'renja')
  ->where('tahun', 2026)
  ->distinct('opd_id')
  ->pluck('opd_id')
  ->toArray();
echo "OPDs with Renja data: " . implode(', ', $opdsWithRenja) . "\n";
?>
