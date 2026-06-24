<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$from = 'Keuanganyang';
$to = 'Keuangan yang';

$count = DB::update("UPDATE indikator_anggaran SET nama_indikator = REPLACE(nama_indikator, ?, ?) WHERE nama_indikator LIKE ?", [$from, $to, '%'.$from.'%']);
echo "Replaced $count rows in indikator_anggaran\n";

$count2 = DB::update("UPDATE indikator SET uraian = REPLACE(uraian, ?, ?) WHERE uraian LIKE ?", [$from, $to, '%'.$from.'%']);
echo "Replaced $count2 rows in indikator.\n";
