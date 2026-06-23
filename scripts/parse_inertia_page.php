<?php
$file = 'tmp_relasi_pa.html';
$html = file_get_contents($file);
if (!preg_match('/data-page="(.*?)"\s*>/s', $html, $m)) {
    echo "NO_DATA_PAGE\n";
    exit;
}
$json = html_entity_decode($m[1]);
$data = json_decode($json, true);
if (!$data) { echo "JSON_ERROR\n"; var_export(json_last_error_msg()); exit; }
$component = $data['component'] ?? null;
$url = $data['url'] ?? null;
$auth_user = isset($data['props']['auth']['user']) ? true : false;
echo "COMPONENT:" . ($component ?? '[none]') . "\n";
echo "URL:" . ($url ?? '[none]') . "\n";
echo "AUTH_USER:" . ($auth_user ? '1' : '0') . "\n";
echo "ROWS_COUNT:" . (isset($data['props']['rows']) ? count($data['props']['rows']) : 'N/A') . "\n";
echo "PARENTS_COUNT:" . (isset($data['props']['parents']) ? count($data['props']['parents']) : 'N/A') . "\n";
