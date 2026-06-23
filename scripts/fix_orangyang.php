<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$root = realpath(__DIR__ . '/..');
$excludeDirs = ['vendor', 'storage', '.git', 'node_modules'];
$filesScanned = 0;
$filesChanged = 0;
$replacementsInFiles = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    // skip excluded dirs
    $skip = false;
    foreach ($excludeDirs as $d) {
        if (stripos($rel, $d . DIRECTORY_SEPARATOR) === 0 || stripos($rel, DIRECTORY_SEPARATOR . $d . DIRECTORY_SEPARATOR) !== false) { $skip = true; break; }
    }
    if ($skip) continue;
    // limit to textual files
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $allowExt = ['php','json','html','htm','txt','js','vue','css','md'];
    if (!in_array($ext, $allowExt)) continue;

    $filesScanned++;
    $content = @file_get_contents($path);
    if ($content === false) continue;
    // check for any case-insensitive 'orang yang' occurrences
    if (stripos($content, 'orang yang') === false && stripos($content, 'Orang yang') === false) continue;
    $bak = $path . '.bak_orang yang';
    if (!file_exists($bak)) copy($path, $bak);

    $new = $content;
    $count = 0;
    // 1) fix when attached to previous char: e.g. "Jumlah orang yang..." -> "Jumlah orang yang ..."
    $new = preg_replace_callback('/([A-Za-z0-9])([oO]rangyang)/u', function($m) use (&$count) {
        $count++;
        $prefix = $m[1];
        $match = $m[2];
        $firstChar = $match[0];
        $replacement = $prefix . ' ' . (ctype_upper($firstChar) ? 'Orang yang' : 'orang yang');
        return $replacement;
    }, $new);

    // 2) fix remaining standalone orang yang (case-insensitive)
    $new = preg_replace_callback('/([oO]rangyang)/u', function($m) use (&$count) {
        $count++;
        $match = $m[1];
        $firstChar = $match[0];
        return ctype_upper($firstChar) ? 'Orang yang' : 'orang yang';
    }, $new);

    // compute additional occurrences in original if any (best-effort)
    // write back if changed
    if ($new !== $content) {
        file_put_contents($path, $new);
        echo "Patched file: {$rel} (replacements={$count})\n";
        $filesChanged++;
        $replacementsInFiles += $count;
    }
}

// Database updates
$dbUpdated = 0;
try {
    $dbUpdated += DB::update("UPDATE indikator_anggaran SET nama_indikator = REPLACE(nama_indikator, ?, ?) WHERE nama_indikator LIKE ?", ['Orang yang', 'Orang yang', '%Orang yang%']);
} catch (Throwable $e) {
    echo "DB update indikator_anggaran failed: " . $e->getMessage() . "\n";
}
try {
    $dbUpdated += DB::update("UPDATE komponen_anggaran SET nama_komponen = REPLACE(nama_komponen, ?, ?) WHERE nama_komponen LIKE ?", ['Orang yang', 'Orang yang', '%Orang yang%']);
} catch (Throwable $e) {
    echo "DB update komponen_anggaran failed: " . $e->getMessage() . "\n";
}

echo "\nSummary:\n";
echo "Files scanned: {$filesScanned}\n";
echo "Files changed: {$filesChanged}\n";
echo "Replacements in files: {$replacementsInFiles}\n";
echo "DB rows updated: {$dbUpdated}\n";

// done
echo "Done fixing 'Orang yang'.\n";
