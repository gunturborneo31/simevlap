<?php

namespace App\Http\Controllers;

use App\Models\Opd;
use App\Models\Program;
use App\Models\Realisasi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResumeController extends Controller
{
    public function index(Request $request): Response
    {
        $documentType = $request->get('document_type', 'rpjmd');
        $tahun = $request->get('tahun', now()->year);
        $opds = Opd::where('is_active', true)->get(['id', 'nama', 'singkatan']);

        $data = Program::with(['opd', 'kepmen', 'realisasi' => function ($q) use ($tahun) {
            $q->where('tahun', $tahun)->orderBy('triwulan');
        }, 'indikator'])
        ->where('document_type', $documentType)
        ->get()
        ->map(function ($program) {
            $lastRealisasi = $program->realisasi->last();
            return [
                'id' => $program->id,
                'kode_rek' => $program->kode_rek,
                'nama_rincian' => $program->nama_rincian,
                'pagu' => $program->pagu,
                'opd' => $program->opd?->singkatan ?? 'Pemda',
                'realisasi_fisik' => $lastRealisasi?->realisasi_fisik ?? 0,
                'realisasi_keuangan' => $lastRealisasi?->realisasi_keuangan ?? 0,
            ];
        });

        return Inertia::render('Resume/Index', compact('data', 'documentType', 'tahun', 'opds'));
    }
}
