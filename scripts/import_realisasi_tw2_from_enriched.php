<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\Realisasi;
use App\Models\SubKegiatan;
use App\Models\User;

$opts = getopt('', [
    'file::',
    'tahun::',
    'triwulan::',
    'document_type::',
    'dry-run',
    'replace-existing-for-file-opd',
]);

$filePath = $opts['file'] ?? (__DIR__ . '/../referensi/realisasi/2026/tw_2.json');
$tahun = isset($opts['tahun']) ? (int) $opts['tahun'] : 2026;
$triwulan = isset($opts['triwulan']) ? (int) $opts['triwulan'] : 2;
$documentType = (string) ($opts['document_type'] ?? 'dpa');
$dryRun = array_key_exists('dry-run', $opts);
$replaceExistingForFileOpd = array_key_exists('replace-existing-for-file-opd', $opts);

if (!file_exists($filePath)) {
    echo "File tidak ditemukan: {$filePath}\n";
    exit(1);
}

$rows = json_decode((string) file_get_contents($filePath), true);
if (!is_array($rows)) {
    echo "Format JSON tidak valid: {$filePath}\n";
    exit(1);
}

if ($triwulan < 1 || $triwulan > 4) {
    echo "Triwulan harus antara 1 sampai 4.\n";
    exit(1);
}

$user = User::first();
$inputBy = $user?->id ?? 1;

$created = 0;
$updated = 0;
$skippedInvalid = 0;
$skippedMissingOpd = 0;
$skippedMissingSub = 0;

$groupedRows = [];
$groupedSourceRowCount = 0;
$deletedExistingCount = 0;

$sampleMissingOpd = [];
$sampleMissingSub = [];

foreach ($rows as $idx => $row) {
    if (!is_array($row)) {
        $skippedInvalid++;
        continue;
    }

    $kodeOpd = trim((string) ($row['kode_opd'] ?? $row['kode_skpd'] ?? ''));
    $kodeSubKegiatan = trim((string) ($row['kode_sub_kegiatan'] ?? ''));
    $realisasiVal = isset($row['realisasi']) ? (float) $row['realisasi'] : null;

    if ($kodeOpd === '' || $kodeSubKegiatan === '' || $realisasiVal === null) {
        $skippedInvalid++;
        continue;
    }

    $groupKey = $kodeOpd . '|' . $kodeSubKegiatan;
    if (!isset($groupedRows[$groupKey])) {
        $groupedRows[$groupKey] = [
            'kode_opd' => $kodeOpd,
            'kode_sub_kegiatan' => $kodeSubKegiatan,
            'realisasi' => 0.0,
            'row_indices' => [],
        ];
    }

    $groupedRows[$groupKey]['realisasi'] += $realisasiVal;
    $groupedRows[$groupKey]['row_indices'][] = $idx;
    $groupedSourceRowCount++;
}

if ($replaceExistingForFileOpd) {
    $kodeOpdFromFile = [];
    foreach ($groupedRows as $g) {
        $kodeOpdFromFile[$g['kode_opd']] = true;
    }

    $opdIds = Opd::query()
        ->whereIn('kode', array_keys($kodeOpdFromFile))
        ->pluck('id')
        ->all();

    if (!empty($opdIds) && !$dryRun) {
        $deletedExistingCount = Realisasi::query()
            ->whereIn('opd_id', $opdIds)
            ->where('tahun', $tahun)
            ->where('triwulan', $triwulan)
            ->where('document_type', $documentType)
            ->where('realisaseable_type', SubKegiatan::class)
            ->delete();
    }
}

