<?php

$file = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
$backup = $file . '.bak_bapenda_adjustment_' . date('YmdHis');

$rows = json_decode((string) file_get_contents($file), true);
if (!is_array($rows)) {
    echo "JSON tidak valid: {$file}\n";
    exit(1);
}

$kodeOpd = '5.02.0.00.0.00.22.0000';
$namaSkpd = 'Badan Pendapatan Daerah';
$kodeSub = '5.02.01.2.02.0002';

$target = [
    'kode_rekening' => '5.1.00.00.000.00001',
    'uraian' => 'Belanja Daerah',
    'realisasi' => 2417974034.00,
];

$foundIndex = null;

foreach ($rows as $i => $r) {
    if (!is_array($r)) {
        continue;
    }

    if (($r['kode_opd'] ?? '') === $kodeOpd
        && ($r['kode_rekening'] ?? '') === $target['kode_rekening']
    ) {
        $foundIndex = $i;
        break;
    }
}

if ($foundIndex !== null) {
    $before = (float) ($rows[$foundIndex]['realisasi'] ?? 0);
    $rows[$foundIndex]['nama_skpd'] = $namaSkpd;
    $rows[$foundIndex]['kode_sub_kegiatan'] = $kodeSub;
    $rows[$foundIndex]['uraian'] = $target['uraian'];
    $rows[$foundIndex]['realisasi'] = $target['realisasi'];
    $rows[$foundIndex]['anggaran_2026'] = $rows[$foundIndex]['anggaran_2026'] ?? 0;
    echo "Update {$target['kode_rekening']}: {$before} => {$target['realisasi']}\n";
} else {
    $rows[] = [
        'jenis' => 'SUB KEGIATAN',
        'kode_rekening' => $target['kode_rekening'],
        'uraian' => $target['uraian'],
        'anggaran_2026' => 0,
        'realisasi' => $target['realisasi'],
        'realisasi_2025' => 0,
        'nama_skpd' => $namaSkpd,
        'kode_opd' => $kodeOpd,
        'kode_sub_kegiatan' => $kodeSub,
    ];
    echo "Tambah {$target['kode_rekening']} realisasi={$target['realisasi']}\n";
}

if (!copy($file, $backup)) {
    echo "Gagal membuat backup: {$backup}\n";
    exit(1);
}

$ok = file_put_contents(
    $file,
    json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

if ($ok === false) {
    echo "Gagal menulis file: {$file}\n";
    exit(1);
}

echo "Sukses update {$file}\n";
echo "Backup: {$backup}\n";