<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KomponenAnggaran;
use Illuminate\Support\Facades\DB;

$year = $argv[1] ?? null;
if (!$year) $year = 2026;

echo "Validate pagu differences for year: $year\n";

$types = ['dpa', 'renja'];

foreach ($types as $type) {
    echo "\nDocument type: $type\n";
    $kegQuery = KomponenAnggaran::query()
        ->where('document_type', $type)
        ->where('jenis', 'kegiatan')
        ->where('tahun', $year)
        ->orderBy('opd_id')
        ->orderBy('kode');

    $total = 0;
    $mismatchCount = 0;
    $rows = $kegQuery->get();
    foreach ($rows as $keg) {
        $total++;
        $children = KomponenAnggaran::query()
            ->where('document_type', $type)
            ->where('jenis', 'sub_kegiatan')
            ->where('parent_id', $keg->id)
            ->where('tahun', $year)
            ->get();

        $computed = (int) $children->sum(fn($c) => (int) ($c->pagu ?? 0));
        $reported = (int) ($keg->pagu ?? 0);
        if ($computed !== $reported) {
            $mismatchCount++;
            echo sprintf("OPD:%s Kode:%s ID:%d reported=%d computed=%d\n", $keg->opd_id, $keg->kode, $keg->id, $reported, $computed);
        }
    }

    echo "Total kegiatan scanned: $total\n";
    echo "Mismatches: $mismatchCount\n";
}

echo "\nValidation finished.\n";
