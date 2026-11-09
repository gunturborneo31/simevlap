<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$ikk = json_decode((string) file_get_contents($root . '/referensi/ikk/ikk.json'), true);
$realisasi = json_decode((string) file_get_contents($root . '/referensi/ikk/realisasi ikk.json'), true);

$normalize = static function (?string $value): string {
    $text = mb_strtolower((string) $value);
    $text = preg_replace('/\([^\)]*\)/u', ' ', $text) ?? $text;
    $text = str_replace(["\n", "\r", '/', '-', ',', '.', ':', ';'], ' ', $text);
    $text = strtr($text, ['jumblah' => 'jumlah', 'persentasi' => 'persentase', 'ketenteraman' => 'ketentraman']);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
};

$targets = [
    'Persentase balita gizi buruk',
    'Cakupan Desa/ Kelurahan mengalami KLB',
    'Jumlah kerjasama antar desa',
    'Persentase wirausaha muda',
];

$sources = [];
foreach ($realisasi as $r) {
    if (!is_array($r)) continue;
    $name = (string) ($r['Indikator Tujuan / Sasaran'] ?? '');
    $cap = $r['Capaian 2025'] ?? null;
    if ($name === '' || $cap === null || $cap === '') continue;
    $sources[] = ['name' => $name, 'key' => $normalize($name), 'cap' => $cap];
}

$score = static function (string $a, string $b): float {
    similar_text($a, $b, $pct);
    $t1 = array_values(array_filter(explode(' ', $a)));
    $t2 = array_values(array_filter(explode(' ', $b)));
    $i = count(array_intersect($t1, $t2));
    $u = count(array_unique(array_merge($t1, $t2)));
    return (($pct/100)*0.7) + (($u>0? $i/$u : 0)*0.3);
};

foreach ($targets as $t) {
    $k = $normalize($t);
    $best = ['s' => -1, 'n' => null, 'c' => null];
    foreach ($sources as $s) {
        $sc = $score($k, (string) $s['key']);
        if ($sc > $best['s']) $best = ['s' => $sc, 'n' => $s['name'], 'c' => $s['cap']];
    }
    echo $t . PHP_EOL;
    echo '  key=' . $k . PHP_EOL;
    echo '  best=' . ($best['n'] ?? '-') . PHP_EOL;
    echo '  score=' . round((float) $best['s'], 4) . PHP_EOL;
    echo '  capaian=' . json_encode($best['c'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
