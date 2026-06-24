<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$threshold = $argv[1] ?? '85';
$apply = in_array('--apply', $argv);
$threshold = (int)$threshold;

echo "Running align_renja_to_dpa_db (threshold={$threshold}%)" . ($apply?" [apply]":" [dry-run]") . "\n";

// get OPD ids that have both dpa and renja components
$opdRows = DB::select("SELECT opd_id, SUM(document_type='dpa') as dpa_count, SUM(document_type='renja') as renja_count FROM komponen_anggaran GROUP BY opd_id HAVING dpa_count>0 AND renja_count>0");
$opdIds = array_map(fn($r)=>$r->opd_id, $opdRows);

if (empty($opdIds)) { echo "No OPDs with both DPA and RENJA components found.\n"; exit(0); }

$totalMappings = 0;
$totalChanged = 0;
foreach ($opdIds as $opdId) {
    echo "Processing OPD id: $opdId\n";
    // get distinct DPA indikator names for this opd
    $dpaRows = DB::table('indikator_anggaran')
        ->join('komponen_anggaran','indikator_anggaran.komponen_anggaran_id','=','komponen_anggaran.id')
        ->where('komponen_anggaran.opd_id',$opdId)
        ->where('komponen_anggaran.document_type','dpa')
        ->select('indikator_anggaran.nama_indikator')
        ->distinct()
        ->get()
        ->pluck('nama_indikator')
        ->toArray();

    $renjaRows = DB::table('indikator_anggaran')
        ->join('komponen_anggaran','indikator_anggaran.komponen_anggaran_id','=','komponen_anggaran.id')
        ->where('komponen_anggaran.opd_id',$opdId)
        ->where('komponen_anggaran.document_type','renja')
        ->select('indikator_anggaran.nama_indikator')
        ->distinct()
        ->get()
        ->pluck('nama_indikator')
        ->toArray();

    if (empty($dpaRows) || empty($renjaRows)) continue;

    // normalize function
    $norm = function($s){
        $s = (string)$s;
        $s = preg_replace('/[[:space:]]+/', ' ', $s);
        $s = trim($s);
        $s = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $s));
        $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    };

    $dpaIndex = [];
    foreach ($dpaRows as $d) {
        $dpaIndex[] = ['raw'=>$d,'norm'=>$norm($d)];
    }

    foreach ($renjaRows as $r) {
        $rNorm = $norm($r);
        $best = null; $bestScore = 0;
        foreach ($dpaIndex as $d) {
            similar_text($rNorm, $d['norm'], $percent);
            if ($percent > $bestScore) { $bestScore=$percent; $best=$d; }
        }
        if ($best && $bestScore >= $threshold) {
            // skip if identical
            if ($best['norm'] === $rNorm) continue;
            $totalMappings++;
            echo "OPD {$opdId} mapping ({$bestScore}%): '{$r}' -> '{$best['raw']}'\n";
            if ($apply) {
                $count = DB::update("UPDATE indikator_anggaran ia JOIN komponen_anggaran ka ON ia.komponen_anggaran_id=ka.id SET ia.nama_indikator = REPLACE(ia.nama_indikator, ?, ?) WHERE ka.opd_id=? AND ka.document_type='renja' AND ia.nama_indikator LIKE ?", [$r, $best['raw'], $opdId, '%'.$r.'%']);
                echo "  changed rows: {$count}\n";
                $totalChanged += $count;
            }
        }
    }
}

if ($apply) echo "Done. Total mappings applied: {$totalMappings}, rows changed: {$totalChanged}\n";
else echo "Dry-run complete. Proposed mappings: {$totalMappings}\n";
