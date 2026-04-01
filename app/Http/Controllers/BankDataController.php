<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\Kepmen;
use App\Models\Misi;
use App\Models\Opd;
use App\Models\Program;
use App\Models\SubKegiatan;
use App\Models\Visi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankDataController extends Controller
{
    public function index(Request $request): Response
    {
        $documentType = $request->get('document_type', 'rpjmd');
        $visi = Visi::with([
            'misi.tujuan.sasaran.strategi.arahKebijakan',
        ])->where('document_type', $documentType)->get();

        $program = Program::with(['kepmen', 'kegiatan.subKegiatan', 'opd'])
            ->where('document_type', $documentType)
            ->get();

        $kepmen = Kepmen::all(['id', 'kode', 'nama']);
        $opds = Opd::where('is_active', true)->get(['id', 'nama', 'singkatan']);

        return Inertia::render('BankData/Index', compact('visi', 'program', 'kepmen', 'opds', 'documentType'));
    }

    public function storeVisi(Request $request)
    {
        $validated = $request->validate([
            'opd_id' => 'nullable|exists:opds,id',
            'document_type' => 'required|in:rpjmd,renstra,renja,dpa',
            'kode' => 'required|string|max:50',
            'uraian' => 'required|string',
            'tahun_awal' => 'required|integer',
            'tahun_akhir' => 'required|integer|gte:tahun_awal',
        ]);
        Visi::create($validated);
        return redirect()->back()->with('success', 'Visi berhasil ditambahkan.');
    }

    public function updateVisi(Request $request, Visi $visi)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50',
            'uraian' => 'required|string',
            'tahun_awal' => 'required|integer',
            'tahun_akhir' => 'required|integer|gte:tahun_awal',
        ]);
        $visi->update($validated);
        return redirect()->back()->with('success', 'Visi berhasil diperbarui.');
    }

    public function destroyVisi(Visi $visi)
    {
        $visi->delete();
        return redirect()->back()->with('success', 'Visi berhasil dihapus.');
    }

    public function storeProgram(Request $request)
    {
        $validated = $request->validate([
            'opd_id' => 'nullable|exists:opds,id',
            'kepmen_id' => 'required|exists:kepmen,id',
            'document_type' => 'required|in:rpjmd,renstra,renja,dpa',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
        ]);
        Program::create($validated);
        return redirect()->back()->with('success', 'Program berhasil ditambahkan.');
    }

    public function updateProgram(Request $request, Program $program)
    {
        $validated = $request->validate([
            'kepmen_id' => 'required|exists:kepmen,id',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
            'catatan_evaluasi' => 'nullable|string',
        ]);
        $program->update($validated);
        return redirect()->back()->with('success', 'Program berhasil diperbarui.');
    }

    public function destroyProgram(Program $program)
    {
        $program->delete();
        return redirect()->back()->with('success', 'Program berhasil dihapus.');
    }

    public function storeKegiatan(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:program,id',
            'opd_id' => 'nullable|exists:opds,id',
            'kepmen_id' => 'required|exists:kepmen,id',
            'document_type' => 'required|in:rpjmd,renstra,renja,dpa',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
        ]);
        Kegiatan::create($validated);
        return redirect()->back()->with('success', 'Kegiatan berhasil ditambahkan.');
    }

    public function updateKegiatan(Request $request, Kegiatan $kegiatan)
    {
        $validated = $request->validate([
            'kepmen_id' => 'required|exists:kepmen,id',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
        ]);
        $kegiatan->update($validated);
        return redirect()->back()->with('success', 'Kegiatan berhasil diperbarui.');
    }

    public function destroyKegiatan(Kegiatan $kegiatan)
    {
        $kegiatan->delete();
        return redirect()->back()->with('success', 'Kegiatan berhasil dihapus.');
    }

    public function storeSubKegiatan(Request $request)
    {
        $validated = $request->validate([
            'kegiatan_id' => 'required|exists:kegiatan,id',
            'opd_id' => 'nullable|exists:opds,id',
            'kepmen_id' => 'required|exists:kepmen,id',
            'document_type' => 'required|in:rpjmd,renstra,renja,dpa',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
        ]);
        SubKegiatan::create($validated);
        return redirect()->back()->with('success', 'Sub Kegiatan berhasil ditambahkan.');
    }

    public function updateSubKegiatan(Request $request, SubKegiatan $subKegiatan)
    {
        $validated = $request->validate([
            'kepmen_id' => 'required|exists:kepmen,id',
            'kode_rek' => 'required|string|max:50',
            'nama_rincian' => 'required|string|max:500',
            'pagu' => 'required|numeric|min:0',
            'tahun_awal' => 'nullable|integer',
            'tahun_akhir' => 'nullable|integer',
            'target_t1' => 'nullable|numeric',
            'target_t2' => 'nullable|numeric',
            'target_t3' => 'nullable|numeric',
            'target_t4' => 'nullable|numeric',
            'target_t5' => 'nullable|numeric',
            'target_tahunan' => 'nullable|numeric',
            'tahun' => 'nullable|integer',
        ]);
        $subKegiatan->update($validated);
        return redirect()->back()->with('success', 'Sub Kegiatan berhasil diperbarui.');
    }

    public function destroySubKegiatan(SubKegiatan $subKegiatan)
    {
        $subKegiatan->delete();
        return redirect()->back()->with('success', 'Sub Kegiatan berhasil dihapus.');
    }
}
