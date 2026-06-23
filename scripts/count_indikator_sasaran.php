<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Indikator (jenis_indikator=sasaran): " . DB::table('indikator')->where('jenis_indikator', 'sasaran')->count() . PHP_EOL;
echo "Pivot indikatorables (Sasaran): " . DB::table('indikatorables')->where('indicatorable_type', 'App\\Models\\Sasaran')->count() . PHP_EOL;
