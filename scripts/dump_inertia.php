<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request as HttpRequest;

try {
    $controller = new App\Http\Controllers\ResumeController();
    $request = HttpRequest::create('/resume', 'GET', [
        'view' => 'konsistensi-rpjmd-rkpd',
        'table' => 'tabel-4',
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
    // find data-page attribute (Inertia renders data-page="{...}")
    if (preg_match('/data-page="(.*?)"/s', $content, $m)) {
        $raw = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        $json = json_decode($raw, true);
        echo json_encode($json['props'] ?? $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    echo "No data-page found in response.\n";
    echo substr($content, 0, 4000);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
