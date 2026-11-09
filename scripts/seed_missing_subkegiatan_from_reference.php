<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Kegiatan;
use App\Models\SubKegiatan;

$opts = getopt('', [
    'file::',
    'tahun::',
    'document_type::',
    'dry-run',
]);

$filePath = $opts['file'] ?? (__DIR__ . '/../referensi/realisasi/2026/tw_2_missing_subkegiatan_grouped.json');
$tahun = isset($opts['tahun']) ? (int) $opts['tahun'] : 2026;
$documentType = (string) ($opts['document_type'] ?? 'dpa');
$dryRun = array_key_exists('dry-run', $opts);

if (!file_exists($filePath)) {
    echo "File tidak ditemukan: {$filePath}\n";
    exit(1);
}

$json = json_decode((string) file_get_contents($filePath), true);
if (!is_array($json) || !isset($json['groups']) || !is_array($json['groups'])) {
    echo "Format grouped JSON tidak valid: {$filePath}\n";
    exit(1);
}

$created = 0;
$skippedExist = 0;
$skippedNoKegiatan = 0;
$skippedNoReference = 0;
$usedReferenceKegiatan = 0;
$sampleNoKegiatan = [];
$sampleNoReference = [];

foreach ($json['groups'] as $g) {
    $opdId = (int) ($g['opd_id'] ?? 0);
    $kodeSub = trim((string) ($g['kode_sub_kegiatan'] ?? ''));
    if ($opdId <= 0 || $kodeSub === '') {
        continue;
    }

    $existing = SubKegiatan::query()
        ->where('opd_id', $opdId)
        ->where('kode_rek', $kodeSub)
        ->first();

    if ($existing) {
        $skippedExist++;
        continue;
    }

    $parts = explode('.', $kodeSub);
    $kodeKegiatan = count($parts) >= 5 ? implode('.', array_slice($parts, 0, 5)) : null;

    $kegiatan = null;
    if ($kodeKegiatan) {
        $kegiatan = Kegiatan::query()
            ->where('opd_id', $opdId)
            ->where('kode_rek', $kodeKegiatan)
            ->where('document_type', $documentType)
            ->where('tahun', $tahun)
            ->first();

        if (!$kegiatan) {
            $kegiatan = Kegiatan::query()
                ->where('opd_id', $opdId)
                ->where('kode_rek', $kodeKegiatan)
                ->first();
        }
    }

    $reference = SubKegiatan::query()
        ->where('kode_rek', $kodeSub)
        ->where('document_type', $documentType)
        ->where('tahun', $tahun)
        ->first();

    if (!$reference) {
        $reference = SubKegiatan::query()
            ->where('kode_rek', $kodeSub)
            ->first();
    }

    if (!$reference) {
        $skippedNoReference++;
        if (count($sampleNoReference) < 20) {
            $sampleNoReference[] = "opd_id={$opdId} kode_sub={$kodeSub}";
        }
        continue;
    }

    $kegiatanId = $kegiatan?->id;
    if (!$kegiatanId) {
        $kegiatanId = $reference->kegiatan_id;
        $usedReferenceKegiatan++;
        $skippedNoKegiatan++;

        if (count($sampleNoKegiatan) < 20) {
            $sampleNoKegiatan[] = "opd_id={$opdId} kode_sub={$kodeSub} fallback_kegiatan_id={$kegiatanId}";
        }
    }

    $payload = [
        'kegiatan_id' => $kegiatanId,
        'opd_id' => $opdId,
        'kepmen_id' => $kegiatan?->kepmen_id ?? $reference->kepmen_id,
        'document_type' => $documentType,
        'kode_rek' => $kodeSub,
        'nama_rincian' => (string) ($reference->nama_rincian ?: ('Sub Kegiatan ' . $kodeSub . ' (otomatis)')),
        'tahun_awal' => $reference->tahun_awal,
        'tahun_akhir' => $reference->tahun_akhir,
        'target_t1' => $reference->target_t1,
        'target_t2' => $reference->target_t2,
        'target_t3' => $reference->target_t3,
        'target_t4' => $reference->target_t4,
        'target_t5' => $reference->target_t5,
        'target_tahunan' => $reference->target_tahunan,
        'tahun' => $tahun,
        'catatan_evaluasi' => $reference->catatan_evaluasi,
    ];

    if (!$dryRun) {
        SubKegiatan::query()->create($payload);
    }

    $created++;
}

echo "Seed missing SubKegiatan dari reference selesai.\n";
echo "- Mode: " . ($dryRun ? 'DRY RUN' : 'APPLY') . "\n";
echo "- Source grouped file: {$filePath}\n";
echo "- Created: {$created}\n";
echo "- Skipped (already exist): {$skippedExist}\n";
echo "- No kegiatan target (fallback ke kegiatan referensi): {$skippedNoKegiatan}\n";
echo "- Menggunakan kegiatan referensi: {$usedReferenceKegiatan}\n";
echo "- Skipped (no reference kode): {$skippedNoReference}\n";

if (!empty($sampleNoKegiatan)) {
    echo "\nContoh fallback kegiatan referensi:\n";
    foreach ($sampleNoKegiatan as $s) {
        echo "  - {$s}\n";
    }
}

if (!empty($sampleNoReference)) {
    echo "\nContoh no reference kode:\n";
    foreach ($sampleNoReference as $s) {
        echo "  - {$s}\n";
    }
}
