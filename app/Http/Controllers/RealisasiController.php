<?php

namespace App\Http\Controllers;

use App\Http\Controllers\KomponenAnggaranController;
use App\Models\Kegiatan;
use App\Models\Opd;
use App\Models\Program;
use App\Models\Realisasi;
use App\Models\SubKegiatan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RealisasiController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuperadmin = $user?->hasRole('superadmin') ?? false;
        $isAdmin = $user?->hasRole('admin') ?? false;
        $isOpd = $user?->hasRole('opd') ?? false;

        if ($isSuperadmin) {
            $allowedDocumentTypes = ['iku', 'ikk', 'dpa'];
            $defaultDocumentType = 'iku';
        } elseif ($isAdmin) {
            // Admin hanya diizinkan bekerja pada IKU sesuai kebutuhan bisnis.
            $allowedDocumentTypes = ['iku'];
            $defaultDocumentType = 'iku';
        } else {
            $allowedDocumentTypes = ['ikk', 'dpa'];
            $defaultDocumentType = 'ikk';
        }

        $requestedDocumentType = $request->get('document_type');
        $documentType = in_array($requestedDocumentType, $allowedDocumentTypes, true)
            ? $requestedDocumentType
            : $defaultDocumentType;

        // Render halaman DPA yang sama, tetapi tetap dari URL /realisasi
        if ($documentType === 'dpa') {
            if ($isOpd) {
                $request->merge(['opd_id' => $user?->opd_id]);
            }

            $request->merge(['page_mode' => 'realisasi', 'document_type' => 'dpa']);
            return app(KomponenAnggaranController::class)->index();
        }

        $tahun = $request->get('tahun', now()->year);
        $triwulan = $request->get('triwulan', 1);

        $programQuery = Program::with([
            'kepmen',
            'realisasi' => function ($q) use ($tahun, $triwulan) {
                $q->where('tahun', $tahun)->where('triwulan', $triwulan);
            },
        ]);

        $program = $programQuery->where(['document_type' => $documentType])->get();

        return Inertia::render('Realisasi/Index', compact('program', 'documentType', 'tahun', 'triwulan'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Realisasi::class);

        $user = $request->user();
        $allowedDocumentTypes = ['ikk', 'dpa'];
        if ($user?->hasRole('superadmin')) {
            $allowedDocumentTypes = ['iku', 'ikk', 'dpa'];
        } elseif ($user?->hasRole('admin')) {
            $allowedDocumentTypes = ['iku'];
        }

        $validated = $request->validate([
            'realisaseable_id' => 'required|integer',
            'realisaseable_type' => 'required|string|in:App\\Models\\Program,App\\Models\\Kegiatan,App\\Models\\SubKegiatan',
            'opd_id' => 'nullable|exists:opds,id',
            'document_type' => 'required|in:' . implode(',', $allowedDocumentTypes),
            'tahun' => 'required|integer',
            'tahun_ke' => 'nullable|integer|between:1,5',
            'triwulan' => 'required|integer|between:1,4',
            'realisasi_fisik' => 'required|numeric|min:0|max:100',
            'realisasi_keuangan' => 'nullable|numeric|min:0',
            'sisa_anggaran' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string',
        ]);

        if ($user?->hasRole('opd')) {
            // OPD must always write under their own OPD context.
            $validated['opd_id'] = $user->opd_id;
        }

        $validated['input_by'] = auth()->id();
        Realisasi::create($validated);
        return redirect()->back()->with('success', 'Realisasi berhasil disimpan.');
    }

    public function update(Request $request, Realisasi $realisasi)
    {
        $this->authorize('update', $realisasi);

        if ($request->user()?->hasRole('opd')) {
            abort(403, 'User OPD tidak memiliki akses mengubah realisasi.');
        }

        $validated = $request->validate([
            'realisasi_fisik' => 'required|numeric|min:0|max:100',
            'realisasi_keuangan' => 'nullable|numeric|min:0',
            'sisa_anggaran' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string',
        ]);
        $realisasi->update($validated);
        return redirect()->back()->with('success', 'Realisasi berhasil diperbarui.');
    }

    public function destroy(Realisasi $realisasi)
    {
        $this->authorize('delete', $realisasi);

        if (request()->user()?->hasRole('opd')) {
            abort(403, 'User OPD tidak memiliki akses menghapus realisasi.');
        }

        $realisasi->delete();
        return redirect()->back()->with('success', 'Realisasi berhasil dihapus.');
    }
}
