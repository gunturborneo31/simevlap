<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Program;

$opdId = 20;
$year = 2026;
$programs = Program::where('opd_id', $opdId)
    ->where('document_type', 'dpa')
    ->where('tahun', $year)
    ->get()
    ->map(function($p){
        return [
            'kode' => $p->kode_rek ?? $p->kode ?? null,
            'nama' => $p->nama_rincian ?? $p->nama ?? null,
            'dokumen' => $p->document_type ?? null,
            'tahun' => $p->tahun ?? null,
            'indikator' => $p->indikator ?? null,
        ];
    });

echo json_encode($programs, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
