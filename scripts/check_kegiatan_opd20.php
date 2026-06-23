<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$opdId = 20;
$rows = DB::table('kegiatan')
    ->select('id','program_id','opd_id','kode_rek','nama_rincian','document_type','tahun')
    ->where('opd_id', $opdId)
    ->where('document_type', 'renja')
    ->where(function($q){ $q->whereNull('tahun')->orWhere('tahun',2026); })
    ->get();

if ($rows->isEmpty()) {
    echo "No renja kegiatan found for opd_id={$opdId}\n";
    exit(0);
}

foreach ($rows as $r) {
    echo json_encode([ 'id'=>$r->id, 'kode'=>$r->kode_rek, 'nama'=>$r->nama_rincian, 'tahun'=>$r->tahun, 'program_id'=>$r->program_id, 'document_type'=>$r->document_type ]) . "\n";
}

echo "count=".$rows->count()."\n";
