<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ids = [1111,1112,1113,1133,1145,1156,325,368];
foreach ($ids as $id) {
    $row = \App\Models\SubKegiatan::find($id);
    if ($row) {
        echo "SubKegiatan {$id}: kode_rek=".($row->kode_rek ?? '(null)')." opd_id=".($row->opd_id ?? 'null')." nama=".($row->nama_rincian ?? '')."\n";
        continue;
    }
    $row2 = \App\Models\Kegiatan::find($id);
    if ($row2) {
        echo "Kegiatan {$id}: kode_rek=".($row2->kode_rek ?? '(null)')." opd_id=".($row2->opd_id ?? 'null')." nama=".($row2->nama_rincian ?? '')."\n";
        continue;
    }
    $row3 = \App\Models\Program::find($id);
    if ($row3) {
        echo "Program {$id}: kode_rek=".($row3->kode_rek ?? '(null)')." opd_id=".($row3->opd_id ?? 'null')." nama=".($row3->nama_rincian ?? '')."\n";
        continue;
    }
    echo "ID {$id} not found in Program/Kegiatan/SubKegiatan\n";
}
