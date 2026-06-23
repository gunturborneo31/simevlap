<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('kegiatan')
    ->whereNull('program_id')
    ->select('id','kode_rek','opd_id')
    ->get();

$updated = 0;
foreach ($rows as $r) {
    $parts = explode('.', $r->kode_rek);
    if (count($parts) >= 3) {
        $prefix3 = $parts[0] . '.' . $parts[1] . '.' . $parts[2];
        $prog = DB::table('program')->where('kode_rek', $prefix3)->where(function($q) use ($r){
            if ($r->opd_id) $q->where('opd_id', $r->opd_id);
        })->first();
        if (!$prog) {
            // fallback: try first two parts
            $prefix2 = $parts[0] . '.' . $parts[1];
            $prog = DB::table('program')->where('kode_rek', $prefix2)->where(function($q) use ($r){
                if ($r->opd_id) $q->where('opd_id', $r->opd_id);
            })->first();
        }

        if ($prog) {
            DB::table('kegiatan')->where('id', $r->id)->update(['program_id' => $prog->id]);
            $updated++;
        }
    }
}

echo "fill_kegiatan_program_id done. updated={$updated}\n";
