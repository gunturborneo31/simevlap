<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RealisasiCloneDebugController extends Controller
{
    public function index(Request $request)
    {
        $opdId = $request->query('opd_id') ? (int) $request->query('opd_id') : null;
        $tahun = $request->query('tahun') ? (int) $request->query('tahun') : null;
        $pageMode = $request->query('page_mode', 'realisasi');
        $documentType = $request->query('document_type', 'dpa');

        $service = app(\App\Services\RealisasiCloneService::class);
        $payload = $service->buildPayloadForOpd($opdId, $tahun, $pageMode, $documentType, false);

        // include helpful meta
        $meta = [
            'opd_id' => $opdId,
            'tahun' => $tahun,
            'page_mode' => $pageMode,
            'document_type' => $documentType,
        ];

        return response()->json(['meta' => $meta, 'keys' => array_keys($payload), 'sample' => [
            'opds_count' => isset($payload['opds']) ? count($payload['opds']) : 0,
            'data_count' => is_array($payload['data']) || (is_object($payload['data']) && method_exists($payload['data'], 'count')) ? (is_array($payload['data']) ? count($payload['data']) : $payload['data']->count()) : 0,
        ], 'payload' => $payload]);
    }
}
