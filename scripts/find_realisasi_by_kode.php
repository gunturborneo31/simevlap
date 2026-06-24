<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$kode = $argv[1] ?? '1.01.01.2.01.0001';
$tahun = $argv[2] ?? 2026;

try {
    // find OPD by name
    $opd = \App\Models\Opd::where('nama', 'like', '%Pendidikan%')
        ->where('nama', 'like', '%Kebudayaan%')
        ->orWhere('nama', 'like', '%Pendidikan%')
        ->first();

    if (!$opd) {
        echo "OPD not found for name 'Pendidikan Kebudayaan'\n";
    } else {
        echo "OPD: " . $opd->id . " - " . $opd->nama . "\n";
    }

    $opdId = $opd?->id ?? null;

    // find master entries matching kode
    $foundMasters = [];
    $program = \App\Models\Program::where('kode_rek', $kode)->where('opd_id', $opdId)->where('tahun', $tahun)->first();
    if ($program) $foundMasters[] = ['type' => 'program', 'id' => $program->id];
    $kegiatan = \App\Models\Kegiatan::where('kode_rek', $kode)->where('opd_id', $opdId)->where('tahun', $tahun)->first();
    if ($kegiatan) $foundMasters[] = ['type' => 'kegiatan', 'id' => $kegiatan->id];
    $sub = \App\Models\SubKegiatan::where('kode_rek', $kode)->where('opd_id', $opdId)->where('tahun', $tahun)->first();
    if ($sub) $foundMasters[] = ['type' => 'sub_kegiatan', 'id' => $sub->id];

    echo "Masters found: " . count($foundMasters) . "\n";
    foreach ($foundMasters as $m) echo json_encode($m) . "\n";

    // build realisasi lookup via private method
    $ctrlClass = \App\Http\Controllers\KomponenAnggaranController::class;
    $ctrl = app($ctrlClass);
    $ref = new ReflectionMethod($ctrlClass, 'buildRealisasiLookup');
    $ref->setAccessible(true);
    $realisasiRef = [];
    $lookup = $ref->invokeArgs($ctrl, [[$opdId], $tahun, &$realisasiRef]);

    // search exact key matches
    $keysMatched = [];
    foreach ($lookup as $k => $v) {
        $parts = explode('|', $k);
        if (count($parts) < 3) continue;
        [$jenis, $kKode, $kOpd] = $parts;
        if ($kOpd != $opdId) continue;
        if (trim($kKode) === trim($kode) || str_starts_with($kKode, $kode) || str_starts_with($kode, $kKode)) {
            $keysMatched[$k] = $v;
        }
    }

    echo "Lookup matches: " . count($keysMatched) . "\n";
    foreach ($keysMatched as $k => $v) {
        echo $k . ' => ' . json_encode($v) . "\n";
    }

    // If no matches, show candidate keys for OPD
    if (empty($keysMatched)) {
        echo "No exact/prefix matches found in lookup. Showing some sample keys for OPD: \n";
        $count = 0;
        foreach ($lookup as $k => $v) {
            if (str_ends_with($k, '|' . $opdId)) {
                echo $k . "\n";
                $count++;
                if ($count > 20) break;
            }
        }
    }

    // also query realisasi table by matching realisaseable entries found earlier
    if (!empty($foundMasters)) {
        echo "Realisasi rows for matched masters:\n";
        foreach ($foundMasters as $m) {
            $typeClass = match ($m['type']) {
                'program' => \App\Models\Program::class,
                'kegiatan' => \App\Models\Kegiatan::class,
                'sub_kegiatan' => \App\Models\SubKegiatan::class,
            };
            $rows = \App\Models\Realisasi::where('realisaseable_type', $typeClass)
                ->where('realisaseable_id', $m['id'])
                ->where('opd_id', $opdId)
                ->where('tahun', $tahun)
                ->get();
            echo "Master {$m['type']} {$m['id']} -> count=" . $rows->count() . "\n";
            foreach ($rows as $r) echo json_encode(['id'=>$r->id,'tw'=>$r->triwulan,'keuangan'=>$r->realisasi_keuangan,'fisik'=>$r->realisasi_fisik]) . "\n";
        }
    }

} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
