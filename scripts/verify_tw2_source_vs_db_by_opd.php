<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\Realisasi;
use App\Models\SubKegiatan;

$filePath = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
$tahun = 2026;
$triwulan = 2;
$documentType = 'dpa';

$rows = json_decode((string) file_get_contents($filePath), true);
if (!is_array($rows)) {
    echo "JSON tidak valid: {$filePath}\n";
    exit(1);
}

$sourceByOpdSub = [];
foreach ($rows as $r) {
    if (!is_array($r)) continue;
    $kodeOpd = trim((string) ($r['kode_opd'] ?? ''));
    $kodeSub = trim((string) ($r['kode_sub_kegiatan'] ?? ''));
    if ($kodeOpd === '' || $kodeSub === '') continue;
    $k = $kodeOpd . '|' . $kodeSub;
    $sourceByOpdSub[$k] = ($sourceByOpdSub[$k] ?? 0) + (float) ($r['realisasi'] ?? 0);
}

$sourceByOpd = [];
foreach ($sourceByOpdSub as $k => $v) {
    [$kodeOpd] = explode('|', $k, 2);
    $sourceByOpd[$kodeOpd] = ($sourceByOpd[$kodeOpd] ?? 0) + $v;
}

$opdByKode = Opd::query()->get()->keyBy('kode');

$report = [];
foreach ($sourceByOpd as $kodeOpd => $sourceTotal) {
    $opd = $opdByKode->get($kodeOpd);
    if (!$opd) {
        $report[] = [
            'kode_opd' => $kodeOpd,
            'nama_skpd' => '-',
            'source_total' => $sourceTotal,
            'db_total' => 0,
            'selisih' => $sourceTotal,
            'status' => 'opd_not_found',
        ];
        continue;
    }

    $subMapStrict = SubKegiatan::query()
        ->where('opd_id', $opd->id)
        ->where('document_type', $documentType)
        ->where('tahun', $tahun)
        ->pluck('id', 'kode_rek');

    $subMapAny = SubKegiatan::query()
        ->where('opd_id', $opd->id)
        ->pluck('id', 'kode_rek');

    $dbTotal = 0.0;
    foreach ($sourceByOpdSub as $k => $v) {
        [$kOpd, $kSub] = explode('|', $k, 2);
        if ($kOpd !== $kodeOpd) continue;
        $subId = $subMapStrict[$kSub] ?? ($subMapAny[$kSub] ?? null);
        if (!$subId) continue;

        $dbVal = Realisasi::query()
            ->where('opd_id', $opd->id)
            ->where('tahun', $tahun)
            ->where('triwulan', $triwulan)
            ->where('document_type', $documentType)
            ->where('realisaseable_type', SubKegiatan::class)
            ->where('realisaseable_id', $subId)
            ->value('realisasi_keuangan');

        $dbTotal += (float) ($dbVal ?? 0);
    }

    $selisih = $sourceTotal - $dbTotal;
    $status = abs($selisih) < 0.0001 ? 'match' : 'mismatch';

    $report[] = [
        'kode_opd' => $kodeOpd,
        'nama_skpd' => $opd->nama,
        'source_total' => $sourceTotal,
        'db_total' => $dbTotal,
        'selisih' => $selisih,
        'status' => $status,
    ];
}

usort($report, static function ($a, $b) {
    return strcmp($a['kode_opd'], $b['kode_opd']);
});

$match = count(array_filter($report, fn ($r) => $r['status'] === 'match'));
$mismatch = count(array_filter($report, fn ($r) => $r['status'] !== 'match'));

echo "Verifikasi TW2 source vs DB per OPD\n";
echo "- OPD match: {$match}\n";
echo "- OPD mismatch: {$mismatch}\n";

foreach ($report as $r) {
    if ($r['status'] === 'match') continue;
    echo "- {$r['kode_opd']} | {$r['nama_skpd']} | source={$r['source_total']} | db={$r['db_total']} | selisih={$r['selisih']}\n";
}
