<?php
$dir = __DIR__ . '/../referensi/rkpd';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$patterns = [
    // various malformed forms -> corrected
    "PelaksanaanPenatausahaandan\\n\\nPengujian\\/VerifikasiKeuanganSKPD" => "Pelaksanaan Penatausahaan dan Pengujian / Verifikasi Keuangan SKPD",
    "PelaksanaanPenatausahaandan\\n\\nPengujian\\\\/VerifikasiKeuanganSKPD" => "Pelaksanaan Penatausahaan dan Pengujian / Verifikasi Keuangan SKPD",
    "PenatausahaandanPengujian" => "Penatausahaan dan Pengujian",
    "Penatausahaandan Pengujian" => "Penatausahaan dan Pengujian",
    "Penatausahaandan" => "Penatausahaan dan",
    // compacted without spaces
    "JumlahDokumenPenatausahaandanPengujian\\/VerifikasiKeuanganSKPD" => "Jumlah Dokumen Penatausahaan dan Pengujian / Verifikasi Keuangan SKPD",
    // spacing around slash
    "Pengujian\\/VerifikasiKeuanganSKPD" => "Pengujian / Verifikasi Keuangan SKPD",
    "Pengujian\\/ Verifikasi Keuangan SKPD" => "Pengujian / Verifikasi Keuangan SKPD",
];
$changed = 0;
foreach ($files as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    if (strpos($path, $dir) !== 0) continue;
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (!in_array($ext, ['json', 'txt'])) continue;
    $content = file_get_contents($path);
    $new = $content;
    foreach ($patterns as $from => $to) {
        $new = str_replace($from, $to, $new);
    }
    if ($new !== $content) {
        file_put_contents($path, $new);
        echo "Updated: $path\n";
        $changed++;
    }
}
echo "Done. Files changed: $changed\n";
