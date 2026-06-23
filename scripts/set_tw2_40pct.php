<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SubKegiatan;
use App\Models\Realisasi;
use Illuminate\Support\Facades\DB;

$tahun = 2026;
$triwulan = 2; // set TW2
$documentType = 'dpa';

$rows = SubKegiatan::where('document_type', $documentType)
            ->where('tahun', $tahun)
            ->whereNotNull('pagu')
            ->get();

$created = 0;
$updated = 0;

foreach ($rows as $sub) {
    $pagu = (float) $sub->pagu;
    if ($pagu <= 0) continue;

    $value = round($pagu * 0.40, 2);

    $existing = Realisasi::where('realisaseable_id', $sub->id)
                ->where('realisaseable_type', get_class($sub))
                ->where('tahun', $tahun)
                ->where('triwulan', $triwulan)
                ->where('document_type', $documentType)
                ->first();

    if ($existing) {
        $existing->realisasi_keuangan = $value;
        $existing->realisasi_fisik = 0;
        $existing->sisa_anggaran = $pagu - $value;
        $existing->save();
        $updated++;
    } else {
        $r = new Realisasi();
        $r->realisaseable_id = $sub->id;
        $r->realisaseable_type = get_class($sub);
        $r->opd_id = $sub->opd_id;
        $r->document_type = $documentType;
        $r->tahun = $tahun;
        $r->triwulan = $triwulan;
        $r->realisasi_keuangan = $value;
        $r->realisasi_fisik = 0;
        $r->sisa_anggaran = $pagu - $value;
        $r->input_by = 1;
        $r->save();
        $created++;
    }
}

echo "TW2 set. created={$created} updated={$updated} total_processed=" . $rows->count() . "\n";
