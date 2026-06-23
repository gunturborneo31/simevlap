<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tahun = 2026;
$documentType = 'dpa';
$triwulan = 1;

$rows = DB::table('realisasi')
    ->join('kegiatan as k', 'realisasi.realisaseable_id', '=', 'k.id')
    ->where('realisaseable_type', App\Models\Kegiatan::class)
    ->where('realisasi.tahun', $tahun)
    ->where('realisasi.triwulan', $triwulan)
    ->where('realisasi.document_type', $documentType)
    ->select('k.program_id', DB::raw('SUM(realisasi_keuangan) as total'), DB::raw('COUNT(*) as cnt'))
    ->groupBy('k.program_id')
    ->orderByDesc('total')
    ->limit(50)
    ->get();

foreach ($rows as $r) {
    echo "program_id={$r->program_id} total={$r->total} count_kegiatan={$r->cnt}\n";
}

echo "done\n";
