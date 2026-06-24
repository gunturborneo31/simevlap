<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$prefix = $argv[1] ?? '1.01.01';
$tahun = isset($argv[2]) ? (int)$argv[2] : 2026;
$triwulan = isset($argv[3]) ? (int)$argv[3] : 1;

use Illuminate\Support\Facades\DB;

try {
    echo "Searching indikatorables for masters with kode_rek starting with {$prefix} (tahun={$tahun}, tw={$triwulan})\n";
    $programs = \App\Models\Program::where('kode_rek', 'like', $prefix . '%')->where('tahun', $tahun)->get();
    $kegCount = 0; $subCount = 0; $found=0;
    foreach ($programs as $p) {
        echo "Program {$p->id} kode={$p->kode_rek} opd={$p->opd_id}\n";
        // program indikatorables
        $rows = DB::table('indikatorables as ia')
            ->join('indikator as i','i.id','=','ia.indikator_id')
            ->where('ia.indicatorable_type', \App\Models\Program::class)
            ->where('ia.indicatorable_id', $p->id)
            ->where('ia.tahun', $tahun)
            ->where(function($q) use ($triwulan){ $q->where('ia.triwulan',$triwulan)->orWhereNull('ia.triwulan'); })
            ->select('ia.*','i.uraian','i.satuan')
            ->get();
        if ($rows->count()>0) {
            echo " Program indikatorables: " . $rows->count() . "\n"; $found+=$rows->count();
            foreach ($rows as $r) echo json_encode(['id'=>$r->id,'indikator'=>$r->uraian,'target'=>$r->target,'realisasi'=>$r->realisasi,'triwulan'=>$r->triwulan]) . "\n";
        }
        // kegiatan under program
        $kegs = \App\Models\Kegiatan::where('kode_rek', 'like', $p->kode_rek . '%')->where('tahun', $tahun)->get();
        foreach ($kegs as $k) {
            $kegCount++;
            $rows = DB::table('indikatorables as ia')
                ->join('indikator as i','i.id','=','ia.indikator_id')
                ->where('ia.indicatorable_type', \App\Models\Kegiatan::class)
                ->where('ia.indicatorable_id', $k->id)
                ->where('ia.tahun', $tahun)
                ->where(function($q) use ($triwulan){ $q->where('ia.triwulan',$triwulan)->orWhereNull('ia.triwulan'); })
                ->select('ia.*','i.uraian','i.satuan')
                ->get();
            if ($rows->count()>0) { echo " Kegiatan {$k->id} indikatorables: " . $rows->count() . "\n"; $found+=$rows->count(); foreach($rows as $r) echo json_encode(['id'=>$r->id,'indikator'=>$r->uraian,'target'=>$r->target,'realisasi'=>$r->realisasi,'triwulan'=>$r->triwulan]) . "\n"; }
            // sub under kegiatan
            $subs = \App\Models\SubKegiatan::where('kode_rek','like',$k->kode_rek . '%')->where('tahun',$tahun)->get();
            foreach ($subs as $s) {
                $subCount++;
                $rows = DB::table('indikatorables as ia')
                    ->join('indikator as i','i.id','=','ia.indikator_id')
                    ->where('ia.indicatorable_type', \App\Models\SubKegiatan::class)
                    ->where('ia.indicatorable_id', $s->id)
                    ->where('ia.tahun', $tahun)
                    ->where(function($q) use ($triwulan){ $q->where('ia.triwulan',$triwulan)->orWhereNull('ia.triwulan'); })
                    ->select('ia.*','i.uraian','i.satuan')
                    ->get();
                if ($rows->count()>0) { echo "  SubKegiatan {$s->id} indikatorables: " . $rows->count() . "\n"; $found+=$rows->count(); foreach($rows as $r) echo json_encode(['id'=>$r->id,'indikator'=>$r->uraian,'target'=>$r->target,'realisasi'=>$r->realisasi,'triwulan'=>$r->triwulan]) . "\n"; }
            }
        }
    }
    echo "Scanned programs: " . $programs->count() . ", keg: {$kegCount}, sub: {$subCount}, found indikatorables: {$found}\n";
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
