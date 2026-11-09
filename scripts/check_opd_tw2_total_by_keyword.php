<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\Realisasi;
use App\Models\SubKegiatan;

$keywords = [
    'Pekerjaan Umum',
    'Penanaman Modal',
];

foreach ($keywords as $keyword) {
    $opds = Opd::query()->where('nama', 'like', '%' . $keyword . '%')->get();
    if ($opds->isEmpty()) {
        echo "Keyword '{$keyword}': tidak ada OPD\n";
        continue;
    }

    foreach ($opds as $opd) {
        $sum = (float) Realisasi::query()
            ->where('opd_id', $opd->id)
            ->where('tahun', 2026)
            ->where('triwulan', 2)
            ->where('document_type', 'dpa')
            ->where('realisaseable_type', SubKegiatan::class)
            ->sum('realisasi_keuangan');

        echo "{$opd->nama} => {$sum}\n";
    }
}
