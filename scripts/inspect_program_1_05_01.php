<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Program;

$programs = Program::where('kode_rek', '1.05.01')->get();
foreach ($programs as $p) {
    echo "id={$p->id} opd_id={$p->opd_id} kode_rek={$p->kode_rek} nama_rincian={$p->nama_rincian}\n";
}

if ($programs->isEmpty()) echo "No program found for kode_rek=1.05.01\n";
