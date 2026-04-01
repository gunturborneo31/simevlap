<?php

namespace App\Http\Controllers;

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
        $documentType = $request->get('document_type', 'rpjmd');
        $tahun = $request->get('tahun', now()->year);
        $triwulan = $request->get('triwulan', 1);

        $program = Program::with(['kepmen', 'realisasi' => function ($q) use ($tahun, $triwulan) {
            $q->where('tahun', $tahun)->where('triwulan', $triwulan);
        }])->where('document_type', $documentType)->get();

        return Inertia::render('Realisasi/Index', compact('program', 'documentType', 'tahun', 'triwulan'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Realisasi::class);
        $validated = $request->validate([
            'realisaseable_id' => 'required|integer',
            'realisaseable_type' => 'required|string|in:App\\Models\\Program,App\\Models\\Kegiatan,App\\Models\\SubKegiatan',
            'opd_id' => 'nullable|exists:opds,id',
            'document_type' => 'required|in:rpjmd,renstra,renja,dpa',
            'tahun' => 'required|integer',
            'tahun_ke' => 'nullable|integer|between:1,5',
            'triwulan' => 'required|integer|between:1,4',
            'realisasi_fisik' => 'required|numeric|min:0|max:100',
            'realisasi_keuangan' => 'nullable|numeric|min:0',
            'sisa_anggaran' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string',
        ]);
        $validated['input_by'] = auth()->id();
        Realisasi::create($validated);
        return redirect()->back()->with('success', 'Realisasi berhasil disimpan.');
    }

    public function update(Request $request, Realisasi $realisasi)
    {
        $this->authorize('update', $realisasi);
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
        $realisasi->delete();
        return redirect()->back()->with('success', 'Realisasi berhasil dihapus.');
    }
}
