<?php

$file = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
$backup = $file . '.bak_bappelitbangda_modal_' . date('YmdHis');

$rows = json_decode((string) file_get_contents($file), true);
if (!is_array($rows)) {
    echo "JSON tidak valid: {$file}\n";
    exit(1);
}

$kodeOpd = '5.01.5.02.5.05.21.0000';
$namaSkpd = 'Badan Perencanaan Pembangunan, Penelitian dan Pengembangan Daerah';
$kodeSub = '5.01.01.2.07.0005';

$target = [
    'kode_rekening' => '5.2.02.05.001.00005',
    'uraian' => 'Belanja Modal Alat Kantor Lainnya',
    'realisasi' => 5250000.00,
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