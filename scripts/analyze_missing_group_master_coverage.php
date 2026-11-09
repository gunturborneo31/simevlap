<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SubKegiatan;

$filePath = __DIR__ . '/../referensi/realisasi/2026/tw_2_missing_subkegiatan_grouped.json';

$json = json_decode((string) file_get_contents($filePath), true);
if (!is_array($json) || !isset($json['groups']) || !is_array($json['groups'])) {
    echo "File grouped missing tidak valid: {$filePath}\n";
    exit(1);
}

$codes = [];
foreach ($json['groups'] as $g) {
    $code = trim((string) ($g['kode_sub_kegiatan'] ?? ''));
    if ($code === '') {
        continue;
    }
    $codes[$code] = true;
}

$stats = [];
foreach (array_keys($codes) as $code) {
    $count = SubKegiatan::query()->where('kode_rek', $code)->count();
    $stats[] = ['kode' => $code, 'count' => $count];
}

$exists = array_values(array_filter($stats, static fn ($x) => $x['count'] > 0));
$missing = array_values(array_filter($stats, static fn ($x) => $x['count'] === 0));

echo "Analisis master coverage untuk grouped missing\n";
echo "- Unique kode_sub_kegiatan missing: " . count($stats) . "\n";
echo "- Ada di master (OPD manapun): " . count($exists) . "\n";
echo "- Tidak ada sama sekali di master: " . count($missing) . "\n";

if (!empty($missing)) {
    echo "- Daftar tidak ada sama sekali:\n";
    foreach ($missing as $m) {
        echo "  - {$m['kode']}\n";
    }
}
