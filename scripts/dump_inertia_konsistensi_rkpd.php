<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request as HttpRequest;
use App\Http\Controllers\ResumeController;

try {
    $controller = new ResumeController();
    $tableArg = $argv[1] ?? 'tabel-5';
    $viewArg = $argv[2] ?? 'konsistensi-rkpd-apbd';
    $request = HttpRequest::create('/resume', 'GET', [
        'view' => $viewArg,
        'table' => $tableArg,
        'year' => 2026,
        'basis' => 'perangkat-daerah',
        'debug_opd_id' => 20,
    ]);

    $resp = $controller->index($request);
    if ($resp instanceof Inertia\Response) {
        $sym = $resp->toResponse($request);
        $content = $sym->getContent();
    } else {
        $content = method_exists($resp, 'getContent') ? $resp->getContent() : (string) $resp;
    }
    if (preg_match('/data-page="(.*?)"/s', $content, $m)) {
        $raw = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        $json = json_decode($raw, true);
        $props = $json['props'] ?? $json;
        $out = [
            'currentView' => $props['currentView'] ?? ($props['page']['props']['currentView'] ?? null),
            'currentTable' => $props['currentTable'] ?? ($props['page']['props']['currentTable'] ?? null),
            'tableMetricType' => $props['tableMetricType'] ?? ($props['page']['props']['tableMetricType'] ?? null),
            'isRenjaDpaMode' => $props['isRenjaDpaMode'] ?? ($props['page']['props']['isRenjaDpaMode'] ?? null),
            'useRenjaForLeftColumn' => $props['useRenjaForLeftColumn'] ?? ($props['page']['props']['useRenjaForLeftColumn'] ?? null),
            'sampleRow' => ($props['tableData_debug']['sample'][0] ?? ($props['tableData'][0] ?? null)),
            'firstRowFull' => ($props['tableData'][0] ?? null),
            'tableDataCount' => is_array($props['tableData'] ?? null) ? count($props['tableData']) : null,
        ];
        // optional: third arg can be an index (1-based) or search string to find a specific row
        $finder = $argv[3] ?? null;
        if ($finder) {
            $found = null;
            $foundRows = [];
            $table = $props['tableData'] ?? [];
            if (is_numeric($finder)) {
                $i = max(0, intval($finder) - 1);
                $found = $table[$i] ?? null;
            } else {
                // support prefix ALL: to return all matches
                if (strpos($finder, 'ALL:') === 0) {
                    $needle = mb_strtoupper(substr($finder, 4));
                    foreach ($table as $r) {
                        $hay = mb_strtoupper(($r['program_prioritas'] ?? $r['program'] ?? $r['nama'] ?? ''));
                        if (strpos($hay, $needle) !== false) { $foundRows[] = $r; }
                    }
                } else {
                    $needle = mb_strtoupper($finder);
                    if ($needle === 'FIRST_NONEMPTY_INDIKATOR') {
                        foreach ($table as $r) {
                            $ind = $r['indikator'] ?? ($r['indikator_program'] ?? null);
                            if ($ind && $ind !== '-' && trim((string)$ind) !== '') { $found = $r; break; }
                        }
                    } else {
                        foreach ($table as $r) {
                            $hay = mb_strtoupper(($r['program_prioritas'] ?? $r['program'] ?? $r['nama'] ?? ''));
                            if (strpos($hay, $needle) !== false) { $found = $r; break; }
                        }
                    }
                }
            }
            $out['foundRow'] = $found;
            if (!empty($foundRows)) { $out['foundRows'] = $foundRows; }
        }

        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit(0);
    }
    echo "--- RESPONSE START ---\n";
    echo $content . "\n";
    echo "--- RESPONSE END ---\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
