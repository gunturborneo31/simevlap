<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use App\Models\Opd;
use App\Models\Program;
use App\Models\Realisasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $stats = [
            'total_opd' => Opd::where('is_active', true)->count(),
            'total_program' => Program::count(),
            'total_realisasi' => Realisasi::count(),
        ];

        $dokumenUploads = [];
        if ($user?->hasRole('opd')) {
            $dokumenUploads = Dokumen::query()
                ->where('opd_id', $user->opd_id)
                ->latest()
                ->get()
                ->map(fn (Dokumen $dokumen) => [
                    'id' => $dokumen->id,
                    'document_type' => $dokumen->document_type,
                    'judul' => $dokumen->judul,
                    'tahun' => $dokumen->tahun,
                    'created_at' => optional($dokumen->created_at)?->format('d/m/Y H:i'),
                    'view_url' => route('dashboard.dokumen.view', $dokumen),
                ])
                ->values()
                ->all();
        }

        return Inertia::render('DashboardClassic', [
            'stats' => $stats,
            'user' => $user?->load('opd'),
            'dokumenUploads' => $dokumenUploads,
            'uploadDocumentTypes' => [
                ['label' => 'Renstra', 'value' => 'renstra'],
                ['label' => 'Renja', 'value' => 'renja'],
                ['label' => 'DPA', 'value' => 'dpa'],
            ],
        ]);
    }

    public function storeDokumen(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('opd'), 403);

        $validated = $request->validate([
            'document_type' => 'required|in:renstra,renja,dpa',
            'judul' => 'required|string|max:255',
            'tahun' => 'required|integer|min:2020|max:2100',
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        $path = $request->file('file')->store('dokumen', 'public');

        Dokumen::create([
            'opd_id' => $user->opd_id,
            'document_type' => $validated['document_type'],
            'judul' => $validated['judul'],
            'file_path' => $path,
            'tahun' => (int) $validated['tahun'],
            'uploaded_by' => $user->id,
        ]);

        return redirect()->route('dashboard')->with('success', 'Dokumen berhasil diunggah.');
    }

    public function viewDokumen(Request $request, Dokumen $dokumen)
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole(['superadmin', 'admin']) || $dokumen->opd_id === $user?->opd_id, 403);

        if (!Storage::disk('public')->exists($dokumen->file_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($dokumen->file_path));
    }

    public function destroyDokumen(Request $request, Dokumen $dokumen): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('opd') && $dokumen->opd_id === $user->opd_id, 403);

        Storage::disk('public')->delete($dokumen->file_path);
        $dokumen->delete();

        return redirect()->route('dashboard')->with('success', 'Dokumen berhasil dihapus.');
    }
}
