<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$from = 'Keuanganyang';
$rows = DB::table('indikator_anggaran')
    ->select('id', 'nama_indikator', DB::raw('HEX(nama_indikator) as hex'))
    ->where('nama_indikator', 'like', '%'.$from.'%')
    ->get();

if ($rows->isEmpty()) {
    echo "No rows found.\n";
    exit;
}

foreach ($rows as $r) {
    echo "id={$r->id}\n";
    echo "text={$r->nama_indikator}\n";
    echo "hex={$r->hex}\n";
    echo "---\n";
}
