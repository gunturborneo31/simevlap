<?php
$in = __DIR__ . '/../referensi/rkpd/indikator_fix.json';
$out = __DIR__ . '/../referensi/rkpd/indikator_fix.indikator_repaired.json';
if (!file_exists($in)) {
    echo "Input file not found: $in\n";
    exit(1);
}
$raw = file_get_contents($in);
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo "Failed to parse JSON from $in\n";
    exit(1);
}
$changed = 0;
foreach ($data as &$item) {
    if (isset($item['indikator']) && is_string($item['indikator'])) {
        $s = $item['indikator'];
        if ($s === '-' || trim($s) === '') continue;
        $orig = $s;
        // Insert space before uppercase when preceded by lowercase or digit
        $s = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $s);
        // Ensure space after commas
        $s = preg_replace('/,([^ \t])/', ', $1', $s);
        // Normalize slashes with spaces
        $s = preg_replace('/\/(?! )/', '/ ', $s);
        $s = preg_replace('/(?<! )\//', ' /', $s);
        // Ensure spaces around parentheses and punctuation if stuck
        $s = preg_replace('/\)\s*/', ') ', $s);
        $s = preg_replace('/\s*\(/', ' (', $s);
        // Collapse multiple spaces
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);
        if ($s !== $orig) {
            $item['indikator'] = $s;
            $changed++;
        }
    }
}
file_put_contents($out, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($in, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Processed: $changed entries changed. Wrote $out and updated $in\n";
