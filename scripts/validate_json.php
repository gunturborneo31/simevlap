<?php
if ($argc<2) { echo "Usage: php validate_json.php path/to/file.json\n"; exit(2); }
$path = $argv[1];
if (!file_exists($path)) { echo "File not found: $path\n"; exit(2); }
$s = file_get_contents($path);
json_decode($s);
$err = json_last_error();
$msg = json_last_error_msg();
if ($err === JSON_ERROR_NONE) {
    echo "OK\n";
    exit(0);
}
// Try to locate approximate line by scanning for invalid characters around error offset if available
$offset = null;
if (is_callable('json_last_error')) {
    // PHP doesn't provide offset, so just print message
}
echo "JSON error: $msg\n";
exit(1);
