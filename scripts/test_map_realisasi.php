<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $ctrlClass = \App\Http\Controllers\KomponenAnggaranController::class;
    $ctrl = app($ctrlClass);
    $refBuild = new ReflectionMethod($ctrlClass, 'buildRealisasiLookup');
    $refBuild->setAccessible(true);
    $realisasiRefLookup = [];
    $lookup = $refBuild->invokeArgs($ctrl, [[20], 2026, &$realisasiRefLookup]);

    $roots = \App\Models\KomponenAnggaran::where('document_type','dpa')->whereNull('parent_id')->where('opd_id',20)->orderBy('kode')->get();
    echo "ROOTS=".count($roots)."\n";
    $refMap = new ReflectionMethod($ctrlClass, 'mapKomponenWithReferenceNames');
    $refMap->setAccessible(true);
    $mapped = $refMap->invoke($ctrl, $roots, $lookup, $realisasiRefLookup);
    foreach ($mapped as $m) {
        echo json_encode([
            'kode' => $m->kode ?? null,
            'jenis' => $m->jenis ?? null,
            'opd_id' => $m->opd_id ?? null,
            'realisasi_keys' => array_keys((array)($m->realisasi_tw ?? [])),
        ]) . "\n";
    }
} catch (\Throwable $e) {
    echo 'ERROR:' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString();
}
