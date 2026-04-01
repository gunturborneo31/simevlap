<?php

namespace App\Http\Controllers;

use App\Models\Opd;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpdController extends Controller
{
    public function index(): Response
    {
        $opds = Opd::latest()->paginate(20);
        return Inertia::render('Pengaturan/Opd/Index', ['opds' => $opds]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:20|unique:opds,kode',
            'nama' => 'required|string|max:255',
            'singkatan' => 'nullable|string|max:50',
            'kepala_opd' => 'nullable|string|max:255',
            'nip_kepala' => 'nullable|string|max:30',
            'is_active' => 'boolean',
        ]);
        Opd::create($validated);
        return redirect()->back()->with('success', 'OPD berhasil ditambahkan.');
    }

    public function update(Request $request, Opd $opd)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:20|unique:opds,kode,' . $opd->id,
            'nama' => 'required|string|max:255',
            'singkatan' => 'nullable|string|max:50',
            'kepala_opd' => 'nullable|string|max:255',
            'nip_kepala' => 'nullable|string|max:30',
            'is_active' => 'boolean',
        ]);
        $opd->update($validated);
        return redirect()->back()->with('success', 'OPD berhasil diperbarui.');
    }

    public function destroy(Opd $opd)
    {
        $opd->delete();
        return redirect()->back()->with('success', 'OPD berhasil dihapus.');
    }
}
