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
$outJson = $opts['out-json'] ?? (__DIR__ . '/../referensi/realisasi/2026/tw_2_missing_subkegiatan_grouped.json');
$outCsv = $opts['out-csv'] ?? (__DIR__ . '/../referensi/realisasi/2026/tw_2_missing_subkegiatan_grouped.csv');

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
$missingGrouped = [];

foreach ($rows as $idx => $row) {
    if (!is_array($row)) {
        continue;
    }

    $kodeOpd = trim((string) ($row['kode_opd'] ?? $row['kode_skpd'] ?? ''));
    $kodeSubKegiatan = trim((string) ($row['kode_sub_kegiatan'] ?? ''));
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

    $key = $kodeOpd . '|' . $kodeSubKegiatan;
    if (!isset($missingGrouped[$key])) {
        $missingGrouped[$key] = [
            'kode_opd' => $kodeOpd,
            'opd_id' => (int) $opd->id,
            'nama_skpd' => (string) ($row['nama_skpd'] ?? $opd->nama ?? ''),
            'kode_sub_kegiatan' => $kodeSubKegiatan,
            'row_count' => 0,
            'sum_anggaran_2026' => 0.0,
            'sum_realisasi' => 0.0,
            'sample_row_indices' => [],
            'sample_kode_rekening' => [],
            'sample_uraian' => [],
        ];
    }

    $missingGrouped[$key]['row_count']++;
    $missingGrouped[$key]['sum_anggaran_2026'] += (float) ($row['anggaran_2026'] ?? 0);
    $missingGrouped[$key]['sum_realisasi'] += (float) ($row['realisasi'] ?? 0);

    if (count($missingGrouped[$key]['sample_row_indices']) < 5) {
        $missingGrouped[$key]['sample_row_indices'][] = $idx;
    }

    $kodeRekening = trim((string) ($row['kode_rekening'] ?? ''));
    if ($kodeRekening !== '' && count($missingGrouped[$key]['sample_kode_rekening']) < 5) {
        $missingGrouped[$key]['sample_kode_rekening'][] = $kodeRekening;
    }

    $uraian = trim((string) ($row['uraian'] ?? ''));
    if ($uraian !== '' && count($missingGrouped[$key]['sample_uraian']) < 3) {
        $missingGrouped[$key]['sample_uraian'][] = $uraian;
    }
}

$groupedList = array_values($missingGrouped);
usort($groupedList, static function ($a, $b) {
    $cmp = strcmp($a['kode_opd'], $b['kode_opd']);
    if ($cmp !== 0) {
        return $cmp;
    }
    return strcmp($a['kode_sub_kegiatan'], $b['kode_sub_kegiatan']);
});

$summaryByOpd = [];
foreach ($groupedList as $g) {
    $k = $g['kode_opd'];
    if (!isset($summaryByOpd[$k])) {
        $summaryByOpd[$k] = [
            'kode_opd' => $g['kode_opd'],
            'opd_id' => $g['opd_id'],
            'nama_skpd' => $g['nama_skpd'],
            'count_missing_groups' => 0,
            'count_missing_rows' => 0,
            'sum_missing_realisasi' => 0.0,
        ];
    }

    $summaryByOpd[$k]['count_missing_groups']++;
    $summaryByOpd[$k]['count_missing_rows'] += (int) $g['row_count'];
    $summaryByOpd[$k]['sum_missing_realisasi'] += (float) $g['sum_realisasi'];
}

$summaryList = array_values($summaryByOpd);
usort($summaryList, static function ($a, $b) {
    if ($a['count_missing_groups'] === $b['count_missing_groups']) {
        return strcmp($a['kode_opd'], $b['kode_opd']);
    }
    return $b['count_missing_groups'] <=> $a['count_missing_groups'];
});

$payload = [
    'generated_at' => date('c'),
    'source_file' => $filePath,
    'tahun' => $tahun,
    'document_type' => $documentType,
    'missing_group_count' => count($groupedList),
    'missing_row_count' => array_sum(array_map(static fn ($i) => (int) $i['row_count'], $groupedList)),
    'summary_by_opd' => $summaryList,
    'groups' => $groupedList,
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
    'kode_opd',
    'opd_id',
    'nama_skpd',
    'kode_sub_kegiatan',
    'row_count',
    'sum_anggaran_2026',
    'sum_realisasi',
    'sample_row_indices',
    'sample_kode_rekening',
    'sample_uraian',
], ',', '"', '\\');

foreach ($groupedList as $g) {
    fputcsv($fh, [
        $g['kode_opd'],
        $g['opd_id'],
        $g['nama_skpd'],
        $g['kode_sub_kegiatan'],
        $g['row_count'],
        $g['sum_anggaran_2026'],
        $g['sum_realisasi'],
        implode('|', $g['sample_row_indices']),
        implode('|', $g['sample_kode_rekening']),
        implode(' | ', $g['sample_uraian']),
    ], ',', '"', '\\');
}

fclose($fh);

echo "Export grouped missing sub kegiatan selesai.\n";
echo "- Missing groups: " . count($groupedList) . "\n";
echo "- Missing rows (akumulasi): " . $payload['missing_row_count'] . "\n";
echo "- JSON: {$outJson}\n";
echo "- CSV: {$outCsv}\n";
echo "- Ringkasan OPD (top 10 by groups):\n";

foreach (array_slice($summaryList, 0, 10) as $s) {
    echo "  - {$s['kode_opd']} | {$s['nama_skpd']} | groups={$s['count_missing_groups']} | rows={$s['count_missing_rows']}\n";
}
