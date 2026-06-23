<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$columns = [
    ['table' => 'indikator_anggaran', 'column' => 'nama_indikator'],
    ['table' => 'komponen_anggaran', 'column' => 'nama_komponen'],
];

$patterns = [
    '/SKPDdan/i' => 'SKPD dan',
    '/SKP\s*D/i' => 'SKPD',
    '/SKP\s*DAN/i' => 'SKPD dan',
];

$totalUpdated = 0;
foreach ($columns as $c) {
    $table = $c['table'];
    $col = $c['column'];
        // fetch rows conservatively: include any row that contains SKPD or 'dan' or has no spaces
        $rows = DB::table($table)->where(function($q) use ($col) {
                $q->where($col, 'like', '%SKPD%')
                    ->orWhere($col, 'like', '%dan%')
                    ->orWhere($col, 'not like', '% %');
        })->get();

    $updated = 0;
    foreach ($rows as $r) {
        $old = $r->{$col} ?? '';
        $new = $old;
        // apply SKPD-specific replacements first
        foreach ($patterns as $pat => $rep) {
            $new = preg_replace($pat, $rep, $new);
        }
        // apply general normalization similar to scripts/fix_indikator.php
        // Insert space before uppercase when preceded by lowercase or digit
        $new = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $new);
        // Ensure space after commas
        $new = preg_replace('/,([^ \t])/', ', $1', $new);
        // Normalize slashes with spaces
        $new = preg_replace('/\/(?! )/', '/ ', $new);
        $new = preg_replace('/(?<! )\//', ' /', $new);
        // Ensure spaces around parentheses
        $new = preg_replace('/\)\s*/', ') ', $new);
        $new = preg_replace('/\s*\(/', ' (', $new);
        // Collapse multiple spaces
        $new = preg_replace('/\s+/', ' ', $new);
        $new = trim($new);
        if ($new !== $old) {
            DB::table($table)->where('id', $r->id)->update([$col => $new]);
            $updated++;
            echo "Updated {$table}.{$col} id={$r->id}: \n- {$old}\n+ {$new}\n";
        }
    }
    echo "{$table}.{$col}: updated={$updated}\n";
    $totalUpdated += $updated;
}

echo "Total rows updated: {$totalUpdated}\n";
