<?php

namespace App\Http\Controllers;

use App\Models\BidangUrusan;
use App\Models\Urusan;
use Illuminate\Http\Request;

class BidangUrusanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $urusan_id = $request->input('urusan_id');
        $query = BidangUrusan::with('urusan');
        if ($search) {
            $query->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%");
        }
        if ($urusan_id) {
            $query->where('urusan_id', $urusan_id);
        }
        $bidangUrusans = $query->orderBy('kode')->paginate(10)->withQueryString();
        $urusans = Urusan::orderBy('kode')->get();
        return inertia('DataDasar/BidangUrusan/Index', compact('bidangUrusans', 'urusans', 'search', 'urusan_id'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:10|unique:bidang_urusans,kode',
            'nama' => 'required|string|max:100',
        ], [
            'kode.required' => 'Kode wajib diisi.',
            'kode.unique' => 'Kode sudah terpakai.',
            'nama.required' => 'Nama wajib diisi.',
        ]);
        BidangUrusan::create($validated);
        return redirect()->back()->with('success', 'Bidang Urusan berhasil ditambahkan.');
    }

    public function update(Request $request, BidangUrusan $bidang_urusan)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:10|unique:bidang_urusans,kode,' . $bidang_urusan->id,
            'nama' => 'required|string|max:100',
        ], [
            'kode.required' => 'Kode wajib diisi.',
            'kode.unique' => 'Kode sudah terpakai.',
            'nama.required' => 'Nama wajib diisi.',
        ]);
        $bidang_urusan->update($validated);
        return redirect()->back()->with('success', 'Bidang Urusan berhasil diubah.');
    }

    public function destroy(BidangUrusan $bidang_urusan)
    {
        $bidang_urusan->delete();
        return redirect()->back()->with('success', 'Bidang Urusan berhasil dihapus.');
    }
}
