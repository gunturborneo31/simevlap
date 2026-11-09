<?php

$rekening = $argv[1] ?? '';
$kodeOpdFilter = $argv[2] ?? '';
$customFile = $argv[3] ?? '';

if ($rekening === '') {
    echo "Usage: php scripts/inspect_tw2_rekening_mapping.php <kode_rekening> [kode_opd]\n";
    exit(1);
}

$file = $customFile !== '' ? $customFile : (__DIR__ . '/../referensi/realisasi/2026/tw_2.json');
$rows = json_decode((string) file_get_contents($file), true);
if (!is_array($rows)) {
    echo "JSON tidak valid: {$file}\n";
    exit(1);
}

$matches = [];
foreach ($rows as $idx => $r) {
    if (!is_array($r)) {
        continue;
    }
    if (($r['kode_rekening'] ?? '') !== $rekening) {
        continue;
    }
    if ($kodeOpdFilter !== '' && ($r['kode_opd'] ?? '') !== $kodeOpdFilter) {
        continue;
    }

    $matches[] = [
        'row_index' => $idx,
        'kode_opd' => (string) ($r['kode_opd'] ?? ''),
        'nama_skpd' => (string) ($r['nama_skpd'] ?? ''),
        'kode_sub_kegiatan' => (string) ($r['kode_sub_kegiatan'] ?? ''),
        'uraian' => (string) ($r['uraian'] ?? ''),
        'realisasi' => (float) ($r['realisasi'] ?? 0),
    ];
}

echo "Inspect rekening {$rekening}\n";
echo "- total rows matched: " . count($matches) . "\n";

$group = [];
foreach ($matches as $m) {
    $k = $m['kode_opd'] . '|' . $m['kode_sub_kegiatan'];
    if (!isset($group[$k])) {
        $group[$k] = [
            'kode_opd' => $m['kode_opd'],
            'nama_skpd' => $m['nama_skpd'],
            'kode_sub_kegiatan' => $m['kode_sub_kegiatan'],
            'sum_realisasi' => 0.0,
            'count_rows' => 0,
        ];
    }
    $group[$k]['sum_realisasi'] += $m['realisasi'];
    $group[$k]['count_rows']++;
}

foreach (array_values($group) as $g) {
    echo "- {$g['kode_opd']} | {$g['nama_skpd']} | sub={$g['kode_sub_kegiatan']} | rows={$g['count_rows']} | realisasi={$g['sum_realisasi']}\n";
}

if ($kodeOpdFilter !== '') {
    foreach ($matches as $m) {
        echo "  row={$m['row_index']} sub={$m['kode_sub_kegiatan']} realisasi={$m['realisasi']} uraian={$m['uraian']}\n";
    }
}
