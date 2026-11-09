<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Opd;
use App\Models\Realisasi;
use App\Models\SubKegiatan;

function normalize_name(string $name): string
{
    $name = mb_strtolower($name, 'UTF-8');
    $name = str_replace([',', '.', '(', ')'], ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim((string) $name);
}

$expected = [
    'Badan Penanggulangan Bencana Daerah' => 5907402395.00,
    'Dinas Lingkungan Hidup' => 5906244032.00,
    'Badan Pengelola Keuangan dan Aset Daerah' => 211996416400.89,
    'Kecamatan Long Apari' => 3654765211.00,
    'Kecamatan Long Pahangai' => 4293482561.00,
    'Kecamatan Long Bagun' => 3093893795.00,
    'Kecamatan Laham' => 3058889420.00,
    'Kecamatan Long Hubung' => 3297469490.00,
    'Badan Kesatuan Bangsa dan Politik' => 4687841952.00,
    'Dinas Pendidikan dan Kebudayaan' => 61434009750.00,
    'Dinas Kesehatan, Pengendalian Penduduk dan KB' => 62058898989.00,
    'Dinas Pekerjaan Umum dan Penataan Ruang, Perumahan dan Kawasan Pemukiman' => 110071957975.00,
    'Dinas Sosial, Pemberdayaan Perempuan Perlindungan Anak' => 5446756231.00,
    'Dinas Kependudukan dan Pencatatan Sipil' => 4376894861.00,
    'Dinas Pemberdayaan Masyarakat dan Pemerintahan Kampung' => 48763425010.00,
    'Dinas Komunikasi dan Informatika, Statistik, dan Persandian' => 4120672183.00,
    'Dinas Penanaman Modal dan Pelayanan Perijinan Terpadu' => 3527703399.00,
    'Dinas Pariwisata, Pemuda dan Olahraga' => 4146519940.00,
    'Dinas Ketahanan Pangan dan Pertanian' => 8326595924.00,
    'Sekretariat DPRD' => 20225456318.00,
    'Badan Perencanaan Pembangunan, Penelitian dan Pengembangan Daerah' => 7900154392.00,
    'Badan Pendapatan Daerah' => 5823609324.00,
    'Badan Kepegawaian, Pendidikan dan Pelatihan' => 6755842020.00,
    'Badan Pengelola Perbatasan Daerah' => 3675071724.00,
    'bagian organisasi' => 1334273346.00,
    'Bagian Perekonomian dan Sumber Daya Alam' => 1186540682.00,
    'Bagian Kesejahteraan Rakyat' => 7911324062.00,
    'Bagian Umum' => 29455158534.00,
    'Bagian Protokol dan Komunikasi Pimpinan' => 1560008100.00,
    'Bagian Pengadaan Barang Dan Jasa' => 535283000.00,
    'Bagian Hukum' => 590553525.00,
    'Bagian Administrasi Pembangunan' => 1495076854.00,
    'Bagian Pemerintahan' => 1222247666.00,
    'Satuan Polisi Pamong Praja' => 9145437510.00,
    'Inspektorat' => 5074239603.00,
    'Dinas Perhubungan' => 8994248065.00,
];

$aliases = [
    normalize_name('Badan Kepegawaian, Pendidikan dan Pelatihan') => normalize_name('Badan Kepegawaian dan Pengembangan Sumber Daya Manusia'),
    normalize_name('Dinas Penanaman Modal dan Pelayanan Perijinan Terpadu') => normalize_name('Dinas Penanaman Modal dan Pelayanan Perizinan Terpadu'),
    normalize_name('Dinas Pekerjaan Umum dan Penataan Ruang, Perumahan dan Kawasan Pemukiman') => normalize_name('Dinas Pekerjaan Umum dan Penataan Ruang, Perumahan dan Kawasan Permukiman'),
    normalize_name('bagian organisasi') => normalize_name('Bagian Organisasi'),
];

$opds = Opd::query()->get(['id', 'nama']);

$dbTotalsByNormName = [];
foreach ($opds as $opd) {
    $sum = (float) Realisasi::query()
        ->where('opd_id', $opd->id)
        ->where('tahun', 2026)
        ->where('triwulan', 2)
        ->where('document_type', 'dpa')
        ->where('realisaseable_type', SubKegiatan::class)
        ->sum('realisasi_keuangan');

    $dbTotalsByNormName[normalize_name((string) $opd->nama)] = $sum;
}

$match = 0;
$mismatch = 0;
$notFound = 0;

echo "Validasi daftar user vs DB realisasi TW2 (2026, dpa, SubKegiatan)\n";

foreach ($expected as $name => $expectedValue) {
    $norm = normalize_name($name);
    $normLookup = $aliases[$norm] ?? $norm;

    if (!array_key_exists($normLookup, $dbTotalsByNormName)) {
        $notFound++;
        echo "- NOT FOUND | {$name} | expected={$expectedValue}\n";
        continue;
    }

    $dbValue = $dbTotalsByNormName[$normLookup];
    $diff = $expectedValue - $dbValue;

    if (abs($diff) < 0.0001) {
        $match++;
        continue;
    }

    $mismatch++;
    echo "- MISMATCH | {$name} | expected={$expectedValue} | db={$dbValue} | diff={$diff}\n";
}

echo "- Match: {$match}\n";
echo "- Mismatch: {$mismatch}\n";
echo "- Not found: {$notFound}\n";
