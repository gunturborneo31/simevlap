<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SubKegiatan;
use App\Models\Kegiatan;
use App\Models\Program;
use App\Models\Realisasi;
use Illuminate\Support\Facades\DB;

$tahun = 2026;
$documentType = 'dpa';

$types = [1, 2]; // triwulan 1 and 2

foreach ($types as $triwulan) {
    echo "Processing triwulan={$triwulan}\n";

    // Aggregate to Kegiatan (sum of sub_kegiatan realisasi)
    $kegSums = DB::table('realisasi')
        ->join('sub_kegiatan', 'realisasi.realisaseable_id', '=', 'sub_kegiatan.id')
        ->where('realisaseable_type', SubKegiatan::class)
        ->where('realisasi.tahun', $tahun)
        ->where('realisasi.triwulan', $triwulan)
        ->where('realisasi.document_type', $documentType)
        ->select('sub_kegiatan.kegiatan_id', DB::raw('SUM(realisasi_keuangan) as total'))
        ->groupBy('sub_kegiatan.kegiatan_id')
        ->get();

    $kCreated = 0; $kUpdated = 0;
    foreach ($kegSums as $row) {
        if (!$row->kegiatan_id) continue;
        $keg = Kegiatan::find($row->kegiatan_id);
        if (!$keg) continue;
        $total = (float) $row->total;

        $existing = Realisasi::where('realisaseable_type', Kegiatan::class)
            ->where('realisaseable_id', $keg->id)
            ->where('tahun', $tahun)
            ->where('triwulan', $triwulan)
            ->where('document_type', $documentType)
            ->first();

        if ($existing) {
            $existing->realisasi_keuangan = $total;
            $existing->realisasi_fisik = 0;
            $existing->sisa_anggaran = $keg->pagu ? ($keg->pagu - $total) : null;
            $existing->save();
            $kUpdated++;
        } else {
            $r = new Realisasi();
            $r->realisaseable_type = Kegiatan::class;
            $r->realisaseable_id = $keg->id;
            $r->opd_id = $keg->opd_id;
            $r->document_type = $documentType;
            $r->tahun = $tahun;
            $r->triwulan = $triwulan;
            $r->realisasi_keuangan = $total;
            $r->realisasi_fisik = 0;
            $r->sisa_anggaran = $keg->pagu ? ($keg->pagu - $total) : null;
            $r->input_by = 1;
            $r->save();
            $kCreated++;
        }
    }

    echo "Kegiatan: created={$kCreated} updated={$kUpdated}\n";

    // Aggregate to Program by summing Kegiatan realisasi (ensure program reflects children)
    $progSums = DB::table('realisasi')
        ->join('kegiatan as k', 'realisasi.realisaseable_id', '=', 'k.id')
        ->where('realisaseable_type', Kegiatan::class)
        ->where('realisasi.tahun', $tahun)
        ->where('realisasi.triwulan', $triwulan)
        ->where('realisasi.document_type', $documentType)
        ->select('k.program_id', DB::raw('SUM(realisasi_keuangan) as total'))
        ->groupBy('k.program_id')
        ->get();

    $pCreated = 0; $pUpdated = 0;
    foreach ($progSums as $row) {
        if (!$row->program_id) continue;
        $prog = Program::find($row->program_id);
        if (!$prog) continue;
        $total = (float) $row->total;

        $existing = Realisasi::where('realisaseable_type', Program::class)
            ->where('realisaseable_id', $prog->id)
            ->where('tahun', $tahun)
            ->where('triwulan', $triwulan)
            ->where('document_type', $documentType)
            ->first();

        if ($existing) {
            $existing->realisasi_keuangan = $total;
            $existing->realisasi_fisik = 0;
            $existing->sisa_anggaran = $prog->pagu ? ($prog->pagu - $total) : null;
            $existing->save();
            $pUpdated++;
        } else {
            $r = new Realisasi();
            $r->realisaseable_type = Program::class;
            $r->realisaseable_id = $prog->id;
            $r->opd_id = $prog->opd_id;
            $r->document_type = $documentType;
            $r->tahun = $tahun;
            $r->triwulan = $triwulan;
            $r->realisasi_keuangan = $total;
            $r->realisasi_fisik = 0;
            $r->sisa_anggaran = $prog->pagu ? ($prog->pagu - $total) : null;
            $r->input_by = 1;
            $r->save();
            $pCreated++;
        }
    }

    echo "Program: created={$pCreated} updated={$pUpdated}\n";
}

echo "Done aggregate.\n";
