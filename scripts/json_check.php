<?php
$path = $argv[1] ?? null;
if (!$path) { echo "Usage: php json_check.php <file>\n"; exit(1); }
$content = file_get_contents($path);
try {
    $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    echo "OK: parsed " . (is_array($data) ? count($data) : 1) . " items\n";
} catch (\JsonException $e) {
    echo "JSON error: " . $e->getMessage() . "\n";
}
