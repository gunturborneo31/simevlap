<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Program;
try {
    $rows = Program::where('jenis_program', 'program-aksi')->get()->map(fn($r) => ['id' => $r->id, 'opd_id' => $r->opd_id, 'kode_rek' => $r->kode_rek, 'nama_rincian' => $r->nama_rincian])->all();
    echo json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
