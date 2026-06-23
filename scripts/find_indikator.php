<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$arg = $argv[1] ?? null;
$patterns = [];
if ($arg) {
    $patterns[] = '%' . $arg . '%';
} else {
    $patterns[] = '%Laporan Capaian Kinerja%';
    $patterns[] = '%Ikhtisar Realisasi Kinerja SKPD%';
    $patterns[] = '%Koordinasi dan Penyusunan Laporan Capaian Kinerja%';
}

$found = [];
foreach ($patterns as $p) {
    $rows = DB::table('indikator_anggaran')->select('id','nama_indikator','komponen_anggaran_id')->where('nama_indikator','like',$p)->get();
    foreach ($rows as $r) {
        $found['indikator_anggaran'][] = ['id'=>$r->id,'nama'=>$r->nama_indikator,'komponen_id'=>$r->komponen_anggaran_id];
    }

    $rows2 = DB::table('komponen_anggaran')->select('id','nama_komponen','opd_id','kode')->where('nama_komponen','like',$p)->get();
    foreach ($rows2 as $r) {
        $found['komponen_anggaran'][] = ['id'=>$r->id,'nama'=>$r->nama_komponen,'opd_id'=>$r->opd_id,'kode'=>$r->kode];
    }
}

echo "Search patterns:\n";
foreach ($patterns as $p) echo " - $p\n";

echo "\nResults:\n";
if (empty($found)) {
    echo "No matches found.\n";
    exit(0);
}

if (!empty($found['komponen_anggaran'])) {
    echo "komponen_anggaran matches:\n";
    foreach ($found['komponen_anggaran'] as $k) {
        echo "id={$k['id']} opd_id={$k['opd_id']} kode={$k['kode']} nama={$k['nama']}\n";
    }
}
if (!empty($found['indikator_anggaran'])) {
    echo "\nindikator_anggaran matches:\n";
    foreach ($found['indikator_anggaran'] as $k) {
        echo "id={$k['id']} komponen_id={$k['komponen_id']} nama={$k['nama']}\n";
    }
}

echo "\nDone.\n";
