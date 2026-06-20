<?php

namespace App\Http\Controllers;

use App\Models\Iku;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IkuController extends Controller
{
    public function index()
    {
        $query = Iku::query();
        if ($search = request('search')) {
            $query->where('indikator', 'like', "%{$search}%");
        }
        $ikus = $query->orderBy('id')->paginate(10)->withQueryString();
        return Inertia::render('DataDasar/Iku/Index', compact('ikus'));
    }

    public function create()
    {
        return Inertia::render('DataDasar/Iku/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'indikator' => 'required',
            'satuan' => 'required',
            'capaian_2024' => 'required',
            'target_2025' => 'required',
            'target_2026' => 'required',
            'target_2027' => 'required',
            'target_2028' => 'required',
            'target_2029' => 'required',
            'target_2030' => 'required',
        ]);
        Iku::create($data);
        return redirect()->route('iku.index')->with('success', 'Data IKU berhasil ditambah.');
    }

    public function edit(Iku $iku)
    {
        return Inertia::render('DataDasar/Iku/Edit', compact('iku'));
    }

    public function update(Request $request, Iku $iku)
    {
        $data = $request->validate([
            'indikator' => 'required',
            'satuan' => 'required',
            'capaian_2024' => 'required',
            'target_2025' => 'required',
            'target_2026' => 'required',
            'target_2027' => 'required',
            'target_2028' => 'required',
            'target_2029' => 'required',
            'target_2030' => 'required',
        ]);
        $iku->update($data);
        return redirect()->route('iku.index')->with('success', 'Data IKU berhasil diubah.');
    }

    public function destroy(Iku $iku)
    {
        $iku->delete();
        return redirect()->route('iku.index')->with('success', 'Data IKU berhasil dihapus.');
    }
}
