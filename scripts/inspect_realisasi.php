<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Realisasi;
use Illuminate\Support\Facades\DB;

$rows = Realisasi::limit(10)->get();
foreach ($rows as $r) {
    echo "id={$r->id} tahun={$r->tahun} triwulan={$r->triwulan} realisaseable_type={$r->realisaseable_type} realisaseable_id={$r->realisaseable_id}\n";
    $type = $r->realisaseable_type;
    $id = $r->realisaseable_id;
    if ($type && $id) {
        $pivotCount = DB::table('indikatorables')->where('indicatorable_type', $type)->where('indicatorable_id', $id)->count();
        echo "  indikatorables_count={$pivotCount}\n";
    }
}
