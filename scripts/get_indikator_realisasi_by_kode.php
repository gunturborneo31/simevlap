<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$kode = $argv[1] ?? '1.01.01.2.01.0001';
$tahun = isset($argv[2]) ? (int)$argv[2] : 2026;
$triwulan = isset($argv[3]) ? (int)$argv[3] : 1;

try {
    // find the sub_kegiatan or kegiatan or program with this kode
    $sub = \App\Models\SubKegiatan::where('kode_rek', $kode)->first();
    $keg = null; $prog = null;
    if (!$sub) {
        $keg = \App\Models\Kegiatan::where('kode_rek', $kode)->first();
    } else {
        $keg = \App\Models\Kegiatan::find($sub->kegiatan_id ?? null);
    }
    if (!$keg) {
        $prog = \App\Models\Program::where('kode_rek', $kode)->first();
    } else {
        $prog = \App\Models\Program::find($keg->program_id ?? null);
    }

    echo "Search kode={$kode} tahun={$tahun} triwulan={$triwulan}\n";
    if ($sub) {
        echo "Found SubKegiatan id={$sub->id} opd_id={$sub->opd_id} nama={$sub->nama_rincian}\n";
        $type = \App\Models\SubKegiatan::class;
        $id = $sub->id;
    } elseif ($keg) {
        echo "Found Kegiatan id={$keg->id} opd_id={$keg->opd_id} nama={$keg->nama_rincian}\n";
        $type = \App\Models\Kegiatan::class;
        $id = $keg->id;
    } elseif ($prog) {
        echo "Found Program id={$prog->id} opd_id={$prog->opd_id} nama={$prog->nama_rincian}\n";
        $type = \App\Models\Program::class;
        $id = $prog->id;
    } else {
        echo "No master found for kode {$kode}\n";
        exit(0);
    }

    $rows = DB::table('indikatorables as ia')
        ->join('indikator as i', 'i.id', '=', 'ia.indikator_id')
        ->where('ia.indicatorable_type', $type)
        ->where('ia.indicatorable_id', $id)
        ->where('ia.tahun', $tahun)
        ->where(function($q) use ($triwulan) {
            $q->where('ia.triwulan', $triwulan)->orWhereNull('ia.triwulan');
        })
        ->select([
            'ia.id as pivot_id', 'ia.indikator_id', 'ia.target', 'ia.realisasi', 'ia.tahun', 'ia.triwulan', 'ia.catatan',
            'i.uraian as indikator_uraian', 'i.satuan as indikator_satuan'
        ])
        ->orderBy('i.uraian')
        ->get();

    echo "Found indikatorables: " . $rows->count() . "\n";
    foreach ($rows as $r) {
        echo json_encode([
            'pivot_id' => $r->pivot_id,
            'indikator_id' => $r->indikator_id,
            'indikator' => $r->indikator_uraian,
            'satuan' => $r->indikator_satuan,
            'target' => $r->target,
            'realisasi' => $r->realisasi,
            'triwulan' => $r->triwulan,
            'catatan' => $r->catatan,
        ]) . "\n";
    }

} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
