<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\Realisasi;
use App\Models\SubKegiatan;

function norm(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $s = str_replace([',', '.', '(', ')'], ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim((string) $s);
}

$target = [
    'Badan Penanggulangan Bencana Daerah' => 5907402395.00,
    'Badan Pengelola Keuangan dan Aset Daerah' => 211996416400.89,
    'Kecamatan Long Bagun' => 3093893795.00,
    'Kecamatan Laham' => 3058889420.00,
    'Dinas Pekerjaan Umum dan Penataan Ruang, Perumahan dan Kawasan Pemukiman' => 110071957975.00,
    'Badan Perencanaan Pembangunan, Penelitian dan Pengembangan Daerah' => 7900154392.00,
    'Badan Pendapatan Daerah' => 5823609324.00,
];

$missingFile = __DIR__ . '/../referensi/realisasi/2026/tw_2_missing_subkegiatan_grouped.json';
$missingPayload = json_decode((string) file_get_contents($missingFile), true);
$missingSummaryByOpd = [];
if (is_array($missingPayload) && isset($missingPayload['summary_by_opd']) && is_array($missingPayload['summary_by_opd'])) {
    foreach ($missingPayload['summary_by_opd'] as $row) {
        $missingSummaryByOpd[(string) ($row['kode_opd'] ?? '')] = $row;
    }
}

$sourceFile = __DIR__ . '/../referensi/realisasi/2026/tw_2.json';
$sourceRows = json_decode((string) file_get_contents($sourceFile), true);

$sourceByOpd = [];
if (is_array($sourceRows)) {
    foreach ($sourceRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $kodeOpd = trim((string) ($row['kode_opd'] ?? ''));
        if ($kodeOpd === '') {
            continue;
        }
        $sourceByOpd[$kodeOpd] = ($sourceByOpd[$kodeOpd] ?? 0.0) + (float) ($row['realisasi'] ?? 0);
    }
}

$opds = Opd::query()->get(['id', 'kode', 'nama']);
$opdByNorm = [];
foreach ($opds as $opd) {
    $opdByNorm[norm((string) $opd->nama)] = $opd;
}

$aliases = [
    norm('Badan Kepegawaian, Pendidikan dan Pelatihan') => norm('Badan Kepegawaian dan Pengembangan Sumber Daya Manusia'),
    norm('Dinas Penanaman Modal dan Pelayanan Perijinan Terpadu') => norm('Dinas Penanaman Modal dan Pelayanan Perizinan Terpadu'),
    norm('Dinas Pekerjaan Umum dan Penataan Ruang, Perumahan dan Kawasan Pemukiman') => norm('Dinas Pekerjaan Umum dan Penataan Ruang, Perumahan dan Kawasan Pemukiman'),
    norm('bagian organisasi') => norm('Bagian Organisasi'),
];

echo "Analisis 7 OPD: expected vs source vs DB vs missing\n";

foreach ($target as $name => $expected) {
    $needle = norm($name);
    $lookup = $aliases[$needle] ?? $needle;
    $opd = $opdByNorm[$lookup] ?? null;

    if (!$opd) {
        echo "- {$name} | OPD tidak ditemukan di master\n";
        continue;
    }

    $dbTotal = (float) Realisasi::query()
        ->where('opd_id', $opd->id)
        ->where('tahun', 2026)
        ->where('triwulan', 2)
        ->where('document_type', 'dpa')
        ->where('realisaseable_type', SubKegiatan::class)
        ->sum('realisasi_keuangan');

    $sourceTotal = (float) ($sourceByOpd[$opd->kode] ?? 0.0);
    $diffExpectedDb = $expected - $dbTotal;
    $diffSourceDb = $sourceTotal - $dbTotal;

    $missing = $missingSummaryByOpd[$opd->kode] ?? null;
    $missingGroups = (int) ($missing['count_missing_groups'] ?? 0);
    $missingRows = (int) ($missing['count_missing_rows'] ?? 0);
    $missingRealisasi = (float) ($missing['sum_missing_realisasi'] ?? 0.0);

    echo "- {$name}\n";
    echo "  kode_opd={$opd->kode}\n";
    echo "  expected={$expected}\n";
    echo "  source_tw2_json={$sourceTotal}\n";
    echo "  db_tw2={$dbTotal}\n";
    echo "  diff_expected_db={$diffExpectedDb}\n";
    echo "  diff_source_db={$diffSourceDb}\n";
    echo "  missing_groups={$missingGroups} missing_rows={$missingRows} missing_sum_realisasi={$missingRealisasi}\n";

    if (abs($diffExpectedDb - $missingRealisasi) < 0.0001) {
        echo "  verdict=selisih expected-db sama dengan missing_realisasi\n";
    } elseif ($missingGroups === 0 && abs($diffExpectedDb) > 0.0001) {
        echo "  verdict=tidak bisa dicocokkan dari missing (tidak ada missing untuk OPD ini)\n";
    } else {
        echo "  verdict=ada missing tapi tidak cukup/berbeda terhadap selisih expected-db\n";
    }
}
