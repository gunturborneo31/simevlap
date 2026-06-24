<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KomponenAnggaran;

// Test 1: Check if sub_kegiatan 5.03.01.2.01.0001 has renja data
$renja = KomponenAnggaran::where('document_type', 'renja')
  ->where('tahun', 2026)
  ->where('opd_id', 20)
  ->where('jenis', 'sub_kegiatan')
  ->where('kode', '5.03.01.2.01.0001')
  ->first();

echo "Renja sub_kegiatan 5.03.01.2.01.0001:\n";
if ($renja) {
  echo "  Found: Pagu={$renja->pagu}, Jenis={$renja->jenis}\n";
} else {
  echo "  NOT FOUND\n";
}

// Test 2: Check if backend rkpd_programs is populated
// Let's call resume controller endpoint directly

// Create a simulated request
$response = app('App\Http\Controllers\ResumeController')
  ->__invoke(new \Illuminate\Http\Request([
    'tahun' => 2026,
    'table' => 'tabel-7',
    'view' => 'hasil-pelaksanaan-rkpd',
    'opd_id' => 20,
  ]));

// Try to extract tableData from response
if (method_exists($response, 'getOriginalContent')) {
  $content = $response->getOriginalContent();
  if (isset($content['props']['tableData'])) {
    $tableData = $content['props']['tableData'];
    echo "\n=== Realisasi table entries ===\n";
    echo "Total rows: " . count($tableData) . "\n";
    
    // Find row with 5.03.01.2.01.0001
    foreach ($tableData as $row) {
      if (($row['kode_rek'] ?? null) === '5.03.01.2.01.0001') {
        echo "\nFound row for 5.03.01.2.01.0001:\n";
        echo "  rkpd_programs count: " . count($row['rkpd_programs'] ?? []) . "\n";
        if (count($row['rkpd_programs'] ?? []) > 0) {
          echo "  rkpd_programs[0].pagu: " . ($row['rkpd_programs'][0]['pagu'] ?? 'MISSING') . "\n";
        }
        break;
      }
    }
  }
}
?>
