<?php

$file = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
$backup = $file . '.bak_puprkp_modal_' . date('YmdHis');

$rows = json_decode((string) file_get_contents($file), true);
if (!is_array($rows)) {
    echo "JSON tidak valid: {$file}\n";
    exit(1);
}

$kodeOpd = '1.03.1.04.0.00.03.0000';
$namaSkpd = 'Dinas Pekerjaan Umum dan Penataan Ruang, Perumahan dan Kawasan Pemukiman';
$kodeSub = '1.03.10.2.01.0053';

$targets = [
    [
        'kode_rekening' => '5.2.03.01.001.00010',
        'uraian' => 'Belanja Modal Bangunan Gedung Tempat Pendidikan',
        'realisasi' => 167988000.00,
    ],
    [
        'kode_rekening' => '5.2.04.01.001.00003',
        'uraian' => 'Belanja Modal Jalan Kabupaten',
        'realisasi' => 91013791952.00,
    ],
    [
        'kode_rekening' => '5.2.06.01.001.00005',
        'uraian' => 'Belanja Modal Aset Tidak Berwujud-Software',
        'realisasi' => 168959500.00,
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