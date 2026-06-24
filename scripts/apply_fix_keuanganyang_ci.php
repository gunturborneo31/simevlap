<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$needle = 'keuanganyang';
$replacement = 'Keuangan yang';

$rows = DB::table('indikator_anggaran')
    ->select('id', 'nama_indikator')
    ->whereRaw('LOWER(nama_indikator) LIKE ?', ['%'.$needle.'%'])
    ->get();

$changed = 0;
foreach ($rows as $r) {
    $new = preg_replace('/keuanganyang/i', $replacement, $r->nama_indikator);
    if ($new !== $r->nama_indikator) {
        DB::table('indikator_anggaran')->where('id', $r->id)->update(['nama_indikator' => $new]);
        $changed++;
        echo "Updated indikator_anggaran id={$r->id}\n";
    }
}

$rows2 = DB::table('indikator')
    ->select('id', 'uraian')
    ->whereRaw('LOWER(uraian) LIKE ?', ['%'.$needle.'%'])
    ->get();

$changed2 = 0;
foreach ($rows2 as $r) {
    $new = preg_replace('/keuanganyang/i', $replacement, $r->uraian);
    if ($new !== $r->uraian) {
        DB::table('indikator')->where('id', $r->id)->update(['uraian' => $new]);
        $changed2++;
        echo "Updated indikator id={$r->id}\n";
    }
}

echo "Done. Changed indikator_anggaran: $changed, indikator: $changed2\n";
