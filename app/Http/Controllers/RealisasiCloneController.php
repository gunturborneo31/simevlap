<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class RealisasiCloneController extends Controller
{
    /**
     * Render a cloned Realisasi (DPA) view for Resume attachments.
     * This controller returns the same payload shape as the original Realisasi
     * builder but renders a cloned view under the ResumeRealisasi namespace.
     */
    public function index(Request $request)
    {
        $opdId = $request->query('opd_id') ? (int) $request->query('opd_id') : null;
        $tahun = $request->query('tahun') ? (int) $request->query('tahun') : null;
        $pageMode = $request->query('page_mode', 'realisasi');
        $documentType = $request->query('document_type', 'dpa');

        // Use dedicated service for clone payload to keep clone independent from KomponenAnggaranController
        $service = app(\App\Services\RealisasiCloneService::class);
        $payload = $service->buildPayloadForOpd($opdId, $tahun, $pageMode, $documentType, false);

        // mark that this render is a resume clone/attachment (read-only)
        // legacy prop name expected by the original DPA component
        $payload['resume_clone'] = true;
        $payload['resume_duplicate'] = true;
        $payload['resume_source'] = 'resume.attachments';
        $payload['readonly'] = true;
        // Resume attachments should hide RENSTRA columns by default
        $payload['includeRenstra'] = false;

        // lightweight debug summary so frontend can show counts when diagnosing empty page
        $payload['payload_summary'] = [
            'keys' => array_keys($payload),
            'opds_count' => isset($payload['opds']) ? count($payload['opds']) : 0,
            'data_count' => is_array($payload['data']) ? count($payload['data']) : (method_exists($payload['data'], 'length') ? $payload['data'].length : 0),
        ];

        // Render the independent clone view which forwards props to the original component
        return Inertia::render('ResumeRealisasi/Dokumen/Dpa/Index', $payload);
    }
}
