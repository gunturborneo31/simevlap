<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use Illuminate\Support\Facades\Schema;

$tahun = isset($argv[1]) ? (int)$argv[1] : (int) date('Y');
$dryRun = in_array('--dry', $argv, true);

echo "Fixing pagu zeros for tahun={$tahun} (dryRun=" . ($dryRun ? 'yes' : 'no') . ")\n";

$changes = ['kegiatan' => [], 'program' => []];

DB::beginTransaction();
try {
    // 1) Kegiatan: for each kegiatan with pagu == 0 (if column exists) otherwise scan all kegiatan
    $kegiatanHasPagu = Schema::hasColumn('kegiatan', 'pagu');
    if ($kegiatanHasPagu) {
        $kegQuery = Kegiatan::where('tahun', $tahun)->where(function($q){ $q->whereNull('pagu')->orWhere('pagu', 0); });
        $kegCount = $kegQuery->count();
        echo "Found kegiatan with pagu==0: {$kegCount}\n";
    } else {
        $kegQuery = Kegiatan::where('tahun', $tahun);
        $kegCount = $kegQuery->count();
        echo "Kegiatan table has no pagu column; scanning all kegiatan: {$kegCount}\n";
    }
    foreach ($kegQuery->get() as $keg) {
        $sum = (float) SubKegiatan::where('kegiatan_id', $keg->id)->where('tahun', $tahun)->sum('pagu');
        if ($sum > 0) {
            $changes['kegiatan'][] = ['id' => $keg->id, 'old' => ($kegHas = ($kegiatanHasPagu ? $keg->pagu : null)), 'new' => $sum, 'opd_id' => $keg->opd_id, 'kode_rek' => $keg->kode_rek];
            if (!$dryRun && $kegiatanHasPagu) {
                $keg->pagu = $sum;
                $keg->save();
            }
        }
    }

    // 2) Program: for each program with pagu == 0, sum its kegiatan pagu (after above updates)
    $progQuery = Program::where('tahun', $tahun)->where(function($q){ $q->whereNull('pagu')->orWhere('pagu', 0); });
    $progCount = $progQuery->count();
    echo "Found program with pagu==0: {$progCount}\n";

    foreach ($progQuery->get() as $prog) {
        // Sum kegiatan pagu; if kegiatan table lacks pagu, sum sub_kegiatan pagu grouped by kegiatan.program_id
        if (Schema::hasColumn('kegiatan', 'pagu')) {
            $sum = (float) Kegiatan::where('program_id', $prog->id)->where('tahun', $tahun)->sum('pagu');
        } else {
            // sum sub_kegiatan pagu for all sub_kegiatan whose kegiatan belongs to this program
            $sum = (float) DB::table('sub_kegiatan')
                ->join('kegiatan', 'sub_kegiatan.kegiatan_id', '=', 'kegiatan.id')
                ->where('kegiatan.program_id', $prog->id)
                ->where('sub_kegiatan.tahun', $tahun)
                ->sum('sub_kegiatan.pagu');
        }

        if ($sum > 0) {
            $changes['program'][] = ['id' => $prog->id, 'old' => $prog->pagu, 'new' => $sum, 'opd_id' => $prog->opd_id, 'kode_rek' => $prog->kode_rek];
            if (!$dryRun) {
                $prog->pagu = $sum;
                $prog->save();
            }
        }
    }

    if ($dryRun) {
        DB::rollBack();
        echo "Dry run - no DB changes applied.\n";
    } else {
        DB::commit();
        echo "DB updates committed.\n";
    }

} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}

// Print report
echo "-- Report --\n";
echo "Kegiatan updated: " . count($changes['kegiatan']) . "\n";
foreach ($changes['kegiatan'] as $c) {
    echo "Kegiatan {$c['id']} opd={$c['opd_id']} kode={$c['kode_rek']} pagu: {$c['old']} => {$c['new']}\n";
}

echo "Program updated: " . count($changes['program']) . "\n";
foreach ($changes['program'] as $c) {
    echo "Program {$c['id']} opd={$c['opd_id']} kode={$c['kode_rek']} pagu: {$c['old']} => {$c['new']}\n";
}

echo "Done.\n";
