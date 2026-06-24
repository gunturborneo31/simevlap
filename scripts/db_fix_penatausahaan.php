<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Indikator;

$patterns = [
    'PelaksanaanPenatausahaandan' => 'Pelaksanaan Penatausahaan dan',
    "PenatausahaandanPengujian" => 'Penatausahaan dan Pengujian',
    'Penatausahaandan' => 'Penatausahaan dan',
    'JumlahDokumenPenatausahaandanPengujian/VerifikasiKeuanganSKPD' => 'Jumlah Dokumen Penatausahaan dan Pengujian / Verifikasi Keuangan SKPD',
    'Pengujian/VerifikasiKeuanganSKPD' => 'Pengujian / Verifikasi Keuangan SKPD',
];

$rows = Indikator::where('uraian', 'like', '%Penata%')
    ->orWhere('indikator', 'like', '%Penata%')
    ->get();

$changed = 0;
foreach ($rows as $row) {
    $origU = $row->uraian;
    $origI = $row->indikator ?? '';
    $u = $origU;
    $i = $origI;
    foreach ($patterns as $from => $to) {
        $u = str_replace($from, $to, $u);
        $i = str_replace($from, $to, $i);
    }
    if ($u !== $origU || $i !== $origI) {
        $row->uraian = $u;
        $row->indikator = $i;
        $row->save();
        echo "Updated indikator id={$row->id}\n";
        $changed++;
    }
}

echo "Done. Rows changed: $changed\n";
