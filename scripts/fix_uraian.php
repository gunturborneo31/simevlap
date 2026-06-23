<?php
// scripts/fix_uraian.php
// Usage: php fix_uraian.php
$in = __DIR__ . '/../referensi/rkpd/indikator_fix.json';
$out = __DIR__ . '/../referensi/rkpd/indikator_fix.repaired.json';
if (!file_exists($in)) {
    echo "Input file not found: $in\n";
    exit(1);
}
$raw = file_get_contents($in);
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo "Failed to decode JSON\n";
    exit(1);
}
$changed = 0;
foreach ($data as &$it) {
    if (!isset($it['uraian']) || !is_string($it['uraian'])) continue;
    $u = $it['uraian'];
    $orig = $u;
    // Only attempt if it looks concatenated (no spaces) or has camelcase
    if (strpos($u, ' ') === false || preg_match('/[a-z][A-Z]/', $u) || preg_match('/[A-Z]{2,}[a-z]/', $u)) {
        // Insert space between lower->Upper (camel/pascal case)
        $u = preg_replace('/([a-z])([A-Z])/', '$1 $2', $u);
        // Split sequences like ABCWord -> AB C Word (handle upper-upperLower boundaries)
        $u = preg_replace('/([A-Z])([A-Z][a-z])/', '$1 $2', $u);
        // Normalize slashes and commas spacing
        $u = preg_replace('#\s*/\s*#', ' / ', $u);
        $u = preg_replace('/,\s*/', ', ', $u);
        // Replace multiple spaces with single
        $u = preg_replace('/\s+/', ' ', $u);
        $u = trim($u);
        if ($u !== $orig) {
            $it['uraian'] = $u;
            $changed++;
        }
    }
}
unset($it);
file_put_contents($out, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Processed: $changed entries changed. Output: $out\n";
