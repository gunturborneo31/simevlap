<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use App\Models\Opd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DokumenController extends Controller
{
    public function index(): Response
    {
        $dokumen = Dokumen::with(['opd', 'uploadedBy'])->latest()->paginate(20);
        $opds = Opd::where('is_active', true)->get(['id', 'nama', 'singkatan']);
        return Inertia::render('BankData/Dokumen', compact('dokumen', 'opds'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'opd_id' => 'nullable|exists:opds,id',
            'document_type' => 'required|in:rpjmd,renstra,renja,dpa',
            'judul' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf|max:10240',
            'tahun' => 'required|integer',
        ]);
        $path = $request->file('file')->store('dokumen', 'public');
        Dokumen::create([
            'opd_id' => $validated['opd_id'] ?? null,
            'document_type' => $validated['document_type'],
            'judul' => $validated['judul'],
            'file_path' => $path,
            'tahun' => $validated['tahun'],
            'uploaded_by' => auth()->id(),
        ]);
        return redirect()->back()->with('success', 'Dokumen berhasil diunggah.');
    }

    public function destroy(Dokumen $dokumen)
    {
        Storage::disk('public')->delete($dokumen->file_path);
        $dokumen->delete();
        return redirect()->back()->with('success', 'Dokumen berhasil dihapus.');
    }
}
