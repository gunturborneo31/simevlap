<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = \App\Models\Tujuan::where('kode', 'tujuan1')->get();
if ($rows->isEmpty()) {
    echo "No rows found\n";
    exit;
}
foreach ($rows as $r) {
    echo "id:" . $r->id . " misi_id:" . $r->misi_id . " kode:" . $r->kode . " uraian:" . substr($r->uraian,0,120) . "\n";
}
