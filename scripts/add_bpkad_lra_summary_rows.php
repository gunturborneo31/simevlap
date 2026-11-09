<?php

$file = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
$backup = $file . '.bak_bpkad_lra_' . date('YmdHis');

$rows = json_decode((string) file_get_contents($file), true);
if (!is_array($rows)) {
    echo "JSON tidak valid: {$file}\n";
    exit(1);
}

$kodeOpd = '5.02.4.01.0.00.23.0000';
$namaSkpd = 'Badan Pengelola Keuangan dan Aset Daerah';
$defaultKodeSub = '5.02.02.2.01.0005';

$targets = [
    [
        'kode_rekening' => '4.2.01.01.000.00001',
        'kode_sub_kegiatan' => $defaultKodeSub,
        'uraian' => 'Pendapatan Transfer Pemerintah Pusat',
        'realisasi' => 363236214322.00,
    ],
    [
        'kode_rekening' => '5.1.00.00.000.00001',
        'kode_sub_kegiatan' => $defaultKodeSub,
        'uraian' => 'Belanja Daerah',
        'realisasi' => -26390261564.00,
    ],
    [
        'kode_rekening' => '6.1.01.07.001.00001',
        'kode_sub_kegiatan' => $defaultKodeSub,
        'uraian' => 'Penerimaan Pembiayaan Daerah',
        'realisasi' => 211996416400.89,
    ],
];

foreach ($targets as $target) {
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
        $rows[$foundIndex]['kode_sub_kegiatan'] = $target['kode_sub_kegiatan'];
        $rows[$foundIndex]['uraian'] = $target['uraian'];
        $rows[$foundIndex]['realisasi'] = $target['realisasi'];
        $rows[$foundIndex]['anggaran_2026'] = $rows[$foundIndex]['anggaran_2026'] ?? 0;
        echo "Update {$target['kode_rekening']}: {$before} => {$target['realisasi']}\n";
        continue;
    }

    $rows[] = [
        'jenis' => 'SUB KEGIATAN',
        'kode_rekening' => $target['kode_rekening'],
        'uraian' => $target['uraian'],
        'anggaran_2026' => 0,
        'realisasi' => $target['realisasi'],
        'realisasi_2025' => 0,
        'nama_skpd' => $namaSkpd,
        'kode_opd' => $kodeOpd,
        'kode_sub_kegiatan' => $target['kode_sub_kegiatan'],
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