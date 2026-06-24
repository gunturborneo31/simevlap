<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate backend logic: check what's in grouped
$selectedYear = 2026;
$opdFilterIds = [20];

$dpaQuery = \App\Models\KomponenAnggaran::with(['indikator', 'children.indikator', 'children.children.indikator'])
  ->whereRaw('LOWER(document_type) = ?', ['dpa'])
  ->whereNull('parent_id');

if (!empty($opdFilterIds)) {
  $dpaQuery->whereIn('opd_id', $opdFilterIds);
}
if ($selectedYear) {
  $dpaQuery->where('tahun', $selectedYear);
}

$dpaRows = $dpaQuery->orderBy('kode')->get();

echo "=== DPA Root (program) level ===\n";
foreach ($dpaRows as $root) {
  echo "Program: {$root->kode} - Pagu: {$root->pagu}\n";
  echo "  Children (kegiatan):\n";
  foreach (($root->children ?? []) as $keg) {
    echo "    {$keg->kode} - Pagu: {$keg->pagu}\n";
    $subCount = count($keg->children ?? []);
    if ($subCount > 0) {
      echo "    Sub kegiatan: " . $subCount . " items (first: " . ($keg->children[0]->kode ?? 'N/A') . ")\n";
    }
  }
}

echo "\n=== Structure in grouped ===\n";
echo "Grouped will have 1 key per program (not per kegiatan/sub_kegiatan)\n";
echo "Key format: opd_id|kode\n";
echo "So for program 5.03.01: grouped[20|5.03.01] will contain dpa_programs array\n";
?>
