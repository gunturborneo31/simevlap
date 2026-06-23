<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('realisasi')
    ->where('realisaseable_type', 'App\\Models\\Kegiatan')
    ->select('triwulan', 'document_type', DB::raw('COUNT(*) as cnt'))
    ->groupBy('triwulan', 'document_type')
    ->orderBy('triwulan')
    ->get();

foreach ($rows as $r) {
    echo "triwulan={$r->triwulan} doc={$r->document_type} cnt={$r->cnt}\n";
}

echo "done\n";
