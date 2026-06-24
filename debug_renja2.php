<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Query Renja kegiatan with pagu
$renjaKegiatan = \App\Models\KomponenAnggaran::where('document_type', 'renja')
  ->where('opd_id', 20)
  ->where('tahun', 2026)
  ->where('jenis', 'kegiatan')
  ->whereHas('parent', function($q) {
    $q->whereNull('parent_id');
  })
  ->select('id', 'jenis', 'kode', 'pagu', 'tahun')
  ->orderBy('kode')
  ->limit(5)
  ->get();

echo "=== Renja Kegiatan for opd_id=20, tahun=2026 ===\n";
foreach ($renjaKegiatan as $k) {
  echo "Kode={$k->kode}, Pagu={$k->pagu}\n";
}

// Check sub_kegiatan
$renjaSub = \App\Models\KomponenAnggaran::where('document_type', 'renja')
  ->where('opd_id', 20)
  ->where('tahun', 2026)
  ->where('jenis', 'sub_kegiatan')
  ->select('id', 'jenis', 'kode', 'pagu')
  ->orderBy('kode')
  ->limit(5)
  ->get();

echo "\n=== Renja Sub Kegiatan for opd_id=20, tahun=2026 ===\n";
foreach ($renjaSub as $s) {
  echo "Kode={$s->kode}, Pagu={$s->pagu}\n";
}

// Compare with DPA at same level
$dpaSub = \App\Models\KomponenAnggaran::where('document_type', 'dpa')
  ->where('opd_id', 20)
  ->where('tahun', 2026)
  ->where('jenis', 'sub_kegiatan')
  ->select('id', 'jenis', 'kode', 'pagu')
  ->orderBy('kode')
  ->limit(5)
  ->get();

echo "\n=== DPA Sub Kegiatan for opd_id=20, tahun=2026 (first 5) ===\n";
foreach ($dpaSub as $d) {
  echo "Kode={$d->kode}, Pagu={$d->pagu}\n";
}
?>
