<?php

declare(strict_types=1);

/**
 * Isi kekosongan realisasi IKK berdasarkan kemiripan indikator dari file realisasi IKK yang sudah ada.
 *
 * Output:
 * - referensi/ikk/realisasi_ikk_similarity_report.json
 * - referensi/ikk/realisasi ikk.json (diupdate, backup otomatis)
 */

$root = dirname(__DIR__);
$ikkPath = $root . '/referensi/ikk/ikk.json';
$realisasiPath = $root . '/referensi/ikk/realisasi ikk.json';
$reportPath = $root . '/referensi/ikk/realisasi_ikk_similarity_report.json';

if (!is_file($ikkPath) || !is_file($realisasiPath)) {
    fwrite(STDERR, "File sumber tidak ditemukan.\n");
    exit(1);
}

$decodeJson = static function (string $path): array {
    $raw = (string) file_get_contents($path);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Gagal parse JSON: ' . $path . ' | ' . json_last_error_msg());
    }

    return $decoded;
};

$normalize = static function (?string $value): string {
    $text = mb_strtolower((string) $value);
    $text = preg_replace('/\([^\)]*\)/u', ' ', $text) ?? $text;
    $text = str_replace(["\n", "\r", '/', '-', ',', '.', ':', ';', '(', ')'], ' ', $text);
    $text = str_replace(['apk', 'apm', 'aps'], ['angka partisipasi kasar', 'angka partisipasi murni', 'angka partisipasi sekolah'], $text);

    $text = strtr($text, [
        'jumblah' => 'jumlah',
        'persentasi' => 'persentase',
        'perternakan' => 'peternakan',
        'pengukuruan' => 'pengukuran',
        'pemerdayaan' => 'pemberdayaan',
        'ketenteraman' => 'ketentraman',
    ]);

    $text = preg_replace('/\b(tingkat|persentase|rasio|angka|cakupan|jumlah|nilai|per|pada|dan|yang|dengan|terhadap|dari|untuk|tahun|kinerja|pelayanan)\b/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
};

$scoreSimilarity = static function (string $a, string $b): float {
    if ($a === '' || $b === '') {
        return 0.0;
    }

    similar_text($a, $b, $pct);
    $textScore = ((float) $pct) / 100;

    $tokensA = array_values(array_filter(explode(' ', $a), static fn ($v) => $v !== ''));
    $tokensB = array_values(array_filter(explode(' ', $b), static fn ($v) => $v !== ''));
    $intersection = count(array_intersect($tokensA, $tokensB));
    $union = count(array_unique(array_merge($tokensA, $tokensB)));
    $tokenScore = $union > 0 ? ($intersection / $union) : 0.0;

    return ($textScore * 0.7) + ($tokenScore * 0.3);
};

$ikkRows = $decodeJson($ikkPath);
$realisasiRows = $decodeJson($realisasiPath);

// Sumber realisasi yang valid (punya indikator + capaian terisi)
$sources = [];
foreach ($realisasiRows as $row) {
    if (!is_array($row)) {
        continue;
    }

    $indikator = (string) ($row['Indikator Tujuan / Sasaran'] ?? '');
    $capaian = $row['Capaian 2025'] ?? null;

    if ($indikator === '' || $capaian === null || $capaian === '' || strtoupper((string) $capaian) === 'N/A') {
        continue;
    }

    $key = $normalize($indikator);
    if ($key === '') {
        continue;
    }

    $sources[] = [
        'indikator' => $indikator,
        'key' => $key,
        'capaian' => $capaian,
        'opd' => $row['Perangkat Daerah Penanggung Jawab'] ?? null,
    ];
}

// Map existing by normalized master key
$existingByKey = [];
foreach ($realisasiRows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $indikator = (string) ($row['Indikator Tujuan / Sasaran'] ?? '');
    if ($indikator === '') {
        continue;
    }

    $key = $normalize($indikator);
    if ($key !== '') {
        $existingByKey[$key] = true;
    }
}

$added = [];
$updated = [];
$report = [];

$rowIndexByKey = [];
foreach ($realisasiRows as $idx => $row) {
    if (!is_array($row)) {
        continue;
    }

    $indikator = (string) ($row['Indikator Tujuan / Sasaran'] ?? '');
    if ($indikator === '') {
        continue;
    }

    $key = $normalize($indikator);
    if ($key !== '' && !array_key_exists($key, $rowIndexByKey)) {
        $rowIndexByKey[$key] = $idx;
    }
}

foreach ($ikkRows as $master) {
    if (!is_array($master)) {
        continue;
    }

    $indikatorIkk = (string) ($master['INDIKATOR KINERJA'] ?? '');
    if ($indikatorIkk === '') {
        continue;
    }

    $masterKey = $normalize($indikatorIkk);
    if ($masterKey === '') {
        continue;
    }

    $existingIndex = $rowIndexByKey[$masterKey] ?? null;
    $hasExistingRow = $existingIndex !== null;
    $existingValue = $hasExistingRow ? ($realisasiRows[$existingIndex]['Capaian 2025'] ?? null) : null;
    $needsUpdateExisting = $hasExistingRow && ($existingValue === null || $existingValue === '' || strtoupper((string) $existingValue) === 'N/A');

    // Lewati jika sudah ada dan nilainya sudah terisi.
    if ($hasExistingRow && !$needsUpdateExisting) {
        continue;
    }

    $bestScore = -1.0;
    $bestSource = null;

    foreach ($sources as $candidate) {
        $score = $scoreSimilarity($masterKey, (string) $candidate['key']);

        // Bonus kalau mengandung satu sama lain
        if (
            str_contains((string) $candidate['key'], $masterKey)
            || str_contains($masterKey, (string) $candidate['key'])
        ) {
            $score += 0.05;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestSource = $candidate;
        }
    }

    if ($bestSource === null) {
        continue;
    }

    if ($needsUpdateExisting && $existingIndex !== null) {
        $realisasiRows[$existingIndex]['Capaian 2025'] = $bestSource['capaian'];
        if (empty($realisasiRows[$existingIndex]['Perangkat Daerah Penanggung Jawab'])) {
            $realisasiRows[$existingIndex]['Perangkat Daerah Penanggung Jawab'] = $bestSource['opd'] ?? 'AUTO-SIMILARITY';
        }

        $updated[] = [
            'Indikator Tujuan / Sasaran' => $indikatorIkk,
            'Capaian 2025' => $bestSource['capaian'],
            'Perangkat Daerah Penanggung Jawab' => $realisasiRows[$existingIndex]['Perangkat Daerah Penanggung Jawab'] ?? ($bestSource['opd'] ?? 'AUTO-SIMILARITY'),
        ];
    } else {
        // Jika benar-benar belum ada, tambahkan row baru.
        $newRow = [
            'Indikator Tujuan / Sasaran' => $indikatorIkk,
            'Capaian 2025' => $bestSource['capaian'],
            'Perangkat Daerah Penanggung Jawab' => $bestSource['opd'] ?? 'AUTO-SIMILARITY',
        ];

        $realisasiRows[] = $newRow;
        $existingByKey[$masterKey] = true;
        $added[] = $newRow;
        $rowIndexByKey[$masterKey] = count($realisasiRows) - 1;
    }

    $report[] = [
        'indikator_ikk' => $indikatorIkk,
        'matched_to' => $bestSource['indikator'],
        'score' => round($bestScore, 4),
        'capaian_2025' => $bestSource['capaian'],
        'opd' => $bestSource['opd'],
    ];
}

// Backup file lama
$backupPath = $realisasiPath . '.bak_auto_similarity_' . date('YmdHis');
copy($realisasiPath, $backupPath);

// Simpan hasil
file_put_contents(
    $realisasiPath,
    json_encode($realisasiRows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

file_put_contents(
    $reportPath,
    json_encode([
        'generated_at' => date('c'),
        'master_ikk_count' => count($ikkRows),
        'source_realisasi_count' => count($sources),
        'added_count' => count($added),
        'updated_count' => count($updated),
        'backup_file' => basename($backupPath),
        'matches' => $report,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'added_count=' . count($added) . PHP_EOL;
    echo 'updated_count=' . count($updated) . PHP_EOL;
echo 'backup=' . $backupPath . PHP_EOL;
echo 'report=' . $reportPath . PHP_EOL;
