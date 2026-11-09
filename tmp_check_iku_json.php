<?php
$path = __DIR__ . '/referensi/iku-ikk/iku.json';
echo "base=" . __DIR__ . PHP_EOL;
echo "path=" . $path . PHP_EOL;
echo "exists=" . (is_file($path) ? 'yes' : 'no') . PHP_EOL;
$s = file_get_contents($path);
$d = json_decode($s, true);
echo "json_error=" . json_last_error() . ' ' . json_last_error_msg() . PHP_EOL;
echo "count=" . (is_array($d) ? count($d) : 0) . PHP_EOL;
if (is_array($d)) {
    foreach ($d as $row) {
        if (($row['Indikator'] ?? null) === 'Indeks Gini') {
            echo "gini_realisasi=" . ($row['realisasi'] ?? 'NULL') . PHP_EOL;
        }
    }
}
