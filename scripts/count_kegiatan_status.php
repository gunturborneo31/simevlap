<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Opd;

$opdId = $argv[1] ?? 20;
$kodeSkpd = $argv[2] ?? '5.03.5.04.0.00.24.0000';
$year = isset($argv[3]) ? (int)$argv[3] : 2026;

$base = __DIR__ . '/../referensi/rkpd';
// count kegaiatan in referensi for kode
$kegFile = $base.'/kegaiatan.json';
$kegItems = [];
if (file_exists($kegFile)) {
    $kegItems = json_decode(file_get_contents($kegFile), true) ?? [];
}
$kegCount = 0;
foreach ($kegItems as $it) {
    if (($it['KODE_SKPD'] ?? null) === $kodeSkpd) $kegCount++;
}

// count sub_kegiatan
$subFile = $base.'/sub_kegiatan.json';
$subItems = [];
if (file_exists($subFile)) {
    $subItems = json_decode(file_get_contents($subFile), true) ?? [];
}
$subCount = 0;
foreach ($subItems as $it) {
    if (($it['KODE_SKPD'] ?? null) === $kodeSkpd) $subCount++;
}

// count komponen_anggaran DPA rows in DB for this opd and jenis kegiatan
try {
    $dpaCount = DB::table('komponen_anggaran')
        ->where('opd_id', $opdId)
        ->where('document_type', 'dpa')
        ->where('jenis', 'kegiatan')
        ->where(function($q) use ($year) {
            $q->whereNull('tahun')->orWhere('tahun', $year);
        })
        ->count();
} catch (\Throwable $e) {
    $dpaCount = 'ERROR: '.$e->getMessage();
}

echo "Referensi kegaiatan count for $kodeSkpd: $kegCount\n";
echo "Referensi sub_kegiatan count for $kodeSkpd: $subCount\n";
echo "DB komponen_anggaran (dpa, jenis=kegiatan) for opd_id $opdId year $year: $dpaCount\n";
