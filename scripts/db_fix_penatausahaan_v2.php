<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$replacements = [
    'Penatausahaandan' => 'Penatausahaan dan',
    'PenatausahaandanPengujian' => 'Penatausahaan dan Pengujian',
    'Penatausahaandan Pengujian' => 'Penatausahaan dan Pengujian',
    'JumlahDokumenPenatausahaandanPengujian/VerifikasiKeuanganSKPD' => 'Jumlah Dokumen Penatausahaan dan Pengujian / Verifikasi Keuangan SKPD',
    'JumlahDokumenPenatausahaandanPengujian\/VerifikasiKeuanganSKPD' => 'Jumlah Dokumen Penatausahaan and Pengujian / Verifikasi Keuangan SKPD',
    'Pengujian/VerifikasiKeuanganSKPD' => 'Pengujian / Verifikasi Keuangan SKPD',
    'Penatausahaandan Pengujian \/ Verifikasi Keuangan SKPD' => 'Penatausahaan dan Pengujian / Verifikasi Keuangan SKPD',
];

$totalUpdated = 0;

foreach ($replacements as $from => $to) {
    // indikator.uraian
    $sql = "UPDATE indikator SET uraian = REPLACE(uraian, ?, ?) WHERE uraian LIKE ?";
    $count = DB::update($sql, [$from, $to, "%" . str_replace('%','\%',$from) . "%"]);
    echo "indikator.uraian: Replaced '$from' -> '$to' ; rows: $count\n";
    $totalUpdated += $count;

    // indikator_anggaran.nama_indikator
    $sql2 = "UPDATE indikator_anggaran SET nama_indikator = REPLACE(nama_indikator, ?, ?) WHERE nama_indikator LIKE ?";
    $count2 = DB::update($sql2, [$from, $to, "%" . str_replace('%','\%',$from) . "%"]);
    echo "indikator_anggaran.nama_indikator: Replaced '$from' -> '$to' ; rows: $count2\n";
    $totalUpdated += $count2;
}

echo "Done. Total rows changed: $totalUpdated\n";
