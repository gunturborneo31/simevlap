<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\SubKegiatan;

$kodeOpd = $argv[1] ?? '';
$tahun = isset($argv[2]) ? (int) $argv[2] : 2026;
$documentType = $argv[3] ?? 'dpa';

if ($kodeOpd === '') {
    echo "Usage: php scripts/list_opd_subkegiatan_master.php <kode_opd> [tahun] [document_type]\n";
    exit(1);
}

$opd = Opd::query()->where('kode', $kodeOpd)->first();
if (!$opd) {
    echo "OPD tidak ditemukan: {$kodeOpd}\n";
    exit(1);
}

$rows = SubKegiatan::query()
    ->where('opd_id', $opd->id)
    ->where('tahun', $tahun)
    ->where('document_type', $documentType)
    ->orderBy('kode_rek')
    ->get(['kode_rek', 'nama_rincian']);

echo "Sub-kegiatan master OPD {$opd->nama} ({$kodeOpd})\n";
foreach ($rows as $r) {
    echo "- {$r->kode_rek} | {$r->nama_rincian}\n";
}
