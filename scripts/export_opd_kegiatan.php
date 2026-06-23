<?php
$kodeSkpd = $argv[1] ?? '5.03.5.04.0.00.24.0000';
$year = $argv[2] ?? '';
$base = __DIR__ . '/../referensi/rkpd';
$outDir = __DIR__ . '/../storage/app/exports';
@mkdir($outDir, 0777, true);
$outFile = $outDir . '/opd_'.preg_replace('/[^A-Z0-9]/i','_', $kodeSkpd) . '_kegiatan.csv';
$fp = fopen($outFile, 'w');
if (!$fp) { echo "Failed to open output file\n"; exit(1); }
fputcsv($fp, ['type','kode','nama','label','tahun','source_file']);
$files = ['program.json','kegaiatan.json','sub_kegiatan.json'];
foreach ($files as $file) {
    $path = $base . '/' . $file;
    if (!file_exists($path)) continue;
    $json = file_get_contents($path);
    $items = json_decode($json, true);
    if (!is_array($items)) continue;
    foreach ($items as $it) {
        $k = $it['KODE_SKPD'] ?? ($it['KODE_SUB_UNIT'] ?? null);
        if ($k !== $kodeSkpd) continue;
        $type = strtoupper(str_replace('.json','',$file));
        $kode = $it['KODE_KEGIATAN'] ?? $it['KODE_PROGRAM'] ?? $it['KODE_SUB_KEGIATAN'] ?? '';
        $nama = $it['NAMA_KEGIATAN'] ?? $it['NAMA_PROGRAM'] ?? $it['NAMA_SUB_KEGIATAN'] ?? '';
        $label = $it['LABEL_SUB_KEGIATAN'] ?? '';
        $tahun = $it['TAHUN'] ?? '';
        fputcsv($fp, [$type, $kode, $nama, $label, $tahun, $file]);
    }
}
fclose($fp);
echo "Wrote: $outFile\n";

