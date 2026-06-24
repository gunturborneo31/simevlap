<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $ctrl = app(\App\Http\Controllers\KomponenAnggaranController::class);
    $payload = $ctrl->buildRealisasiPayloadForOpd(20, 2026, 'realisasi', 'dpa', true);
    $data = $payload['data'] ?? [];
    $count = is_array($data) ? count($data) : ($data->count() ?? 0);
    echo "DATA_COUNT=" . $count . "\n";
    $samples = [];
    if ($count > 0) {
        $iter = is_array($data) ? $data : $data->take(5);
        foreach ($iter as $n => $node) {
            if (is_array($node)) {
                $samples[] = [
                    'kode' => $node['kode'] ?? null,
                    'jenis' => $node['jenis'] ?? null,
                    'opd_id' => $node['opd_id'] ?? null,
                    'pagu' => $node['pagu'] ?? null,
                    'realisasi_keys' => array_keys((array)($node['realisasi_tw'] ?? [])),
                ];
            } else {
                $samples[] = [
                    'kode' => $node->kode ?? null,
                    'jenis' => $node->jenis ?? null,
                    'opd_id' => $node->opd_id ?? null,
                    'pagu' => $node->pagu ?? null,
                    'realisasi_keys' => array_keys((array)($node->realisasi_tw ?? [])),
                ];
            }
        }
    }
    echo json_encode(['samples' => $samples], JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "ERROR:" . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
