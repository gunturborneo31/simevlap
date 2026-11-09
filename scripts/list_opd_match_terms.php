<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;

$terms = [
    'pekerjaan umum',
    'penanaman',
    'perijinan',
    'perizinan',
];

foreach (Opd::query()->get(['nama']) as $opd) {
    $name = (string) $opd->nama;
    $lower = mb_strtolower($name, 'UTF-8');
    foreach ($terms as $term) {
        if (str_contains($lower, $term)) {
            echo $name . "\n";
            break;
        }
    }
}
