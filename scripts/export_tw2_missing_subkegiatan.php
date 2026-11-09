<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\SubKegiatan;

$opts = getopt('', [
    'file::',
    'tahun::',
    'document_type::',
    'out-json::',
    'out-csv::',
]);

$filePath = $opts['file'] ?? (__DIR__ . '/../referensi/realisasi/2026/tw_2.json');
$tahun = isset($opts['tahun']) ? (int) $opts['tahun'] : 2026;
$documentType = (string) ($opts['document_type'] ?? 'dpa');
$outJson = $opts['out-json'] ?? (__DIR__ . '/../referensi/realisasi/2026/tw_2_missing_subkegiatan_rows.json');
$outCsv = $opts['out-csv'] ?? (__DIR__ . '/../referensi/realisasi/2026/tw_2_missing_subkegiatan_rows.csv');

if (!file_exists($filePath)) {
    echo "File tidak ditemukan: {$filePath}\n";
    exit(1);
}

$rows = json_decode((string) file_get_contents($filePath), true);
if (!is_array($rows)) {
    echo "Format JSON tidak valid: {$filePath}\n";
    exit(1);
}

$opdByKode = Opd::query()->get()->keyBy('kode');
$missingRows = [];

foreach ($rows as $idx => $row) {
    if (!is_array($row)) {
        continue;
    }

    $kodeOpd = trim((string) ($row['kode_opd'] ?? $row['kode_skpd'] ?? ''));
    $kodeSubKegiatan = trim((string) ($row['kode_sub_kegiatan'] ?? ''));
    $uraian = trim((string) ($row['uraian'] ?? ''));

    if ($kodeOpd === '' || $kodeSubKegiatan === '') {
        continue;
    }

    $opd = $opdByKode->get($kodeOpd);
    if (!$opd) {
        continue;
    }

    $sub = SubKegiatan::query()
        ->where('opd_id', $opd->id)
        ->where('kode_rek', $kodeSubKegiatan)
        ->where('document_type', $documentType)
        ->where('tahun', $tahun)
        ->first();

    if (!$sub) {
        $sub = SubKegiatan::query()
            ->where('opd_id', $opd->id)
            ->where('kode_rek', $kodeSubKegiatan)
            ->first();
    }

    if ($sub) {
        continue;
    }

    $missingRows[] = [
        'row_index' => $idx,
        'kode_opd' => $kodeOpd,
        'opd_id' => (int) $opd->id,
        'nama_skpd' => (string) ($row['nama_skpd'] ?? ''),
        'kode_sub_kegiatan' => $kodeSubKegiatan,
        'kode_rekening' => (string) ($row['kode_rekening'] ?? ''),
        'uraian' => $uraian,
        'anggaran_2026' => isset($row['anggaran_2026']) ? (float) $row['anggaran_2026'] : null,
        'realisasi' => isset($row['realisasi']) ? (float) $row['realisasi'] : null,
    ];
}

$summaryByOpd = [];
foreach ($missingRows as $item) {
    $key = $item['kode_opd'];
    if (!isset($summaryByOpd[$key])) {
        $summaryByOpd[$key] = [
            'kode_opd' => $item['kode_opd'],
            'opd_id' => $item['opd_id'],
            'nama_skpd' => $item['nama_skpd'],
            'count_missing' => 0,
        ];
    }
    $summaryByOpd[$key]['count_missing']++;
}

usort($missingRows, static function ($a, $b) {
    $ak = $a['kode_opd'] . '|' . str_pad((string) $a['row_index'], 8, '0', STR_PAD_LEFT);
    $bk = $b['kode_opd'] . '|' . str_pad((string) $b['row_index'], 8, '0', STR_PAD_LEFT);
    return $ak <=> $bk;
});

$summaryList = array_values($summaryByOpd);
usort($summaryList, static function ($a, $b) {
    if ($a['count_missing'] === $b['count_missing']) {
        return strcmp($a['kode_opd'], $b['kode_opd']);
    }
    return $b['count_missing'] <=> $a['count_missing'];
});

$payload = [
    'generated_at' => date('c'),
    'source_file' => $filePath,
    'tahun' => $tahun,
    'document_type' => $documentType,
    'missing_count' => count($missingRows),
    'summary_by_opd' => $summaryList,
    'rows' => $missingRows,
];

file_put_contents(
    $outJson,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

$fh = fopen($outCsv, 'w');
if ($fh === false) {
    echo "Gagal menulis CSV: {$outCsv}\n";
    exit(1);
}

fputcsv($fh, [
    'row_index',
    'kode_opd',
    'opd_id',
    'nama_skpd',
    'kode_sub_kegiatan',
    'kode_rekening',
    'uraian',
    'anggaran_2026',
    'realisasi',
], ',', '"', '\\');

foreach ($missingRows as $r) {
    fputcsv($fh, [
        $r['row_index'],
        $r['kode_opd'],
        $r['opd_id'],
        $r['nama_skpd'],
        $r['kode_sub_kegiatan'],
        $r['kode_rekening'],
        $r['uraian'],
        $r['anggaran_2026'],
        $r['realisasi'],
    ], ',', '"', '\\');
}

fclose($fh);

echo "Export missing sub kegiatan selesai.\n";
echo "- Missing rows: " . count($missingRows) . "\n";
echo "- JSON: {$outJson}\n";
echo "- CSV: {$outCsv}\n";
echo "- Ringkasan OPD (top 10):\n";

$top = array_slice($summaryList, 0, 10);
foreach ($top as $s) {
    echo "  - {$s['kode_opd']} | {$s['nama_skpd']} | {$s['count_missing']}\n";
}
