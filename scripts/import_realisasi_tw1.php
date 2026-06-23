<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\SubKegiatan;
use App\Models\Realisasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$path = __DIR__ . '/../referensi/pusat/realisasi.json';
if (!file_exists($path)) {
    echo "File not found: $path\n";
    exit(1);
}

$json = file_get_contents($path);
$rows = json_decode($json, true);
if (!is_array($rows)) {
    echo "Invalid JSON in $path\n";
    exit(1);
}

$user = User::first();
$inputBy = $user?->id ?? 1;

$created = 0;
$updated = 0;
$skipped = 0;

foreach ($rows as $i => $row) {
    $kodeOpd = trim($row['kode_opd'] ?? '');
    $kodeSub = trim($row['kode_sub'] ?? '');
    $realisasiVal = isset($row['realisasi']) ? (float) $row['realisasi'] : null;
    $modal = isset($row['modal']) ? (float) $row['modal'] : null;

    if ($kodeOpd === '' || $kodeSub === '' || $realisasiVal === null) {
        $skipped++;
        continue;
    }

    $opd = Opd::where('kode', $kodeOpd)->first();
    if (!$opd) {
        echo "[{$i}] OPD not found: {$kodeOpd}\n";
        $skipped++;
        continue;
    }

    $sub = SubKegiatan::where('opd_id', $opd->id)->where('kode_rek', $kodeSub)->first();
    if (!$sub) {
        echo "[{$i}] SubKegiatan not found: {$kodeSub} (opd_id={$opd->id})\n";
        $skipped++;
        continue;
    }

    // target fields
    $tahun = 2026;
    $triwulan = 1; // TW1
    $documentType = 'dpa';

    $existing = Realisasi::where('realisaseable_id', $sub->id)
                ->where('realisaseable_type', SubKegiatan::class)
                ->where('tahun', $tahun)
                ->where('triwulan', $triwulan)
                ->where('document_type', $documentType)
                ->first();

    if ($existing) {
        $existing->opd_id = $opd->id;
        $existing->realisasi_keuangan = $realisasiVal;
        $existing->realisasi_fisik = 0;
        $existing->sisa_anggaran = $modal !== null ? ($modal - $realisasiVal) : null;
        $existing->input_by = $inputBy;
        $existing->save();
        $updated++;
    } else {
        $r = new Realisasi();
        $r->realisaseable_id = $sub->id;
        $r->realisaseable_type = SubKegiatan::class;
        $r->opd_id = $opd->id;
        $r->document_type = $documentType;
        $r->tahun = $tahun;
        $r->triwulan = $triwulan;
        $r->realisasi_keuangan = $realisasiVal;
        $r->realisasi_fisik = 0;
        $r->sisa_anggaran = $modal !== null ? ($modal - $realisasiVal) : null;
        $r->input_by = $inputBy;
        $r->save();
        $created++;
    }
}

echo "Done. created={$created} updated={$updated} skipped={$skipped}\n";
