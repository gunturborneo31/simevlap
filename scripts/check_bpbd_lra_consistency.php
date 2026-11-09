<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\Realisasi;
use App\Models\SubKegiatan;

$kodeOpd = '1.05.0.00.0.00.26.0000';
$targetKodeRekening = '5.2.02.05.002.00006';
$expectedLra = 5907402395.00;

$opd = Opd::query()->where('kode', $kodeOpd)->first();
if (!$opd) {
    echo "OPD tidak ditemukan untuk kode {$kodeOpd}\n";
    exit(1);
}

$dbTotal = (float) Realisasi::query()
    ->where('opd_id', $opd->id)
    ->where('tahun', 2026)
    ->where('triwulan', 2)
    ->where('document_type', 'dpa')
    ->where('realisaseable_type', SubKegiatan::class)
    ->sum('realisasi_keuangan');

$rows = json_decode((string) file_get_contents(__DIR__ . '/../referensi/realisasi/2026/tw_2.json'), true);
$sourceTotal = 0.0;
$rekeningVal = null;

foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    if (($row['kode_opd'] ?? '') !== $kodeOpd) {
        continue;
    }

    $sourceTotal += (float) ($row['realisasi'] ?? 0);

    if (($row['kode_rekening'] ?? '') === $targetKodeRekening) {
        $rekeningVal = (float) ($row['realisasi'] ?? 0);
    }
}

echo "BPBD LRA Consistency Check\n";
echo "- OPD: {$opd->nama}\n";
echo "- LRA expected total: {$expectedLra}\n";
echo "- Source tw_2.json total: {$sourceTotal}\n";
echo "- DB total: {$dbTotal}\n";
echo "- Selisih LRA vs Source: " . ($expectedLra - $sourceTotal) . "\n";
echo "- Selisih LRA vs DB: " . ($expectedLra - $dbTotal) . "\n";
echo "- Kode rekening {$targetKodeRekening} di source: " . ($rekeningVal === null ? 'NOT_FOUND' : $rekeningVal) . "\n";
