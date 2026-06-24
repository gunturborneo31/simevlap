<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$replacements = [
    'Dinamispada' => 'Dinamis pada',
    'Daerahpada' => 'Daerah pada',
    'Barang Milik Daerahpada' => 'Barang Milik Daerah pada',
    'Keuanganyang' => 'Keuangan yang',
    'Berkaitandengan' => 'Berkaitan dengan',
    'Penerimaandan' => 'Penerimaan dan',
    'PengeluaranKasserta' => 'Pengeluaran Kas serta',
    'Kasserta' => 'Kas serta',
    'PengeluaranKasserta' => 'Pengeluaran Kas serta',
    'PetunjukTeknisAdministrasiKeuanganyang' => 'Petunjuk Teknis Administrasi Keuangan yang',
    'Penatausahaandan' => 'Penatausahaan dan',
    'PenatausahaandanPengujian' => 'Penatausahaan dan Pengujian',
    'Arsip Dinamispada' => 'Arsip Dinamis pada',
    'Barang Milik Daerahpada SKPD' => 'Barang Milik Daerah pada SKPD',
];

totalChanged:;
$totalUpdated = 0;
foreach ($replacements as $from => $to) {
    // indikator.uraian
    $count = DB::update("UPDATE indikator SET uraian = REPLACE(uraian, ?, ?) WHERE uraian LIKE ?", [$from, $to, "%$from%"]);
    echo "indikator.uraian: Replaced '$from' -> '$to' ; rows: $count\n";
    $totalUpdated += $count;

    // indikator_anggaran.nama_indikator
    $count2 = DB::update("UPDATE indikator_anggaran SET nama_indikator = REPLACE(nama_indikator, ?, ?) WHERE nama_indikator LIKE ?", [$from, $to, "%$from%"]);
    echo "indikator_anggaran.nama_indikator: Replaced '$from' -> '$to' ; rows: $count2\n";
    $totalUpdated += $count2;
}

echo "Done. Total rows changed: $totalUpdated\n";
