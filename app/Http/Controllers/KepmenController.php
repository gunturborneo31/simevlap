<?php

namespace App\Http\Controllers;

use App\Models\Kepmen;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KepmenController extends Controller
{
    public function index(Request $request): Response
    {
        $kepmen = Kepmen::latest()->paginate(20);
        return Inertia::render('Pengaturan/Kepmen/Index', [
            'kepmen' => $kepmen,
            'activeKepmenId' => $request->session()->get('active_kepmen_id'),
        ]);
    }

    public function activate(Request $request, Kepmen $kepmen)
    {
        $request->session()->put('active_kepmen_id', $kepmen->id);
        return redirect()->back()->with('success', "Peraturan aktif diatur ke {$kepmen->kode}.");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:kepmen,kode',
            'nama' => 'required|string|max:500',
            'tahun' => 'required|string|max:10',
            'keterangan' => 'nullable|string',
        ]);
        Kepmen::create($validated);
        return redirect()->back()->with('success', 'Kepmen berhasil ditambahkan.');
    }

    public function update(Request $request, Kepmen $kepmen)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:kepmen,kode,' . $kepmen->id,
            'nama' => 'required|string|max:500',
            'tahun' => 'required|string|max:10',
            'keterangan' => 'nullable|string',
        ]);
        $kepmen->update($validated);
        return redirect()->back()->with('success', 'Kepmen berhasil diperbarui.');
    }

    public function destroy(Kepmen $kepmen)
    {
        $kepmen->delete();
        return redirect()->back()->with('success', 'Kepmen berhasil dihapus.');
    }
}
