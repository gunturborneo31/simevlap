<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo 'indikatorables_nonnull_all=' . DB::table('indikatorables')->whereNotNull('realisasi')->count() . PHP_EOL;
echo 'indikatorables_tw2_nonnull=' . DB::table('indikatorables')->where('triwulan', 2)->whereNotNull('realisasi')->count() . PHP_EOL;
echo 'realisasi_nonnull_all=' . DB::table('realisasi')->whereNotNull('realisasi_fisik')->count() . PHP_EOL;
echo 'realisasi_tw2_all=' . DB::table('realisasi')->where('triwulan', 2)->count() . PHP_EOL;
echo 'realisasi_tw2_renja=' . DB::table('realisasi')->where('triwulan', 2)->where('document_type', 'renja')->count() . PHP_EOL;

$rows = DB::table('indikatorables as ia')
    ->join('indikator as i', 'i.id', '=', 'ia.indikator_id')
    ->where('ia.triwulan', 2)
    ->whereNotNull('ia.realisasi')
    ->select('ia.id', 'ia.tahun', 'ia.triwulan', 'ia.realisasi', 'i.document_type', 'i.jenis_indikator', 'i.uraian')
    ->limit(10)
    ->get();

echo 'sample_indikatorables_tw2=' . $rows->count() . PHP_EOL;
foreach ($rows as $r) {
    echo $r->id . '|' . $r->tahun . '|TW' . $r->triwulan . '|' . ($r->document_type ?? 'NULL') . '|' . ($r->jenis_indikator ?? 'NULL') . '|' . $r->realisasi . '|' . $r->uraian . PHP_EOL;
}
