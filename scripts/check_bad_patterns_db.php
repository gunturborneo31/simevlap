<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$patterns = [
    'Penatausahaandan',
    'PenatausahaandanPengujian',
    'Dinamispada',
    'Orangyang',
    'SKPDdan',
    'SKPDdan',
    'Arsip Dinamispada',
    'Barang Milik Daerahpada',
    'Keuanganyang',
    'Berkaitandengan'
];

echo "Checking bad patterns in indikator and indikator_anggaran...\n";
foreach ($patterns as $p) {
    $like = '%' . $p . '%';
    $c1 = DB::table('indikator')->where('uraian', 'like', $like)->count();
    $c2 = DB::table('indikator_anggaran')->where('nama_indikator', 'like', $like)->count();
    printf("%s -> indikator: %d, indikator_anggaran: %d\n", $p, $c1, $c2);
}

// Also show total mappings applied earlier by reading from a temp file if exists
$mapFile = __DIR__ . '/align_renja_to_dpa_db_last_run.txt';
if (file_exists($mapFile)) {
    echo "\nLast run summary:\n" . file_get_contents($mapFile) . "\n";
}
