<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DokumenController extends Controller
{
    private const RENSTRA_PERIOD_START = 2026;
    private const YEAR_OPTIONS = [2026, 2027, 2028, 2029, 2030];

    public function index(Request $request): Response
    {
        $user = $request->user();

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
                    'status' => 'Sudah Upload',
                    'created_at' => optional($dokumen->created_at)?->format('d/m/Y H:i'),
                    'view_url' => route('dokumen.view', $dokumen),
                ])
                ->values()
                ->all();
        }

        return Inertia::render('DataDasar/Dokumen', [
            'dokumenUploads' => $dokumenUploads,
            'uploadDocumentTypes' => [
                ['label' => 'Renstra', 'value' => 'renstra'],
                ['label' => 'Renja', 'value' => 'renja'],
                ['label' => 'DPA', 'value' => 'dpa'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'opd_id' => 'nullable|exists:opds,id',
            'document_type' => 'required|in:renstra,renja,dpa',
            'judul' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
            'tahun' => 'required|integer',
        ]);

        $this->validateTahunByDocumentType($validated['document_type'], (int) $validated['tahun']);

        if ($user?->hasRole('opd')) {
            $validated['opd_id'] = $user->opd_id;
        }

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

    public function update(Request $request, Dokumen $dokumen)
    {
        $user = $request->user();

        abort_unless($user?->hasAnyRole(['superadmin', 'admin']) || $dokumen->opd_id === $user?->opd_id, 403);

        $validated = $request->validate([
            'document_type' => 'required|in:renstra,renja,dpa',
            'judul' => 'required|string|max:255',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
            'tahun' => 'required|integer',
        ]);

        $this->validateTahunByDocumentType($validated['document_type'], (int) $validated['tahun']);

        $payload = [
            'document_type' => $validated['document_type'],
            'judul' => $validated['judul'],
            'tahun' => $validated['tahun'],
        ];

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($dokumen->file_path);
            $payload['file_path'] = $request->file('file')->store('dokumen', 'public');
        }

        $dokumen->update($payload);

        return redirect()->back()->with('success', 'Dokumen berhasil diperbarui.');
    }

    public function view(Request $request, Dokumen $dokumen)
    {
        $user = $request->user();

        abort_unless($user?->hasAnyRole(['superadmin', 'admin']) || $dokumen->opd_id === $user?->opd_id, 403);

        if (!Storage::disk('public')->exists($dokumen->file_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($dokumen->file_path));
    }

    public function destroy(Request $request, Dokumen $dokumen)
    {
        $user = $request->user();

        abort_unless($user?->hasAnyRole(['superadmin', 'admin']) || $dokumen->opd_id === $user?->opd_id, 403);

        Storage::disk('public')->delete($dokumen->file_path);
        $dokumen->delete();
        return redirect()->back()->with('success', 'Dokumen berhasil dihapus.');
    }

    private function validateTahunByDocumentType(string $documentType, int $tahun): void
    {
        if ($documentType === 'renstra' && $tahun !== self::RENSTRA_PERIOD_START) {
            abort(422, 'Periode Renstra hanya tersedia untuk 2026 - 2030.');
        }

        if (in_array($documentType, ['renja', 'dpa'], true) && !in_array($tahun, self::YEAR_OPTIONS, true)) {
            abort(422, 'Tahun dokumen hanya tersedia untuk 2026 sampai 2030.');
        }
    }
}
