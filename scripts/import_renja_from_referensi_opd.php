<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$arg = $argv[1] ?? null;
$yearArg = $argv[2] ?? null; // optional year filter
if (!$arg) {
    echo "Usage: php scripts/import_renja_from_referensi_opd.php <opd_id|opd_kode> [year]\n";
    exit(1);
}

$opdId = null;
$opdKode = null;
if (is_numeric($arg)) {
    $opdId = (int) $arg;
    $opd = DB::table('opd')->where('id', $opdId)->first();
    if (!$opd) { echo "OPD id {$opdId} not found\n"; exit(1); }
    $opdKode = $opd->kode ?? null;
} else {
    $opdKode = $arg;
    $opd = DB::table('opd')->where('kode', $opdKode)->first();
    if (!$opd) { echo "OPD kode {$opdKode} not found\n"; exit(1); }
    $opdId = $opd->id;
}

$yearFilter = $yearArg ? (int)$yearArg : null;
$base = __DIR__ . '/../referensi/rkpd';
$file = $base . '/kegaiatan.json';
if (!file_exists($file)) { echo "Referensi file not found: {$file}\n"; exit(1); }
$content = file_get_contents($file);
$items = json_decode($content, true) ?? [];

$inserted = 0;
foreach ($items as $it) {
    $kodeSkpd = trim((string)($it['KODE_SKPD'] ?? ''));
    if ($kodeSkpd === '') continue;

    // flexible match: accept when referensi KODE_SKPD equals or contains the local opd kode, or vice versa,
    // or when their prefix segments match (first 3 segments)
    $matched = false;
    if ($opdKode === $kodeSkpd) {
        $matched = true;
    } elseif ($opdKode !== '' && (stripos($kodeSkpd, $opdKode) === 0 || stripos($opdKode, $kodeSkpd) === 0)) {
        $matched = true;
    } else {
        $a = array_values(array_filter(explode('.', $opdKode), fn($s) => $s !== ''));
        $b = array_values(array_filter(explode('.', $kodeSkpd), fn($s) => $s !== ''));
        if (count($a) >= 3 && count($b) >= 3) {
            if ($a[0] === $b[0] && $a[1] === $b[1] && $a[2] === $b[2]) {
                $matched = true;
            }
        }
    }

    if (!$matched) continue;

    $kode = trim((string)($it['KODE_KEGIATAN'] ?? ''));
    $nama = trim((string)($it['NAMA_KEGIATAN'] ?? ''));
    $tahun = isset($it['TAHUN']) ? (int)$it['TAHUN'] : null;

    if ($yearFilter !== null && $tahun !== null && $tahun !== $yearFilter) continue;

    if ($kode === '' || $nama === '') continue;

    $exists = DB::table('kegiatan')
        ->where('opd_id', $opdId)
        ->where('kode_rek', $kode)
        ->where('document_type', 'renja')
        ->first();

    if ($exists) {
        // update name if placeholder
        if (Str::contains($exists->nama_rincian ?? '', '(otomatis)') && $nama !== '') {
            DB::table('kegiatan')->where('id', $exists->id)->update(['nama_rincian' => $nama, 'tahun' => $tahun]);
            echo "Updated kegiatan id={$exists->id} kode={$kode}\n";
        }
        continue;
    }

    $insertedId = DB::table('kegiatan')->insertGetId([
        'opd_id' => $opdId,
        'program_id' => null,
        'kode_rek' => $kode,
        'nama_rincian' => $nama,
        'document_type' => 'renja',
        'tahun' => $tahun,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    echo "Inserted kegiatan id={$insertedId} kode={$kode} nama={$nama}\n";
    $inserted++;
}

echo "Done. inserted={$inserted}\n";
