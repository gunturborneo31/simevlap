<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Opd;

$base = __DIR__ . '/../referensi/rkpd';
$files = ['program.json','kegaiatan.json','sub_kegiatan.json'];
$codes = [];
foreach ($files as $file) {
    $path = $base . '/' . $file;
    if (!file_exists($path)) continue;
    $items = json_decode(file_get_contents($path), true) ?? [];
    foreach ($items as $it) {
        $k = $it['KODE_SKPD'] ?? ($it['KODE_SUB_UNIT'] ?? null);
        if ($k) $codes[$k] = true;
    }
}

$codes = array_keys($codes);
echo "Total distinct KODE_SKPD in referensi: " . count($codes) . "\n";

foreach ($codes as $kode) {
    $opd = Opd::where('kode', $kode)->first();
    echo "KODE_SKPD: $kode -> ";
    if ($opd) {
        echo "FOUND (id={$opd->id}, nama={$opd->nama})\n";
    } else {
        echo "MISSING\n";
    }
}

// show opds count
try {
    $count = Opd::count();
    echo "\nTotal opds in DB: $count\n";
} catch (\Throwable $e) {
    echo "\nFailed to count opds: ".$e->getMessage()."\n";
}
