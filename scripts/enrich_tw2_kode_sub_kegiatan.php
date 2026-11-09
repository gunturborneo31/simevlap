<?php
require __DIR__ . '/../vendor/autoload.php';

$tw2Path = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
$masterPaths = [
    __DIR__ . '/../referensi/rkpd/sub_kegiatan.json',
    __DIR__ . '/../referensi/apbd/sub_kegiatan.json',
];

if (!file_exists($tw2Path)) {
    echo "File TW2 tidak ditemukan: {$tw2Path}\n";
    exit(1);
}

$existingMasterPaths = array_values(array_filter($masterPaths, fn ($p) => file_exists($p)));
if (empty($existingMasterPaths)) {
    echo "Master sub_kegiatan tidak ditemukan (rkpd/apbd).\n";
    exit(1);
}

$tw2Raw = file_get_contents($tw2Path);
$tw2Rows = json_decode($tw2Raw, true);
if (!is_array($tw2Rows)) {
    // Fallback jika isi berupa deretan object tanpa pembungkus array.
    $tw2Rows = json_decode('[' . trim($tw2Raw) . ']', true);
}

if (!is_array($tw2Rows)) {
    echo "Gagal parse JSON TW2.\n";
    exit(1);
}

$masterRows = [];
foreach ($existingMasterPaths as $masterPath) {
    $data = json_decode((string) file_get_contents($masterPath), true);
    if (is_array($data)) {
        foreach ($data as $row) {
            if (is_array($row)) {
                $masterRows[] = $row;
            }
        }
    }
}

if (empty($masterRows)) {
    echo "Gagal parse master sub_kegiatan (data kosong).\n";
    exit(1);
}

/**
 * Normalisasi string untuk pencocokan teks sederhana.
 */
function normalize_text(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\s]+/', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
}

function tokenize(string $value): array
{
    $parts = explode(' ', normalize_text($value));
    $tokens = [];
    foreach ($parts as $p) {
        if (strlen($p) >= 3) {
            $tokens[$p] = true;
        }
    }
    return array_keys($tokens);
}

function score_candidate(array $row, array $candidate): float
{
    $uraian = (string) ($row['uraian'] ?? '');
    $namaSub = (string) ($candidate['NAMA_SUB_KEGIATAN'] ?? '');

    $rowTokens = tokenize($uraian);
    $candTokens = tokenize($namaSub);

    $overlap = 0;
    if (!empty($rowTokens) && !empty($candTokens)) {
        $set = array_fill_keys($candTokens, true);
        foreach ($rowTokens as $t) {
            if (isset($set[$t])) {
                $overlap++;
            }
        }
    }

    $score = (float) $overlap;

    $kodeAkun = (string) ($row['kode_sub'] ?? $row['kode_rekening'] ?? '');
    $uraianNorm = normalize_text($uraian);
    $namaNorm = normalize_text($namaSub);

    // Heuristik kategori belanja pegawai.
    if (str_starts_with($kodeAkun, '5.1.01.') || preg_match('/\b(gaji|tunjangan|pppk|pns|penghasilan)\b/', $uraianNorm)) {
        if (preg_match('/\b(gaji|tunjangan|asn|kepegawaian)\b/', $namaNorm)) {
            $score += 8.0;
        }
    }

    // Heuristik kategori belanja operasional kantor.
    if (str_starts_with($kodeAkun, '5.1.02.') || preg_match('/\b(kantor|alat|bahan|kertas|komputer|rapat|konsumsi|logistik|jasa)\b/', $uraianNorm)) {
        if (preg_match('/\b(kantor|logistik|peralatan|perlengkapan|rapat|pelayanan|jasa|pemeliharaan)\b/', $namaNorm)) {
            $score += 5.0;
        }
    }

    // Bonus jika pagu kandidat masuk akal terhadap nilai anggaran baris.
    $rowAnggaran = (float) ($row['anggaran_2026'] ?? 0);
    $candPagu = (float) ($candidate['PAGU_VALIDASI'] ?? $candidate['PAGU'] ?? 0);
    if ($rowAnggaran > 0 && $candPagu >= $rowAnggaran) {
        $score += 1.5;
    }

    // Sedikit penalti jika kandidat tanpa pagu.
    if ($candPagu <= 0) {
        $score -= 0.5;
    }

    return $score;
}

