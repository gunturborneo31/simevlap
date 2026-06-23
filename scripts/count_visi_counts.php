<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'visi:' . \App\Models\Visi::count() . PHP_EOL;
echo 'misi:' . \App\Models\Misi::count() . PHP_EOL;
echo 'tujuan:' . \App\Models\Tujuan::count() . PHP_EOL;
echo 'sasaran:' . \App\Models\Sasaran::count() . PHP_EOL;
echo 'strategi:' . \App\Models\Strategi::count() . PHP_EOL;
