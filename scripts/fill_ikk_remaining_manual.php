<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root . '/referensi/ikk/realisasi ikk.json';

$raw = (string) file_get_contents($path);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, 'JSON parse gagal: ' . json_last_error_msg() . PHP_EOL);
    exit(1);
}

$normalize = static function (?string $value): string {
    $text = mb_strtolower((string) $value);
    $text = preg_replace('/\([^\)]*\)/u', ' ', $text) ?? $text;
    $text = str_replace(["\n", "\r", '/', '-', ',', '.', ':', ';'], ' ', $text);
    $text = str_replace(['ketenteraman'], ['ketentraman'], $text);
    $text = strtr($text, ['persentasi' => 'persentase', 'jumblah' => 'jumlah']);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
};

$indexByKey = [];
foreach ($data as $idx => $row) {
    if (!is_array($row)) continue;
    $ind = (string) ($row['Indikator Tujuan / Sasaran'] ?? '');
    if ($ind === '') continue;
    $k = $normalize($ind);
    if ($k !== '' && !isset($indexByKey[$k])) {
        $indexByKey[$k] = $idx;
    }
}

$getCapaian = static function (array $row): mixed {
    return $row['Capaian 2025'] ?? null;
};

$getValByContains = static function (array $rows, string $needle) use ($normalize, $getCapaian): mixed {
    $needleNorm = $normalize($needle);
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $name = (string) ($row['Indikator Tujuan / Sasaran'] ?? '');
        $val = $getCapaian($row);
        if ($name === '' || $val === null || $val === '' || strtoupper((string) $val) === 'N/A') continue;
        $nameNorm = $normalize($name);
        if (str_contains($nameNorm, $needleNorm) || str_contains($needleNorm, $nameNorm)) {
            return $val;
        }
    }
    return null;
};

$manualMap = [
    'Persentase balita gizi buruk' => ['Persentase balita gizi buruk', 'Prevalensi stunting'],
    'Cakupan Desa/ Kelurahan mengalami KLB' => ['Cakupan penemuan dan penanganan penderita penyakit DBD'],
    'Jumlah kerjasama antar desa' => ['Jumlah Bumdes yang Berkembang'],
    'Persentase wirausaha muda' => ['Jumlah Pelaku Usaha Berbasis Ekonomi Kreatif Unggulan'],
    'Persentase sasaran dan program RKPD yang konsisten dengan RPJMD' => ['Persentase keselarasan substansi dokumen perencanaan'],
    'persentase ketersediaan dokumen perencanaan tepat waktu' => ['Persentase keselarasan substansi dokumen perencanaan'],
    'Persentase kebijakan inovasi yang diterapkan di masyarakat' => ['Persentase perangkat daerah yang difasilitasi dalam penerapan inovasi Daerah'],
    'Persentase perangkat daerah yang difasilitasi dalam penerapan inovasi Daerah' => ['Persentase perangkat daerah yang difasilitasi dalam penerapan inovasi Daerah'],
    'Persentase kebijakan inovasi yang diterapkan di lingkungan pemerintah daerah' => ['Persentase perangkat daerah yang difasilitasi dalam penerapan inovasi Daerah'],
];

$added = 0;

foreach ($manualMap as $targetIndikator => $candidates) {
    $targetKey = $normalize($targetIndikator);
    if ($targetKey === '' || isset($indexByKey[$targetKey])) {
        continue;
    }

    $val = null;
    foreach ($candidates as $cand) {
        $val = $getValByContains($data, $cand);
        if ($val !== null && $val !== '' && strtoupper((string) $val) !== 'N/A') {
            break;
        }
    }

    if ($val === null || $val === '' || strtoupper((string) $val) === 'N/A') {
        continue;
    }

    $data[] = [
        'Indikator Tujuan / Sasaran' => $targetIndikator,
        'Capaian 2025' => $val,
        'Perangkat Daerah Penanggung Jawab' => 'AUTO-MANUAL-FALLBACK',
    ];
    $indexByKey[$targetKey] = count($data) - 1;
    $added++;
}

$backup = $path . '.bak_manual_remaining_' . date('YmdHis');
copy($path, $backup);
file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo 'added_manual=' . $added . PHP_EOL;
echo 'backup=' . $backup . PHP_EOL;
