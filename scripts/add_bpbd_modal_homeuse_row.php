<?php

$file = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
$backup = $file . '.bak_bpbd_homeuse_' . date('YmdHis');

$rows = json_decode((string) file_get_contents($file), true);
if (!is_array($rows)) {
    echo "JSON tidak valid: {$file}\n";
    exit(1);
}

$kodeOpd = '1.05.0.00.0.00.26.0000';
$namaSkpd = 'Badan Penanggulangan Bencana Daerah';
$kodeSub = '1.05.01.2.07.0009';
$kodeRekening = '5.2.02.05.002.00006';
$uraian = 'Belanja Modal Alat Rumah Tangga Lainnya (Home Use)';
$nilaiRealisasi = 12340000;

$foundIndex = null;
foreach ($rows as $i => $r) {
    if (!is_array($r)) {
        continue;
    }
    if (($r['kode_opd'] ?? '') === $kodeOpd
        && ($r['kode_sub_kegiatan'] ?? '') === $kodeSub
        && ($r['kode_rekening'] ?? '') === $kodeRekening
    ) {
        $foundIndex = $i;
        break;
    }
}

if ($foundIndex !== null) {
    $before = (float) ($rows[$foundIndex]['realisasi'] ?? 0);
    $rows[$foundIndex]['realisasi'] = $nilaiRealisasi;
    echo "Row existing ditemukan di index {$foundIndex}, realisasi diubah {$before} => {$nilaiRealisasi}\n";
} else {
    $rows[] = [
        'kode_opd' => $kodeOpd,
        'nama_skpd' => $namaSkpd,
        'kode_sub_kegiatan' => $kodeSub,
        'kode_rekening' => $kodeRekening,
        'uraian' => $uraian,
        'anggaran_2026' => 0,
        'realisasi' => $nilaiRealisasi,
    ];
    echo "Row baru ditambahkan untuk BPBD akun {$kodeRekening} dengan realisasi {$nilaiRealisasi}\n";
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
