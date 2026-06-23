<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Filesystem\Filesystem;
use App\Models\ResumeProgramAnnotation;

$fs = new Filesystem();
$file = $argv[1] ?? __DIR__ . '/../referensi/pusat/permasalahan_2025.sql';
$year = isset($argv[2]) ? (int) $argv[2] : 2026;
$tableName = $argv[3] ?? 'berdasarkan-bidang-urusan';

if (! $fs->exists($file)) {
    echo "File not found: {$file}\n";
    exit(1);
}

$contents = $fs->get($file);
preg_match_all('/INSERT INTO\s+\w+\s+VALUES\s*\((.*?)\);/si', $contents, $matches);
$rows = $matches[1] ?? [];

echo "Found " . count($rows) . " rows to import from {$file}\n";

$success = 0;
$failures = [];

foreach ($rows as $i => $rowStr) {
    $line = trim($rowStr);
    $cols = str_getcsv($line, ',', "'");
    if (count($cols) < 11) {
        $failures[] = ['row' => $i+1, 'reason' => 'unexpected column count', 'raw' => $line];
        continue;
    }
    [$id, $kode_progra, $nama_progra, $unit_id, $pagu, $id_permasalahan, $faktor_penghambat, $faktor_pendorong, $tindak_lanjut, $keterangan, $tahun_in_file] = $cols;

    $data = [
        'view' => 'rekap-permasalahan',
        'table_name' => $tableName,
        'basis' => 'bidang-urusan',
        'tahun' => $year,
        'entitas' => $unit_id ?: null,
        'program_kode' => $kode_progra ?: null,
        'program_nama' => $nama_progra ?: null,
        'faktor_penghambat' => $faktor_penghambat ?: null,
        'faktor_pendorong' => $faktor_pendorong ?: null,
        'faktor_tindak_lanjut' => $tindak_lanjut ?: null,
    ];

    try {
        ResumeProgramAnnotation::create($data);
        $success++;
        echo "OK: row " . ($i+1) . " program_code={$data['program_kode']}\n";
    } catch (\Throwable $e) {
        $failures[] = ['row' => $i+1, 'reason' => $e->getMessage(), 'data' => $data];
        echo "FAIL: row " . ($i+1) . " -> " . $e->getMessage() . "\n";
    }
}

echo "\nImport completed. Success: {$success}, Failures: " . count($failures) . "\n";
if (! empty($failures)) {
    echo "Failures details:\n";
    foreach ($failures as $f) {
        echo "Row {$f['row']}: {$f['reason']}\n";
    }
}

exit(0);
