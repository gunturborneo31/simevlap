<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Visi;
use App\Models\Misi;
use App\Models\Tujuan;
use App\Models\Sasaran;
use App\Models\Strategi;

$visis = Visi::with(['misi.tujuan.sasaran.strategi'])->get();

foreach ($visis as $visi) {
    echo "Visi: {$visi->kode} - " . (strlen($visi->uraian) > 80 ? substr($visi->uraian,0,77).'...' : $visi->uraian) . PHP_EOL;
    foreach ($visi->misi as $misi) {
        echo "  Misi: {$misi->kode} - " . (strlen($misi->uraian) > 80 ? substr($misi->uraian,0,77).'...' : $misi->uraian) . PHP_EOL;
        foreach ($misi->tujuan as $tujuan) {
            echo "    Tujuan: {$tujuan->kode} - " . (strlen($tujuan->uraian) > 80 ? substr($tujuan->uraian,0,77).'...' : $tujuan->uraian) . PHP_EOL;
            foreach ($tujuan->sasaran as $sasaran) {
                echo "      Sasaran: {$sasaran->kode} - " . (strlen($sasaran->uraian) > 80 ? substr($sasaran->uraian,0,77).'...' : $sasaran->uraian) . PHP_EOL;
                echo "        Strategi count: " . $sasaran->strategi->count() . PHP_EOL;
            }
        }
    }
}
