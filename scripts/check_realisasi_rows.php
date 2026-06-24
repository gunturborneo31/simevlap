<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $rows = \App\Models\Realisasi::where('opd_id',20)->where('tahun',2026)->get();
    echo 'COUNT=' . $rows->count() . "\n";
    foreach ($rows as $r) {
        $type = $r->realisaseable_type ?? '';
        echo json_encode([
            'id' => $r->id,
            'type' => $type,
            'realisaseable_id' => $r->realisaseable_id,
            'triwulan' => $r->triwulan,
            'keuangan' => $r->realisasi_keuangan,
            'fisik' => $r->realisasi_fisik,
        ]) . "\n";
    }
} catch (\Throwable $e) {
    echo 'ERROR:' . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
