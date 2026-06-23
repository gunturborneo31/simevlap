<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\KomponenAnggaran;

$files = [
    __DIR__ . '/../page_json.txt',
    __DIR__ . '/../resume_payload.json',
    __DIR__ . '/../resume_payload_t6.json',
    __DIR__ . '/../resume_payload_t6_after.json',
    __DIR__ . '/../resume_payload_t6_after2.json',
    __DIR__ . '/../resume_payload_t6_final.json',
];

function findNameForCode($code, $opdId = null, $tahun = null)
{
    if (!$code) return null;
    // Try sub_kegiatan
    $q = SubKegiatan::where('kode_rek', $code);
    if ($opdId) $q->where('opd_id', $opdId);
    $m = $q->first();
    if ($m && $m->nama_rincian && stripos($m->nama_rincian, '(otomatis)') === false) return $m->nama_rincian;

    // kegiatan
    $q = Kegiatan::where('kode_rek', $code);
    if ($opdId) $q->where('opd_id', $opdId);
    $m = $q->first();
    if ($m && $m->nama_rincian && stripos($m->nama_rincian, '(otomatis)') === false) return $m->nama_rincian;

    // program
    $q = Program::where('kode_rek', $code);
    if ($opdId) $q->where('opd_id', $opdId);
    $m = $q->first();
    if ($m && $m->nama_rincian && stripos($m->nama_rincian, '(otomatis)') === false) return $m->nama_rincian;

    // komponen_anggaran by kode
    $q = KomponenAnggaran::where('kode', $code);
    if ($opdId) $q->where('opd_id', $opdId);
    $m = $q->first();
    if ($m && $m->nama_komponen && stripos($m->nama_komponen, '(otomatis)') === false) return $m->nama_komponen;

    return null;
}

function walkAndFix(&$data)
{
    if (is_array($data)) {
        foreach ($data as $k => &$v) {
            walkAndFix($v);
        }
    } elseif (is_object($data)) {
        foreach ($data as $k => &$v) {
            walkAndFix($v);
        }
    }

    // handle associative arrays with kode/opd_id/nama
    if (is_array($data) && isset($data['nama']) && is_string($data['nama']) && stripos($data['nama'], '(otomatis)') !== false) {
        $kode = $data['kode'] ?? ($data['kode_rek'] ?? null);
        $opdId = $data['opd_id'] ?? null;
        $tahun = $data['tahun'] ?? null;
        $found = findNameForCode($kode, $opdId, $tahun);
        if ($found) {
            $data['nama'] = $found;
        } else {
            // remove placeholder suffix if no better name
            $data['nama'] = trim(str_ireplace('(otomatis)', '', $data['nama']));
        }
    }
}

$modifiedFiles = [];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    if (stripos($content, '(otomatis)') === false) continue;
    $json = json_decode($content, true);
    if (!$json) {
        // try to extract JSON inside
        $json = @json_decode($content, true);
    }
    if (!$json) {
        // fallback: simple string replace
        $bak = $file . '.bak';
        copy($file, $bak);
        $new = str_ireplace(' (otomatis)', '', $content);
        file_put_contents($file, $new);
        $modifiedFiles[] = ['file' => $file, 'method' => 'simple_replace'];
        continue;
    }

    $bak = $file . '.bak';
    copy($file, $bak);
    walkAndFix($json);
    file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $modifiedFiles[] = ['file' => $file, 'method' => 'json_walk'];
}

echo "Done. modified=" . count($modifiedFiles) . " files.\n";
foreach ($modifiedFiles as $m) echo "- {$m['file']} via {$m['method']}\n";
