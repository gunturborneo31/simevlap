<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Realisasi;
use App\Models\SubKegiatan;

$opdKode = '5.03.5.04.0.00.24.0000'; // BKPSDM
$tahun = 2026;
$triwulan = 2;
$documentType = 'dpa';
$filePath = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';

$rows = json_decode((string) file_get_contents($filePath), true);
if (!is_array($rows)) {
    echo "JSON tidak valid: {$filePath}\n";
    exit(1);
}

$sourceBySub = [];
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }

    if (($row['kode_opd'] ?? '') !== $opdKode) {
        continue;
    }

    $kodeSub = trim((string) ($row['kode_sub_kegiatan'] ?? ''));
    if ($kodeSub === '') {
        continue;
    }

    $sourceBySub[$kodeSub] = ($sourceBySub[$kodeSub] ?? 0) + (float) ($row['realisasi'] ?? 0);
}

ksort($sourceBySub);

$dbBySub = [];
$subs = SubKegiatan::query()
    ->where('opd_id', 20)
    ->where('document_type', $documentType)
    ->where('tahun', $tahun)
    ->pluck('id', 'kode_rek');

foreach ($subs as $kodeRek => $subId) {
    $value = Realisasi::query()
        ->where('opd_id', 20)
        ->where('tahun', $tahun)
        ->where('triwulan', $triwulan)
        ->where('document_type', $documentType)
        ->where('realisaseable_type', SubKegiatan::class)
        ->where('realisaseable_id', $subId)
        ->value('realisasi_keuangan');

    $dbBySub[(string) $kodeRek] = (float) ($value ?? 0);
}

$sourceTotal = array_sum($sourceBySub);
$dbTotalForSourceCodes = 0;

foreach ($sourceBySub as $kodeSub => $sourceVal) {
    $dbTotalForSourceCodes += (float) ($dbBySub[$kodeSub] ?? 0);
}

echo "BKPSDM CHECK TW2 2026\n";
echo "- Source total (tw_2.json): {$sourceTotal}\n";
echo "- DB total for same kode_sub_kegiatan: {$dbTotalForSourceCodes}\n";
echo "- Selisih: " . ($sourceTotal - $dbTotalForSourceCodes) . "\n";
echo "\nRincian beda per kode_sub_kegiatan:\n";

foreach ($sourceBySub as $kodeSub => $sourceVal) {
    $dbVal = (float) ($dbBySub[$kodeSub] ?? 0);
    if (abs($sourceVal - $dbVal) < 0.0001) {
        continue;
    }

    echo "- {$kodeSub} | source={$sourceVal} | db={$dbVal}\n";
}