foreach ($groupedRows as $grouped) {
    $kodeOpd = $grouped['kode_opd'];
    $kodeSubKegiatan = $grouped['kode_sub_kegiatan'];
    $realisasiVal = (float) $grouped['realisasi'];
    $sampleRow = $grouped['row_indices'][0] ?? -1;

    $opd = Opd::where('kode', $kodeOpd)->first();
    if (!$opd) {
        $skippedMissingOpd++;
        if (count($sampleMissingOpd) < 20) {
            $sampleMissingOpd[] = "baris={$sampleRow} kode_opd={$kodeOpd}";
        }
        continue;
    }

    $sub = SubKegiatan::query()
        ->where('opd_id', $opd->id)
        ->where('kode_rek', $kodeSubKegiatan)
        ->where('document_type', $documentType)
        ->where('tahun', $tahun)
        ->first();

    // Fallback bila master tidak terisi document_type/tahun sesuai.
    if (!$sub) {
        $sub = SubKegiatan::query()
            ->where('opd_id', $opd->id)
            ->where('kode_rek', $kodeSubKegiatan)
            ->first();
    }

    if (!$sub) {
        $skippedMissingSub++;
        if (count($sampleMissingSub) < 20) {
            $sampleMissingSub[] = "baris={$sampleRow} opd_id={$opd->id} kode_sub_kegiatan={$kodeSubKegiatan}";
        }
        continue;
    }

    $existing = Realisasi::query()
        ->where('realisaseable_id', $sub->id)
        ->where('realisaseable_type', SubKegiatan::class)
        ->where('tahun', $tahun)
        ->where('triwulan', $triwulan)
        ->where('document_type', $documentType)
        ->first();

    $sisaAnggaran = null;
    if (isset($sub->pagu) && $sub->pagu !== null) {
        $sisaAnggaran = (float) $sub->pagu - $realisasiVal;
    }

    if ($existing) {
        $existing->opd_id = $opd->id;
        $existing->realisasi_keuangan = $realisasiVal;
        $existing->realisasi_fisik = 0;
        $existing->sisa_anggaran = $sisaAnggaran;
        $existing->input_by = $inputBy;

        if (!$dryRun) {
            $existing->save();
        }
        $updated++;
    } else {
        $new = new Realisasi();
        $new->realisaseable_id = $sub->id;
        $new->realisaseable_type = SubKegiatan::class;
        $new->opd_id = $opd->id;
        $new->document_type = $documentType;
        $new->tahun = $tahun;
        $new->triwulan = $triwulan;
        $new->realisasi_keuangan = $realisasiVal;
        $new->realisasi_fisik = 0;
        $new->sisa_anggaran = $sisaAnggaran;
        $new->input_by = $inputBy;

        if (!$dryRun) {
            $new->save();
        }
        $created++;
    }
}

echo "Import realisasi TW{$triwulan} tahun {$tahun} selesai.\n";
echo "- Mode: " . ($dryRun ? 'DRY RUN (tanpa simpan)' : 'APPLY (tersimpan)') . "\n";
echo "- Sumber file: {$filePath}\n";
echo "- Total baris sumber: " . count($rows) . "\n";
echo "- Total baris valid tergrup: {$groupedSourceRowCount}\n";
echo "- Total grup OPD+SubKegiatan: " . count($groupedRows) . "\n";
echo "- Replace existing untuk OPD di file: " . ($replaceExistingForFileOpd ? 'ya' : 'tidak') . "\n";
echo "- Existing deleted: {$deletedExistingCount}\n";
echo "- Created: {$created}\n";
echo "- Updated: {$updated}\n";
echo "- Skipped invalid: {$skippedInvalid}\n";
echo "- Skipped missing OPD: {$skippedMissingOpd}\n";
echo "- Skipped missing SubKegiatan: {$skippedMissingSub}\n";

if (!empty($sampleMissingOpd)) {
    echo "\nContoh missing OPD:\n";
    foreach ($sampleMissingOpd as $line) {
        echo "  - {$line}\n";
    }
}

if (!empty($sampleMissingSub)) {
    echo "\nContoh missing SubKegiatan:\n";
    foreach ($sampleMissingSub as $line) {
        echo "  - {$line}\n";
    }
}
