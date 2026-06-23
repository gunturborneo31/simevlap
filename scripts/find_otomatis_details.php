<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$codeFilter = $argv[1] ?? null; // e.g. '1.05.01'

$tables = [
    ['table' => 'program', 'code' => 'kode_rek', 'name' => 'nama_rincian'],
    ['table' => 'kegiatan', 'code' => 'kode_rek', 'name' => 'nama_rincian'],
    ['table' => 'sub_kegiatan', 'code' => 'kode_rek', 'name' => 'nama_rincian'],
];

$results = [];
foreach ($tables as $t) {
    $q = DB::table($t['table'])->where($t['name'], 'like', '%(otomatis)%');
    if ($codeFilter) {
        $q->where($t['code'], 'like', "$codeFilter%");
    }
    $rows = $q->get();
    foreach ($rows as $r) {
        $results[] = ['table' => $t['table'], 'id' => $r->id, 'kode' => $r->{$t['code']}, 'name' => $r->{$t['name']}, 'opd_id' => $r->opd_id ?? null, 'tahun' => $r->tahun ?? null];
    }
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
