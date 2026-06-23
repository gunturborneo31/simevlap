<?php

namespace App\Http\Controllers;

use App\Http\Controllers\KomponenAnggaranController;
use App\Models\Indikator;
use App\Models\Kegiatan;
use App\Models\Opd;
use App\Models\Program;
use App\Models\Realisasi;
use App\Models\SubKegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if ($documentType === 'ikk') {
            return $this->renderIkkIndex($request, (int) $tahun, (int) $triwulan);
        }

        $programQuery = Program::with([
            'kepmen',
            'realisasi' => function ($q) use ($tahun, $triwulan) {
                $q->where('tahun', $tahun)->where('triwulan', $triwulan);
            },
        ]);

        $program = $programQuery->where(['document_type' => $documentType])->get();

        return Inertia::render('Realisasi/Index', compact('program', 'documentType', 'tahun', 'triwulan'));
    }

    private function renderIkkIndex(Request $request, int $tahun, int $triwulan): Response
    {
        $user = $request->user();
        $isOpd = $user?->hasRole('opd') ?? false;

        $opdQuery = Opd::withoutGlobalScopes()->where('is_active', true);
        if ($isOpd && $user?->opd_id) {
            $opdQuery->where('id', $user->opd_id);
        }

        $opds = $opdQuery
            ->orderBy('nama')
            ->get(['id', 'nama', 'singkatan']);

        $selectedOpdId = (int) $request->get('opd_id', $user?->opd_id ?? $opds->first()?->id ?? 0);

        if ($isOpd) {
            $selectedOpdId = (int) ($user?->opd_id ?? $selectedOpdId);
        }

        $rows = DB::table('indikatorables as ia')
            ->join('indikator as i', 'i.id', '=', 'ia.indikator_id')
            ->where('i.jenis_indikator', 'IKK')
            ->where('i.opd_id', $selectedOpdId)
            ->where('ia.tahun', $tahun)
            ->where(function ($query) use ($triwulan) {
                $query->where('ia.triwulan', $triwulan)
                    ->orWhereNull('ia.triwulan');
            })
            ->orderBy('i.uraian')
            ->get([
                'ia.id as pivot_id',
                'ia.indikator_id',
                'ia.indicatorable_type',
                'ia.indicatorable_id',
                'ia.target',
                'ia.realisasi',
                'ia.tahun',
                'ia.triwulan',
                'ia.catatan',
                'i.uraian as indikator_uraian',
                'i.satuan as indikator_satuan',
            ])
            ->map(function ($row) {
                return [
                    'pivot_id' => $row->pivot_id,
                    'indikator_id' => $row->indikator_id,
                    'indicatorable_type' => $row->indicatorable_type,
                    'indicatorable_id' => $row->indicatorable_id,
                    'indikator_uraian' => $row->indikator_uraian,
                    'indikator_satuan' => $row->indikator_satuan,
                    'target' => (float) $row->target,
                    'realisasi' => $row->realisasi === null ? null : (float) $row->realisasi,
                    'catatan' => $row->catatan,
                    'tahun' => (int) $row->tahun,
                    'triwulan' => $row->triwulan === null ? null : (int) $row->triwulan,
                ];
            })
            ->values();

        return Inertia::render('Realisasi/Index', [
            'program' => [],
            'documentType' => 'ikk',
            'tahun' => $tahun,
            'triwulan' => $triwulan,
            'opds' => $opds,
            'selectedOpdId' => $selectedOpdId,
            'ikkRows' => $rows,
        ]);
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

        if ($request->input('document_type') === 'ikk') {
            $validated = $request->validate([
                'indikator_id' => 'required|exists:indikator,id',
                'indicatorable_type' => 'required|string|in:App\\Models\\Program,App\\Models\\Kegiatan,App\\Models\\SubKegiatan',
                'indicatorable_id' => 'required|integer',
                'tahun' => 'required|integer',
                'triwulan' => 'required|integer|between:1,4',
                'target' => 'required|numeric|min:0',
                'realisasi' => 'required|numeric|min:0',
                'catatan' => 'nullable|string',
            ]);

            $indikator = Indikator::withoutGlobalScopes()->findOrFail($validated['indikator_id']);
            abort_if($indikator->jenis_indikator !== 'IKK', 404);

            $keys = [
                'indikator_id' => (int) $validated['indikator_id'],
                'indicatorable_type' => $validated['indicatorable_type'],
                'indicatorable_id' => (int) $validated['indicatorable_id'],
                'tahun' => (int) $validated['tahun'],
                'triwulan' => (int) $validated['triwulan'],
            ];

            $payload = [
                'target' => $validated['target'],
                'realisasi' => $validated['realisasi'],
                'catatan' => $validated['catatan'] ?? null,
                'updated_at' => now(),
            ];

            $exists = DB::table('indikatorables')->where($keys)->first();

            if ($exists) {
                DB::table('indikatorables')->where('id', $exists->id)->update($payload);
            } else {
                DB::table('indikatorables')->insert(array_merge($keys, $payload, [
                    'created_at' => now(),
                ]));
            }

            return redirect()->back()->with('success', 'Realisasi IKK berhasil disimpan.');
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

        // Prevent manual input for Program and Kegiatan: they are accumulated from SubKegiatan
        if (in_array($validated['realisaseable_type'], [Program::class, Kegiatan::class], true)) {
            return redirect()->back()->with('error', 'Realisasi untuk Program dan Kegiatan tidak boleh diinput manual; nilai akan diakumulasi dari SubKegiatan.');
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

        // Disallow manual update for Program/Kegiatan realisasi (they are aggregated)
        if (in_array($realisasi->realisaseable_type, [Program::class, Kegiatan::class], true)) {
            return redirect()->back()->with('error', 'Realisasi Program/Kegiatan dihitung otomatis dari turunannya dan tidak boleh diubah manual.');
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

        // Prevent deleting aggregated Program/Kegiatan realisasi directly
        if (in_array($realisasi->realisaseable_type, [Program::class, Kegiatan::class], true)) {
            return redirect()->back()->with('error', 'Realisasi Program/Kegiatan adalah hasil akumulasi dan tidak boleh dihapus secara manual.');
        }

        $realisasi->delete();
        return redirect()->back()->with('success', 'Realisasi berhasil dihapus.');
    }
}
