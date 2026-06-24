<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cols = Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM indikator");
foreach ($cols as $c) {
    echo $c->Field . "\t" . $c->Type . "\n";
}
