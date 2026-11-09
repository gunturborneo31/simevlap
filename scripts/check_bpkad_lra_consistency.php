<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\Realisasi;
use App\Models\SubKegiatan;

$kodeOpd = '5.02.4.01.0.00.23.0000';
$expected = [
    'pendapatan_daerah_4' => 386368383970.90,
    'pad_41' => 23132169648.90,
    'transfer_42' => 363236214322.00,
    'lain_lain_43' => 0.00,
    'belanja_5' => 26390261564.00,
    'pembiayaan_6' => 211996416400.89,
];

$expectedNeto = $expected['pendapatan_daerah_4'] + $expected['pembiayaan_6'] - $expected['belanja_5'];

$opd = Opd::query()->where('kode', $kodeOpd)->first();
if (!$opd) {
    echo "OPD tidak ditemukan untuk kode {$kodeOpd}\n";
    exit(1);
}

$rows = json_decode((string) file_get_contents(__DIR__ . '/../referensi/realisasi/2026/tw_2.json'), true);
if (!is_array($rows)) {
    echo "tw_2.json tidak valid\n";
    exit(1);
}

$src = [
    '4' => 0.0,
    '41' => 0.0,
    '42' => 0.0,
    '43' => 0.0,
    '5' => 0.0,
    '6' => 0.0,
];

$found621 = false;
$found621Val = 0.0;

foreach ($rows as $row) {
    if (!is_array($row) || ($row['kode_opd'] ?? '') !== $kodeOpd) {
        continue;
    }

    $kodeRek = (string) ($row['kode_rekening'] ?? '');
    $val = (float) ($row['realisasi'] ?? 0);

    if (str_starts_with($kodeRek, '4.')) {
        $src['4'] += $val;
    }
    if (str_starts_with($kodeRek, '4.1.')) {
        $src['41'] += $val;
    }
    if (str_starts_with($kodeRek, '4.2.')) {
        $src['42'] += $val;
    }
    if (str_starts_with($kodeRek, '4.3.')) {
        $src['43'] += $val;
    }
    if (str_starts_with($kodeRek, '5.')) {
        $src['5'] += $val;
    }
    if (str_starts_with($kodeRek, '6.')) {
        $src['6'] += $val;
    }

    if ($kodeRek === '6.1.01.07.001.00001') {
        $found621 = true;
        $found621Val += $val;
    }
}

$dbTotal = (float) Realisasi::query()
    ->where('opd_id', $opd->id)
    ->where('tahun', 2026)
    ->where('triwulan', 2)
    ->where('document_type', 'dpa')
    ->where('realisaseable_type', SubKegiatan::class)
    ->sum('realisasi_keuangan');

echo "Cek LRA BPKAD vs TW2\n";
echo "- OPD: {$opd->nama} ({$kodeOpd})\n";
echo "- Source sum 4.* (Pendapatan): {$src['4']} | LRA Pendapatan: {$expected['pendapatan_daerah_4']}\n";
echo "- Source sum 4.1.* (PAD): {$src['41']} | LRA PAD: {$expected['pad_41']}\n";
echo "- Source sum 4.2.* (Transfer): {$src['42']} | LRA Transfer: {$expected['transfer_42']}\n";
echo "- Source sum 4.3.* (Lain-lain): {$src['43']} | LRA 4.3: {$expected['lain_lain_43']}\n";
echo "- Source sum 5.* (Belanja): {$src['5']} | LRA Belanja (abs): {$expected['belanja_5']}\n";
echo "- Source sum 6.* (Pembiayaan): {$src['6']} | LRA Pembiayaan: {$expected['pembiayaan_6']}\n";
echo "- DB total terimport: {$dbTotal}\n";
echo "- Kode 6.1.01.07.001.00001 di source: " . ($found621 ? $found621Val : 'NOT_FOUND') . "\n";

$srcNeto = $src['4'] + $src['6'] - abs($src['5']);
echo "- Source total neto (4 + 6 - abs(5)): {$srcNeto} | LRA total neto: {$expectedNeto}\n";
