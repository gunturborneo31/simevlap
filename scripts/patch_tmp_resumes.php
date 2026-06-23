<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\KomponenAnggaran;

$files = [
    __DIR__ . '/../tmp_resume_t10.html',
    __DIR__ . '/../tmp_resume_t10_after_patch.html',
];

function findNameForCode($code, $opdId = null)
{
    if (!$code) return null;
    $m = SubKegiatan::where('kode_rek', $code)->first();
    if ($m && stripos($m->nama_rincian, '(otomatis)') === false) return $m->nama_rincian;
    $m = Kegiatan::where('kode_rek', $code)->first();
    if ($m && stripos($m->nama_rincian, '(otomatis)') === false) return $m->nama_rincian;
    $m = Program::where('kode_rek', $code)->first();
    if ($m && stripos($m->nama_rincian, '(otomatis)') === false) return $m->nama_rincian;
    $m = KomponenAnggaran::where('kode', $code)->first();
    if ($m && stripos($m->nama_komponen, '(otomatis)') === false) return $m->nama_komponen;
    return null;
}

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $pattern1 = '/"kode":"([0-9\.]+)","nama":"([^"]*?)\(otomatis\)"/i';
    $pattern2 = '/&quot;kode&quot;:&quot;([0-9\.]+)&quot;,&quot;nama&quot;:&quot;([^&] *?)\(otomatis\)&quot;/i';
    $matches = [];
    if (preg_match_all($pattern1, $content, $m1, PREG_SET_ORDER)) {
        $matches = array_merge($matches, $m1);
    }
    // handle HTML-escaped JSON
    if (preg_match_all('/&quot;kode&quot;:&quot;([0-9\.]+)&quot;,&quot;nama&quot;:&quot;(.*?)\(otomatis\)&quot;/i', $content, $m2, PREG_SET_ORDER)) {
        $matches = array_merge($matches, $m2);
    }
    if (empty($matches)) {
        echo "No matches in {$file}\n";
        continue;
    }
    $bak = $file . '.bak';
    copy($file, $bak);
    foreach ($matches as $match) {
        $code = $match[1];
        $suggested = findNameForCode($code);
        if ($suggested) {
            // replace both raw JSON and HTML-escaped forms
            $search1 = '"kode":"' . $code . '","nama":"' . $match[2] . '(otomatis)"';
            $replace1 = '"kode":"' . $code . '","nama":"' . addslashes($suggested) . '"';
            $content = str_ireplace($search1, $replace1, $content);
            $search2 = '&quot;kode&quot;:&quot;' . $code . '&quot;,&quot;nama&quot;:&quot;' . $match[2] . '(otomatis)&quot;';
            $replace2 = '&quot;kode&quot;:&quot;' . $code . '&quot;,&quot;nama&quot;:&quot;' . htmlspecialchars($suggested, ENT_COMPAT) . '&quot;';
            $content = str_ireplace($search2, $replace2, $content);
            echo "Patched {$code} in {$file}\n";
        } else {
            // remove suffix occurrences
            $content = str_ireplace('(otomatis)', '', $content);
            echo "Removed suffix in {$file} for code {$code}\n";
        }
    }
    file_put_contents($file, $content);
}

echo "Done patching tmp resumes.\n";
