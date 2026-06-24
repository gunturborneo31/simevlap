<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$rows = \App\Models\KomponenAnggaran::where('document_type','dpa')->where('opd_id',20)->whereNull('parent_id')->get();
echo 'COUNT=' . $rows->count() . "\n";
foreach ($rows as $r) {
    echo json_encode(['id' => $r->id, 'kode' => $r->kode, 'jenis' => $r->jenis, 'opd_id' => $r->opd_id, 'pagu' => $r->pagu]) . "\n";
}
