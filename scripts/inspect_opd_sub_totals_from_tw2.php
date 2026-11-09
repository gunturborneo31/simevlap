<?php

$kodeOpd = $argv[1] ?? '';
if ($kodeOpd === '') {
    echo "Usage: php scripts/inspect_opd_sub_totals_from_tw2.php <kode_opd>\n";
    exit(1);
}

$file = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
$rows = json_decode((string) file_get_contents($file), true);
if (!is_array($rows)) {
    echo "JSON tidak valid: {$file}\n";
    exit(1);
}

$subTotals = [];
foreach ($rows as $r) {
    if (!is_array($r) || ($r['kode_opd'] ?? '') !== $kodeOpd) {
        continue;
    }

    $sub = (string) ($r['kode_sub_kegiatan'] ?? '');
    if ($sub === '') {
        $sub = 'UNKNOWN';
    }

    if (!isset($subTotals[$sub])) {
        $subTotals[$sub] = [
            'kode_sub_kegiatan' => $sub,
            'sum_realisasi' => 0.0,
            'count_rows' => 0,
        ];
    }

    $subTotals[$sub]['sum_realisasi'] += (float) ($r['realisasi'] ?? 0);
    $subTotals[$sub]['count_rows']++;
}

usort($subTotals, static function ($a, $b) {
    if ($a['sum_realisasi'] === $b['sum_realisasi']) {
        return strcmp($a['kode_sub_kegiatan'], $b['kode_sub_kegiatan']);
    }
    return $b['sum_realisasi'] <=> $a['sum_realisasi'];
});

echo "Sub totals dari tw_2 untuk OPD {$kodeOpd}\n";
foreach ($subTotals as $s) {
    echo "- {$s['kode_sub_kegiatan']} | realisasi={$s['sum_realisasi']} | rows={$s['count_rows']}\n";
}
