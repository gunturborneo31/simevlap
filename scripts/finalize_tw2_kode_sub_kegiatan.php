<?php
require __DIR__ . '/../vendor/autoload.php';

$defaultTw2Path = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
$sourcePath = $defaultTw2Path;
$reportOnly = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--source=')) {
        $sourcePath = substr($arg, strlen('--source='));
    }
    if ($arg === '--report-only') {
        $reportOnly = true;
    }
}

$tw2Path = $defaultTw2Path;
$reportJsonPath = __DIR__ . '/../referensi/realisasi/2026/tw_2_low_confidence_report.json';
$reportCsvPath = __DIR__ . '/../referensi/realisasi/2026/tw_2_low_confidence_report.csv';

if (!file_exists($sourcePath)) {
    echo "File sumber tidak ditemukan: {$sourcePath}\n";
    exit(1);
}

$rows = json_decode((string) file_get_contents($sourcePath), true);
if (!is_array($rows)) {
    echo "Gagal parse JSON: {$sourcePath}\n";
    exit(1);
}

$statusCounts = [];
$lowRows = [];

foreach ($rows as $idx => &$row) {
    if (!is_array($row)) {
        continue;
    }

    $status = isset($row['mapping_status']) ? (string) $row['mapping_status'] : '';
    if ($status !== '') {
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }

    if ($status === 'low_confidence') {
        $lowRows[] = [
            'row_index' => $idx,
            'kode_opd' => $row['kode_opd'] ?? null,
            'nama_skpd' => $row['nama_skpd'] ?? null,
            'kode_sub' => $row['kode_sub'] ?? ($row['kode_rekening'] ?? null),
            'uraian' => $row['uraian'] ?? null,
            'anggaran_2026' => $row['anggaran_2026'] ?? null,
            'realisasi' => $row['realisasi'] ?? null,
            'kode_sub_kegiatan' => $row['kode_sub_kegiatan'] ?? null,
            'mapping_status' => $status,
        ];
    }

    // Finalisasi file utama: hilangkan status mapping.
    unset($row['mapping_status']);
}
unset($row);

$backupPath = null;
if (!$reportOnly) {
    $backupPath = $tw2Path . '.bak_' . date('YmdHis');
    if (!copy($tw2Path, $backupPath)) {
        echo "Gagal membuat backup: {$backupPath}\n";
        exit(1);
    }

    $encodedMain = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encodedMain === false) {
        echo "Gagal encode JSON utama.\n";
        exit(1);
    }
    file_put_contents($tw2Path, $encodedMain . PHP_EOL);
}

$reportPayload = [
    'generated_at' => date('c'),
    'source_file' => $sourcePath,
    'total_rows' => count($rows),
    'status_counts_before_cleanup' => $statusCounts,
    'low_confidence_count' => count($lowRows),
    'rows' => $lowRows,
];

$encodedReport = json_encode($reportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($encodedReport === false) {
    echo "Gagal encode report JSON.\n";
    exit(1);
}
file_put_contents($reportJsonPath, $encodedReport . PHP_EOL);

$fp = fopen($reportCsvPath, 'w');
if ($fp === false) {
    echo "Gagal membuat report CSV: {$reportCsvPath}\n";
    exit(1);
}

fputcsv($fp, [
    'row_index',
    'kode_opd',
    'nama_skpd',
    'kode_sub',
    'uraian',
    'anggaran_2026',
    'realisasi',
    'kode_sub_kegiatan',
    'mapping_status',
], ',', '"', '');

foreach ($lowRows as $r) {
    fputcsv($fp, [
        $r['row_index'],
        $r['kode_opd'],
        $r['nama_skpd'],
        $r['kode_sub'],
        $r['uraian'],
        $r['anggaran_2026'],
        $r['realisasi'],
        $r['kode_sub_kegiatan'],
        $r['mapping_status'],
    ], ',', '"', '');
}

fclose($fp);

echo "Selesai finalisasi TW2.\n";
echo "- Mode report-only: " . ($reportOnly ? 'ya' : 'tidak') . "\n";
echo "- Total baris: " . count($rows) . "\n";
echo "- Status sebelum cleanup: " . json_encode($statusCounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
echo "- Low confidence report: {$reportJsonPath}\n";
echo "- Low confidence CSV: {$reportCsvPath}\n";
if ($backupPath !== null) {
    echo "- Backup file utama: {$backupPath}\n";
}
