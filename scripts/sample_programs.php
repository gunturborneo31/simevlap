<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('program')
    ->select('id','opd_id','kode_rek','nama_rincian')
    ->limit(50)
    ->get();

foreach ($rows as $r) {
    echo "id={$r->id} opd_id={$r->opd_id} kode_rek={$r->kode_rek} nama={$r->nama_rincian}\n";
}

echo "done\n";
