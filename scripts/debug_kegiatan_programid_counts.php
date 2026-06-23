<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tahun = 2026;
$triwulan = 1;

$rows = DB::table('realisasi')
    ->join('kegiatan as k', 'realisasi.realisaseable_id', '=', 'k.id')
    ->where('realisaseable_type', 'App\\Models\\Kegiatan')
    ->where('realisasi.tahun', $tahun)
    ->where('realisasi.triwulan', $triwulan)
    ->where('realisasi.document_type', 'dpa')
    ->select(DB::raw('SUM(CASE WHEN k.program_id IS NULL THEN 1 ELSE 0 END) as null_cnt'), DB::raw('SUM(CASE WHEN k.program_id IS NOT NULL THEN 1 ELSE 0 END) as not_null_cnt'))
    ->first();

echo var_export($rows, true) . "\n";
echo "done\n";
