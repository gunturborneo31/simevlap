<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Indikator (jenis_indikator=IKU): " . DB::table('indikator')->where('jenis_indikator', 'IKU')->count() . PHP_EOL;
echo "Pivot indikatorables (Tujuan): " . DB::table('indikatorables')->where('indicatorable_type', 'App\\Models\\Tujuan')->count() . PHP_EOL;
