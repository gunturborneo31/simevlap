<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = Illuminate\Support\Facades\DB::table('indikator_anggaran')->where('nama_indikator','like','%Penatausahaan%')->get();
foreach ($rows as $r) {
    echo $r->id . ' - ' . mb_substr($r->nama_indikator,0,200) . PHP_EOL;
}
