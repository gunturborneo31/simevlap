<?php

namespace App\Http\Controllers;

use App\Models\Urusan;
use Illuminate\Http\Request;

class UrusanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $query = Urusan::query();
        if ($search) {
            $query->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%");
        }
        $urusans = $query->orderBy('kode')->paginate(10)->withQueryString();
        return inertia('DataDasar/Urusan/Index', compact('urusans', 'search'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:10|unique:urusans,kode',
            'nama' => 'required|string|max:100',
        ], [
            'kode.required' => 'Kode wajib diisi.',
            'kode.unique' => 'Kode sudah terpakai.',
            'nama.required' => 'Nama wajib diisi.',
        ]);
        Urusan::create($validated);
        return redirect()->back()->with('success', 'Urusan berhasil ditambahkan.');
    }

    public function update(Request $request, Urusan $urusan)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:10|unique:urusans,kode,' . $urusan->id,
            'nama' => 'required|string|max:100',
        ], [
            'kode.required' => 'Kode wajib diisi.',
            'kode.unique' => 'Kode sudah terpakai.',
            'nama.required' => 'Nama wajib diisi.',
        ]);
        $urusan->update($validated);
        return redirect()->back()->with('success', 'Urusan berhasil diubah.');
    }

    public function destroy(Urusan $urusan)
    {
        $urusan->delete();
        return redirect()->back()->with('success', 'Urusan berhasil dihapus.');
    }
}
