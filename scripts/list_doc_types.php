<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = Illuminate\Support\Facades\DB::table('indikator')
    ->select('document_type', Illuminate\Support\Facades\DB::raw('count(*) as c'))
    ->groupBy('document_type')
    ->get();

foreach ($rows as $r) {
    echo ($r->document_type ?? 'NULL') . ' => ' . $r->c . PHP_EOL;
}
