<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('kegiatan')
    ->select('id','program_id','opd_id','kode_rek')
    ->limit(30)
    ->get();

foreach ($rows as $r) {
    echo "id={$r->id} program_id={$r->program_id} opd_id={$r->opd_id} kode_rek={$r->kode_rek}\n";
}

echo "done\n";
