<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $ctrl = app(\App\Http\Controllers\KomponenAnggaranController::class);
    $realisasiRefLookup = [];
    $refMethod = new ReflectionMethod(\App\Http\Controllers\KomponenAnggaranController::class, 'buildRealisasiLookup');
    $refMethod->setAccessible(true);
    $args = [[20], 2026, &$realisasiRefLookup];
    $lookup = $refMethod->invokeArgs($ctrl, $args);
    echo 'LOOKUP_COUNT=' . count($lookup) . "\n";
    $printed = 0;
    foreach ($lookup as $k => $v) {
        if (str_ends_with($k, '|20')) {
            echo $k . ' => ' . json_encode($v) . "\n";
            $printed++;
            if ($printed > 30) break;
        }
    }
    if ($printed === 0) echo "NO_KEYS_FOR_OPD20\n";
} catch (\Throwable $e) {
    echo 'ERROR:' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString();
}
