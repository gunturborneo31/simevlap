<?php

$kodeOpd = $argv[1] ?? '';
if ($kodeOpd === '') {
    echo "Usage: php scripts/inspect_opd_modal_mapping.php <kode_opd>\n";
    exit(1);
}

$file = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
$rows = json_decode((string) file_get_contents($file), true);
if (!is_array($rows)) {
    echo "JSON tidak valid: {$file}\n";
    exit(1);
}

$bySub = [];
foreach ($rows as $idx => $r) {
    if (!is_array($r) || ($r['kode_opd'] ?? '') !== $kodeOpd) {
        continue;
    }

    $kodeRek = (string) ($r['kode_rekening'] ?? '');
    if (!str_starts_with($kodeRek, '5.2.')) {
        continue;
    }

    $sub = (string) ($r['kode_sub_kegiatan'] ?? '');
    $k = $sub === '' ? 'UNKNOWN' : $sub;
    if (!isset($bySub[$k])) {
        $bySub[$k] = [
            'kode_sub_kegiatan' => $k,
            'count_rows' => 0,
            'sum_realisasi' => 0.0,
            'rekening' => [],
        ];
    }

    $bySub[$k]['count_rows']++;
    $bySub[$k]['sum_realisasi'] += (float) ($r['realisasi'] ?? 0);
    $bySub[$k]['rekening'][] = [
        'row' => $idx,
        'kode_rekening' => $kodeRek,
        'uraian' => (string) ($r['uraian'] ?? ''),
        'realisasi' => (float) ($r['realisasi'] ?? 0),
    ];
}

echo "Inspect modal mapping OPD {$kodeOpd}\n";
echo "- jumlah grup sub_kegiatan untuk akun 5.2.*: " . count($bySub) . "\n";

foreach ($bySub as $g) {
    echo "- sub={$g['kode_sub_kegiatan']} | rows={$g['count_rows']} | sum_realisasi={$g['sum_realisasi']}\n";
    foreach ($g['rekening'] as $r) {
        echo "  row={$r['row']} | {$r['kode_rekening']} | realisasi={$r['realisasi']} | {$r['uraian']}\n";
    }
}
