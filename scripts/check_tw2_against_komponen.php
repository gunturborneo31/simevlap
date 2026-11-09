<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KomponenAnggaran;

$path = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
if (!file_exists($path)) {
    echo "File not found: {$path}\n";
    exit(1);
}

$content = file_get_contents($path);
$rows = json_decode($content, true);
if (!is_array($rows)) {
    $rows = json_decode('[' . trim($content) . ']', true);
}
if (!is_array($rows)) {
    echo "Invalid JSON.\n";
    exit(1);
}

$total = count($rows);
$exactKomponenMatches = 0;

foreach ($rows as $row) {
    $kode = trim((string) ($row['kode_rekening'] ?? ''));
    $opdKode = trim((string) ($row['kode_skpd'] ?? ''));
    if ($kode === '' || $opdKode === '') {
        continue;
    }

    $found = KomponenAnggaran::query()
        ->where('kode', $kode)
        ->whereHas('opd', function ($q) use ($opdKode) {
            $q->where('kode', $opdKode);
        })
        ->exists();

    if ($found) {
        $exactKomponenMatches++;
    }
}

$jenisSummary = KomponenAnggaran::query()
    ->selectRaw('jenis, COUNT(*) as total')
    ->groupBy('jenis')
    ->orderBy('jenis')
    ->get();

echo "TW2 total rows: {$total}\n";
echo "Exact match in komponen_anggaran by (kode_rekening + opd kode): {$exactKomponenMatches}/{$total}\n";
echo "\nKomponen jenis summary:\n";
foreach ($jenisSummary as $item) {
    echo "- {$item->jenis}: {$item->total}\n";
}
