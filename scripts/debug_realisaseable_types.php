<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('realisasi')
    ->select('realisaseable_type', DB::raw('COUNT(*) as cnt'))
    ->groupBy('realisaseable_type')
    ->orderByDesc('cnt')
    ->get();

foreach ($rows as $r) {
    echo "type={$r->realisaseable_type} cnt={$r->cnt}\n";
}

echo "done\n";
