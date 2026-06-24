<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $controller = new App\Http\Controllers\ResumeController();
    $rm = new ReflectionMethod($controller, 'getKonsistensiRpjmdRkpd');
    $rm->setAccessible(true);
    $data = $rm->invokeArgs($controller, ['perangkat-daerah', 2026, 'program']);

    $opdId = 20;
    $opd = App\Models\Opd::find($opdId);
    $ent = $opd ? $opd->nama : null;

    $arr = $data->toArray();
    $found = null;
    foreach ($arr as $r) {
        if (isset($r['entitas']) && trim($r['entitas']) === trim($ent)) {
            $found = $r;
            break;
        }
    }

    echo json_encode($found, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
