<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$opd = DB::table('opd')->where('id',20)->first();
if (!$opd) { echo "OPD id 20 not found\n"; exit; }
echo json_encode($opd) . "\n";