// Index master per OPD agar pencarian cepat.
$byOpd = [];
$byOpdName = [];
$byUrusanPrefix = [];
foreach ($masterRows as $m) {
    $opd = trim((string) ($m['KODE_SKPD'] ?? $m['KODE_SUB_UNIT'] ?? ''));
    $opdName = normalize_text((string) ($m['NAMA_SKPD'] ?? $m['NAMA_SUB_UNIT'] ?? ''));
    $kodeSubKegiatan = trim((string) ($m['KODE_SUB_KEGIATAN'] ?? ''));
    if ($opd === '' || $kodeSubKegiatan === '') {
        continue;
    }
    if (!isset($byOpd[$opd])) {
        $byOpd[$opd] = [];
    }
    $byOpd[$opd][] = $m;

    if ($opdName !== '') {
        if (!isset($byOpdName[$opdName])) {
            $byOpdName[$opdName] = [];
        }
        $byOpdName[$opdName][] = $m;
    }

    $prefix = implode('.', array_slice(explode('.', $kodeSubKegiatan), 0, 2));
    if ($prefix !== '') {
        if (!isset($byUrusanPrefix[$prefix])) {
            $byUrusanPrefix[$prefix] = [];
        }
        $byUrusanPrefix[$prefix][] = $m;
    }
}

$updated = 0;
$fallback = 0;
$unmapped = 0;

foreach ($tw2Rows as &$row) {
    if (!is_array($row)) {
        continue;
    }

    $opdCode = trim((string) ($row['kode_opd'] ?? $row['kode_skpd'] ?? ''));

    // Jika sudah ada, biarkan.
    if (!empty($row['kode_sub_kegiatan'])) {
        continue;
    }

    $candidates = $byOpd[$opdCode] ?? [];
    if (empty($candidates)) {
        $nameKey = normalize_text((string) ($row['nama_skpd'] ?? ''));
        if ($nameKey !== '' && isset($byOpdName[$nameKey])) {
            $candidates = $byOpdName[$nameKey];
        }
    }
    if (empty($candidates)) {
        $prefix = implode('.', array_slice(explode('.', $opdCode), 0, 2));
        if ($prefix !== '' && isset($byUrusanPrefix[$prefix])) {
            $candidates = $byUrusanPrefix[$prefix];
        }
    }
    if (empty($candidates)) {
        $row['kode_sub_kegiatan'] = null;
        $row['mapping_status'] = 'opd_not_found';
        $unmapped++;
        $updated++;
        continue;
    }

    $best = null;
    $bestScore = -INF;
    foreach ($candidates as $cand) {
        $score = score_candidate($row, $cand);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $cand;
        }
    }

    if ($best) {
        $row['kode_sub_kegiatan'] = $best['KODE_SUB_KEGIATAN'] ?? null;
        // Status transparan agar mudah review manual bila perlu.
        if ($bestScore >= 8) {
            $row['mapping_status'] = 'high_confidence';
        } elseif ($bestScore >= 4) {
            $row['mapping_status'] = 'medium_confidence';
        } else {
            $row['mapping_status'] = 'low_confidence';
            $fallback++;
        }
    } else {
        $row['kode_sub_kegiatan'] = null;
        $row['mapping_status'] = 'no_candidate';
        $unmapped++;
    }

    $updated++;
}
unset($row);

$backupPath = $tw2Path . '.bak_' . date('YmdHis');
if (!copy($tw2Path, $backupPath)) {
    echo "Gagal membuat backup file: {$backupPath}\n";
    exit(1);
}

$encoded = json_encode($tw2Rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($encoded === false) {
    echo "Gagal encode JSON hasil.\n";
    exit(1);
}

file_put_contents($tw2Path, $encoded . PHP_EOL);

echo "Selesai.\n";
echo "- Total baris: " . count($tw2Rows) . "\n";
echo "- Baris disisipkan kode_sub_kegiatan: {$updated}\n";
echo "- Low confidence: {$fallback}\n";
echo "- Unmapped: {$unmapped}\n";
echo "- Backup: {$backupPath}\n";
