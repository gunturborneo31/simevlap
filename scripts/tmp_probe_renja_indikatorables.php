<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$base = DB::table('indikatorables as ia')
    ->join('indikator as i', 'i.id', '=', 'ia.indikator_id')
    ->where('i.document_type', 'renja');

echo 'renja_total=' . (clone $base)->count() . PHP_EOL;
echo 'renja_tw2=' . (clone $base)->where('ia.triwulan', 2)->count() . PHP_EOL;
echo 'renja_tw2_realisasi_notnull=' . (clone $base)->where('ia.triwulan', 2)->whereNotNull('ia.realisasi')->count() . PHP_EOL;
echo 'renja_tw2_realisasi_not_empty=' . (clone $base)->where('ia.triwulan', 2)->whereRaw("TRIM(COALESCE(CAST(ia.realisasi AS CHAR), '')) <> ''")->count() . PHP_EOL;
echo 'renja_tw2_target_notnull=' . (clone $base)->where('ia.triwulan', 2)->whereNotNull('ia.target')->count() . PHP_EOL;
echo 'renja_any_realisasi_notnull=' . (clone $base)->whereNotNull('ia.realisasi')->count() . PHP_EOL;
echo 'renja_any_target_notnull=' . (clone $base)->whereNotNull('ia.target')->count() . PHP_EOL;

$years = (clone $base)
    ->select('ia.tahun', DB::raw('count(*) as c'))
    ->groupBy('ia.tahun')
    ->orderByDesc('ia.tahun')
    ->get();

foreach ($years as $y) {
    echo 'tahun_' . $y->tahun . '=' . $y->c . PHP_EOL;
}

$sample = (clone $base)
    ->where('ia.triwulan', 2)
    ->orderByDesc('ia.updated_at')
    ->limit(15)
    ->get(['ia.id','ia.tahun','ia.triwulan','ia.target','ia.realisasi','i.uraian']);

echo 'sample_tw2=' . $sample->count() . PHP_EOL;
foreach ($sample as $r) {
    echo $r->id . '|' . $r->tahun . '|TW' . ($r->triwulan ?? 'null') . '|T=' . ($r->target ?? 'null') . '|R=' . ($r->realisasi ?? 'null') . '|' . $r->uraian . PHP_EOL;
}
