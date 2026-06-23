<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\KomponenAnggaran;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;

$opdId = $argv[1] ?? '5'; // pass 'all' to skip opd filter
$tahun = $argv[2] ?? '2026'; // pass 'all' to skip tahun filter

$report = [];

function tryFindKomponenName($kode)
{
    if (!$kode) return null;
    $k = KomponenAnggaran::where('kode', $kode)
            ->where('nama_komponen', 'NOT LIKE', '%(otomatis)%')
            ->orderByDesc('pagu')
            ->first();
    if ($k) return $k->nama_komponen;

    $k = KomponenAnggaran::where('kode', $kode)
            ->orderByDesc('pagu')
            ->first();
    return $k?->nama_komponen;
}

$tables = [
    ['model' => Program::class, 'table' => 'program', 'field' => 'nama_rincian', 'kode_field' => 'kode_rek'],
    ['model' => Kegiatan::class, 'table' => 'kegiatan', 'field' => 'nama_rincian', 'kode_field' => 'kode_rek'],
    ['model' => SubKegiatan::class, 'table' => 'sub_kegiatan', 'field' => 'nama_rincian', 'kode_field' => 'kode_rek'],
];

$total = 0;
foreach ($tables as $t) {
    $q = DB::table($t['table'])->where($t['field'], 'like', '%(otomatis)%');
    if ($opdId !== 'all' && $opdId !== null && $opdId !== '') {
        $q->where('opd_id', $opdId);
    }
    if ($tahun !== 'all' && $tahun !== null && $tahun !== '') {
        $q->where('tahun', $tahun);
    }
    $rows = $q->get();

    foreach ($rows as $row) {
        $total++;
        $kode = $row->{$t['kode_field']} ?? null;
        $current = $row->{$t['field']} ?? null;
        $found = tryFindKomponenName($kode);

        $entry = [
            'table' => $t['table'],
            'id' => $row->id,
            'kode_rek' => $kode,
            'current' => $current,
            'suggested' => $found,
        ];

        if ($found) {
            // update model
            $modelClass = $t['model'];
            $m = $modelClass::find($row->id);
            if ($m) {
                $m->{$t['field']} = $found;
                $m->save();
                $entry['status'] = 'updated';
            } else {
                $entry['status'] = 'model_not_found';
            }
        } else {
            $entry['status'] = 'no_suggestion';
        }

        $report[] = $entry;
    }
}

$outPath = __DIR__ . '/fix_otomatis_report.json';
file_put_contents($outPath, json_encode(['timestamp' => date('c'), 'opd_id' => $opdId, 'tahun' => $tahun, 'total_checked' => $total, 'rows' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Done. total_checked={$total}. Report: {$outPath}\n";
