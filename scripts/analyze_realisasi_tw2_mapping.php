<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\SubKegiatan;

$path = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
if (!file_exists($path)) {
    echo "File not found: {$path}\n";
    exit(1);
}

$content = file_get_contents($path);
$rows = json_decode($content, true);

if (!is_array($rows)) {
    // File ini tampak berisi deretan object JSON yang belum dibungkus array.
    $wrapped = '[' . trim($content) . ']';
    $rows = json_decode($wrapped, true);
}

if (!is_array($rows)) {
    echo "Invalid JSON, unable to parse even after wrapping as array.\n";
    exit(1);
}

$total = count($rows);
$opdFound = 0;
$subFound = 0;
$subFoundByAnyOpd = 0;

$sampleMissingOpd = [];
$sampleMissingSub = [];

foreach ($rows as $row) {
    // Support old and new key naming from source files.
    $kodeSkpd = trim((string) ($row['kode_skpd'] ?? $row['kode_opd'] ?? ''));
    $kodeRekening = trim((string) ($row['kode_rekening'] ?? $row['kode_sub'] ?? ''));

    $opd = null;
    if ($kodeSkpd !== '') {
        $opd = Opd::where('kode', $kodeSkpd)->first();
    }

    if ($opd) {
        $opdFound++;
    } elseif (count($sampleMissingOpd) < 10) {
        $sampleMissingOpd[] = $kodeSkpd;
    }

    if ($kodeRekening !== '') {
        if ($opd) {
            $sub = SubKegiatan::where('opd_id', $opd->id)
                ->where('kode_rek', $kodeRekening)
                ->first();
            if ($sub) {
                $subFound++;
            } elseif (count($sampleMissingSub) < 10) {
                $sampleMissingSub[] = $kodeRekening . ' @ opd_id=' . $opd->id;
            }
        }

        $subAny = SubKegiatan::where('kode_rek', $kodeRekening)->first();
        if ($subAny) {
            $subFoundByAnyOpd++;
        }
    }
}

echo "Total rows: {$total}\n";
echo "OPD match by kode_skpd: {$opdFound}/{$total}\n";
echo "SubKegiatan match by (kode_rekening + opd): {$subFound}/{$total}\n";
echo "SubKegiatan match by kode_rekening only (any OPD): {$subFoundByAnyOpd}/{$total}\n";

if (!empty($sampleMissingOpd)) {
    echo "\nSample missing OPD codes:\n";
    foreach (array_unique($sampleMissingOpd) as $code) {
        echo "- {$code}\n";
    }
}

if (!empty($sampleMissingSub)) {
    echo "\nSample missing SubKegiatan codes:\n";
    foreach (array_unique($sampleMissingSub) as $code) {
        echo "- {$code}\n";
    }
}
