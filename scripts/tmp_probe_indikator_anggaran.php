<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$cols = Schema::getColumnListing('indikator_anggaran');
echo 'columns=' . implode(',', $cols) . PHP_EOL;

echo 'total=' . DB::table('indikator_anggaran')->count() . PHP_EOL;

$sample = DB::table('indikator_anggaran')->orderByDesc('updated_at')->limit(10)->get();
echo 'sample=' . $sample->count() . PHP_EOL;
foreach ($sample as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
