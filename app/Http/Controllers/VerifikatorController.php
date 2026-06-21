<?php

namespace App\Http\Controllers;

use App\Models\Realisasi;
use App\Models\Kegiatan;
use App\Models\Program;
use App\Models\SubKegiatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerifikatorController extends Controller
{
    public function index(Request $request): Response
    {
        $allowedDocumentTypes = ['ikk', 'dpa'];
        $documentType = $request->string('document_type')->toString();
        if (!in_array($documentType, $allowedDocumentTypes, true)) {
            $documentType = 'ikk';
        }

        $tahun = $request->integer('tahun') ?: now()->year;
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        if ($documentType === 'dpa') {
            $request->merge([
                'page_mode' => 'verifikator',
                'document_type' => 'dpa',
                'readonly' => 1,
                'triwulan' => $request->get('triwulan', 'all'),
                'tahun' => $tahun,
            ]);

            return app(KomponenAnggaranController::class)->index();
        }

        $query = Realisasi::query()
            ->with(['opd:id,nama,singkatan', 'inputBy:id,name', 'verifiedBy:id,name'])
            ->where([
                'document_type' => $documentType,
                'tahun' => $tahun,
            ]);

        if (in_array($status, ['verified', 'unverified'], true)) {
            $query->where(['is_verified' => $status === 'verified']);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('opd', function ($sub) use ($search) {
                    $sub->where('nama', 'like', "%{$search}%")
                        ->orWhere('singkatan', 'like', "%{$search}%");
                })->orWhere('catatan', 'like', "%{$search}%")
                  ->orWhere('catatan_verifikator', 'like', "%{$search}%");
            });
        }

        $realisasi = $query
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Verifikator/Index', [
            'filters' => [
                'document_type' => $documentType,
                'tahun' => $tahun,
                'status' => $status,
                'search' => $search,
            ],
            'documentTypes' => [
                ['label' => 'IKK', 'value' => 'ikk'],
                ['label' => 'REALISASI', 'value' => 'dpa'],
            ],
            'realisasi' => $realisasi,
        ]);
    }

    public function verify(Request $request, Realisasi $realisasi): RedirectResponse
    {
        $validated = $request->validate([
            'catatan_verifikator' => 'nullable|string',
        ]);

        $realisasi->update([
            'catatan_verifikator' => $validated['catatan_verifikator'] ?? null,
            'is_verified' => true,
            'verified_by' => $request->user()?->id,
            'verified_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Data realisasi berhasil diverifikasi.');
    }

    public function verifyByReference(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'realisaseable_type' => 'required|string|in:' . Program::class . ',' . Kegiatan::class . ',' . SubKegiatan::class,
            'realisaseable_id' => 'required|integer',
            'tahun' => 'required|integer',
            'triwulan' => 'nullable|integer|between:1,4',
            'catatan_verifikator' => 'nullable|string',
        ]);

        $query = Realisasi::query()
            ->where('realisaseable_type', $validated['realisaseable_type'])
            ->where('realisaseable_id', (int) $validated['realisaseable_id'])
            ->where('tahun', (int) $validated['tahun']);

        if (!empty($validated['triwulan'])) {
            $query->where('triwulan', (int) $validated['triwulan']);
        }

        $updated = $query->update([
            'catatan_verifikator' => $validated['catatan_verifikator'] ?? null,
            'is_verified' => true,
            'verified_by' => $request->user()?->id,
            'verified_at' => now(),
        ]);

        if ($updated === 0) {
            return redirect()->back()->with('error', 'Data realisasi belum tersedia untuk diverifikasi pada filter ini.');
        }

        return redirect()->back()->with('success', 'Data realisasi berhasil diverifikasi.');
    }
}
