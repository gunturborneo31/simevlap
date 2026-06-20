<?php
namespace App\Http\Controllers;

use App\Models\IndikatorAnggaran;
use App\Models\Kegiatan;
use App\Models\KomponenAnggaran;
use App\Models\Opd;
use App\Models\Program;
use App\Models\Realisasi;
use App\Models\ResumeProgramAnnotation;
use App\Models\ResumeProgramEvidence;
use App\Models\SubKegiatan;
use App\Http\Requests\StoreKomponenAnggaranRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class KomponenAnggaranController extends Controller
{
    public function index()
    {
        $opdId = request('opd_id');
        $tahun = request('tahun');
        $pageMode = request('page_mode', 'dokumen');
        $documentType = request('document_type', 'dpa');
        $opdFilterIds = $this->resolveOpdFilterIds($opdId);

        $query = KomponenAnggaran::with([
            'indikator',
            'urusanRef:id,kode,nama',
            'bidangUrusanRef:id,kode,nama',
            'children.indikator',
            'children.urusanRef:id,kode,nama',
            'children.bidangUrusanRef:id,kode,nama',
            'children.children.indikator',
            'children.children.urusanRef:id,kode,nama',
            'children.children.bidangUrusanRef:id,kode,nama',
        ])
            ->where('document_type', 'dpa')
            ->whereNull('parent_id');

        if ($opdId) {
            $query->whereIn('opd_id', $opdFilterIds);
        }
        if ($tahun) {
            $query->where('tahun', $tahun);
        }

        $data  = $query->orderBy('kode')->get();
        $realisasiRefLookup = [];
        $realisasiLookup = $this->buildRealisasiLookup($opdFilterIds, $tahun, $realisasiRefLookup);
        $data = $this->mapKomponenWithReferenceNames($data, $realisasiLookup, $realisasiRefLookup);

        if ($pageMode === 'realisasi' && $documentType === 'dpa') {
            $renjaQuery = KomponenAnggaran::with([
                'indikator',
                'urusanRef:id,kode,nama',
                'bidangUrusanRef:id,kode,nama',
                'children.indikator',
                'children.urusanRef:id,kode,nama',
                'children.bidangUrusanRef:id,kode,nama',
                'children.children.indikator',
                'children.children.urusanRef:id,kode,nama',
                'children.children.bidangUrusanRef:id,kode,nama',
            ])
                ->where('document_type', 'renja')
                ->whereNull('parent_id');

            if ($opdId) {
                $renjaQuery->whereIn('opd_id', $opdFilterIds);
            }
            if ($tahun) {
                $renjaQuery->where('tahun', $tahun);
            }

            $renjaData = $this->mapKomponenWithReferenceNames($renjaQuery->orderBy('kode')->get());

            $renstraQuery = KomponenAnggaran::with([
                'indikator',
                'urusanRef:id,kode,nama',
                'bidangUrusanRef:id,kode,nama',
                'children.indikator',
                'children.urusanRef:id,kode,nama',
                'children.bidangUrusanRef:id,kode,nama',
                'children.children.indikator',
                'children.children.urusanRef:id,kode,nama',
                'children.children.bidangUrusanRef:id,kode,nama',
            ])
                ->where('document_type', 'renstra')
                ->whereNull('parent_id');

            if ($opdId) {
                $renstraQuery->whereIn('opd_id', $opdFilterIds);
            }

            $renstraData = $this->mapKomponenWithReferenceNames($renstraQuery->orderBy('kode')->get());
            $data = $this->mergeKomponenTreesForRealisasi($data, $renjaData, $renstraData);
        }

        $opds  = \App\Models\Opd::where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'kode']);
        $tahunList = range(date('Y') - 2, date('Y') + 2);

        // Ambil daftar program DPA yang terhubung dengan OPD yang dipilih
        // atau semua program jika belum ada filter OPD
        $programQuery = KomponenAnggaran::where('jenis', 'program')
            ->where('document_type', 'dpa')
            ->whereNull('parent_id');

        if ($opdId) {
            $programQuery->whereIn('opd_id', $opdFilterIds);
        }
        if ($tahun) {
            $programQuery->where('tahun', $tahun);
        }

        $masterProgramList = $programQuery
            ->with(['indikator', 'bidangUrusanRef:id,kode,nama'])
            ->orderBy('kode')
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'kode'        => $p->kode,
                'kode_program'=> $p->kode_program,
                'nama'        => $p->nama_komponen,
                'bidang'      => $p->bidangUrusanRef?->nama ?? $p->bidang_urusan,
                'opd_id'      => $p->opd_id,
                'indikator'   => $p->indikator->map(fn($i) => [
                    'nama_indikator' => $i->nama_indikator,
                    'sifat_indikator'=> $i->sifat_indikator,
                    'target_indikator'=> $i->target_indikator,
                    'satuan'         => $i->satuan,
                ])->values()->all(),
            ]);

        $programReferensi = collect();
        $kegiatanReferensi = collect();
        $subKegiatanReferensi = collect();

        if ($opdId) {
            $programMasterQuery = Program::query()
                ->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])
                ->where('document_type', 'dpa')
                ->whereIn('opd_id', $opdFilterIds);

            $kegiatanMasterQuery = Kegiatan::query()
                ->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])
                ->where('document_type', 'dpa')
                ->whereIn('opd_id', $opdFilterIds);

            $subKegiatanMasterQuery = SubKegiatan::query()
                ->select(['id', 'opd_id', 'kode_rek', 'nama_rincian', 'pagu'])
                ->where('document_type', 'dpa')
                ->whereIn('opd_id', $opdFilterIds);

            if ($tahun) {
                $programMasterQuery->where('tahun', $tahun);
                $kegiatanMasterQuery->where('tahun', $tahun);
                $subKegiatanMasterQuery->where('tahun', $tahun);
            }

            $programReferensi = $programMasterQuery->orderBy('kode_rek')->get()->map(fn ($row) => [
                'id' => $row->id,
                'opd_id' => $row->opd_id,
                'kode' => $row->kode_rek,
                'nama' => $row->nama_rincian,
            ]);

            $kegiatanReferensi = $kegiatanMasterQuery->orderBy('kode_rek')->get()->map(fn ($row) => [
                'id' => $row->id,
                'opd_id' => $row->opd_id,
                'kode' => $row->kode_rek,
                'nama' => $row->nama_rincian,
            ]);

            $subKegiatanReferensi = $subKegiatanMasterQuery->orderBy('kode_rek')->get()->map(fn ($row) => [
                'id' => $row->id,
                'opd_id' => $row->opd_id,
                'kode' => $row->kode_rek,
                'nama' => $row->nama_rincian,
                'pagu' => (int) ($row->pagu ?? 0),
            ]);
        }

        return Inertia::render('DataDasar/Dokumen/Dpa/Index', [
            'data'              => $data,
            'opds'              => $opds,
            'tahunList'         => $tahunList,
            'masterProgramList' => $masterProgramList,
            'masterReferensi' => [
                'program' => $programReferensi,
                'kegiatan' => $kegiatanReferensi,
                'sub_kegiatan' => $subKegiatanReferensi,
            ],
            'pageMode' => $pageMode,
            'documentType' => $documentType,
            'readonly' => request()->boolean('readonly'),
            'realisasiAnnotations' => $this->getRealisasiAnnotationsPayload($pageMode, $documentType, $opdId ? (int) $opdId : null, $tahun ? (int) $tahun : null),
        ]);
    }

    public function upsertRealisasiAnnotation(\Illuminate\Http\Request $request): RedirectResponse
    {
        $data = $request->validate([
            'opd_id' => ['required', 'integer'],
            'tahun' => ['nullable', 'integer'],
            'program_kode' => ['required', 'string', 'max:120'],
            'program_nama' => ['required', 'string', 'max:255'],
            'faktor_penghambat' => ['nullable', 'string'],
            'faktor_pendorong' => ['nullable', 'string'],
            'faktor_tindak_lanjut' => ['nullable', 'string'],
        ]);

        $annotation = $this->resolveRealisasiAnnotation($data);
        $annotation->fill([
            'faktor_penghambat' => $data['faktor_penghambat'] ?? null,
            'faktor_pendorong' => $data['faktor_pendorong'] ?? null,
            'faktor_tindak_lanjut' => $data['faktor_tindak_lanjut'] ?? null,
        ]);
        $annotation->save();

        return redirect()->back();
    }

    public function uploadRealisasiEvidence(\Illuminate\Http\Request $request): RedirectResponse
    {
        $data = $request->validate([
            'opd_id' => ['required', 'integer'],
            'tahun' => ['nullable', 'integer'],
            'program_kode' => ['required', 'string', 'max:120'],
            'program_nama' => ['required', 'string', 'max:255'],
            'sub_kegiatan_kode' => ['nullable', 'string', 'max:120'],
            'sub_kegiatan_nama' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
        ]);

        $annotation = $this->resolveRealisasiAnnotation($data);
        $file = $request->file('file');

        $existing = null;
        if (!empty($data['sub_kegiatan_kode'])) {
            $existing = ResumeProgramEvidence::query()
                ->where('resume_program_annotation_id', $annotation->id)
                ->where('sub_kegiatan_kode', $data['sub_kegiatan_kode'])
                ->first();
        }

        $storedPath = $file->store('uploads/realisasi-evidence', 'public');

        if ($existing) {
            if ($existing->file_path) {
                Storage::disk('public')->delete($existing->file_path);
            }

            $existing->update([
                'sub_kegiatan_nama' => $data['sub_kegiatan_nama'] ?? null,
                'file_path' => $storedPath,
                'original_name' => (string) $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $request->user()?->id,
            ]);

            return redirect()->back();
        }

        ResumeProgramEvidence::query()->create([
            'resume_program_annotation_id' => $annotation->id,
            'sub_kegiatan_kode' => $data['sub_kegiatan_kode'] ?? null,
            'sub_kegiatan_nama' => $data['sub_kegiatan_nama'] ?? null,
            'file_path' => $storedPath,
            'original_name' => (string) $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()?->id,
        ]);

        return redirect()->back();
    }

    public function viewRealisasiEvidence(ResumeProgramEvidence $evidence)
    {
        if (!Storage::disk('public')->exists($evidence->file_path)) {
            abort(404);
        }

        $absolutePath = Storage::disk('public')->path($evidence->file_path);
        $mimeType = $evidence->mime_type ?: Storage::disk('public')->mimeType($evidence->file_path) ?: 'application/octet-stream';
        $filename = basename((string) ($evidence->original_name ?: 'evidence'));

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function store(StoreKomponenAnggaranRequest $request)
    {
        DB::transaction(function () use ($request) {
            $komponen = KomponenAnggaran::create($request->only([
                'parent_id', 'kode', 'kode_program', 'jenis', 'opd_id', 'sub_unit', 'urusan', 'bidang_urusan', 'nama_komponen', 'tahun', 'document_type'
            ]) + ['document_type' => 'dpa']);
            foreach (($request->indikator ?? []) as $indikator) {
                $payload = $this->normalizeIndikatorPayload($indikator);
                if (!empty($payload['nama_indikator'])) {
                    $komponen->indikator()->create($payload);
                }
            }
        });
        return redirect()->back()->with('success', 'Data berhasil disimpan');
    }

    public function update(StoreKomponenAnggaranRequest $request, KomponenAnggaran $anggaran)
    {
        $validated = $request->validated();
        $masterType = $request->input('master_type');
        $masterId = $request->input('master_id');
        $masterData = null;

        if ($masterType && $masterId) {
            $masterData = match ($masterType) {
                'program' => Program::query()->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])->findOrFail((int) $masterId),
                'kegiatan' => Kegiatan::query()->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])->findOrFail((int) $masterId),
                'sub_kegiatan' => SubKegiatan::query()->select(['id', 'opd_id', 'kode_rek', 'nama_rincian', 'pagu'])->findOrFail((int) $masterId),
                default => null,
            };

            if (!$masterData) {
                return redirect()->back()->with('error', 'Data master tidak valid.');
            }

            if ((int) $masterData->opd_id !== (int) ($validated['opd_id'] ?? $anggaran->opd_id)) {
                return redirect()->back()->with('error', 'Data master tidak sesuai dengan kode unit terpilih.');
            }
        }

        $targetKode = $masterData?->kode_rek ?? $validated['kode'];
        $targetJenis = $anggaran->jenis;
        $targetNama = $masterData?->nama_rincian ?? $validated['nama_komponen'];
        $targetPagu = $targetJenis === 'sub_kegiatan' && $masterData ? (int) ($masterData->pagu ?? 0) : (int) ($anggaran->pagu ?? 0);

        $duplicate = KomponenAnggaran::query()
            ->where('id', '!=', $anggaran->id)
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->where('kode', $targetKode)
            ->where('jenis', $targetJenis)
            ->where('opd_id', $validated['opd_id'] ?? $anggaran->opd_id)
            ->first();

        if ($duplicate) {
            return redirect()->back()->with('warning', 'Data sudah ditambahkan pada unit ini.');
        }

        DB::transaction(function () use ($request, $anggaran, $masterData, $targetKode, $targetJenis, $targetNama, $targetPagu) {
            $payload = $request->only([
                'parent_id', 'kode', 'kode_program', 'jenis', 'opd_id', 'sub_unit', 'urusan', 'bidang_urusan', 'nama_komponen', 'tahun', 'document_type'
            ]);
            $payload['document_type'] = $payload['document_type'] ?? 'dpa';

            if ($masterData) {
                $payload['parent_id'] = $anggaran->parent_id;
                $payload['kode'] = $targetKode;
                $payload['kode_program'] = $targetJenis === 'program' ? $targetKode : $this->extractKodeProgram($targetKode);
                $payload['jenis'] = $targetJenis;
                $payload['opd_id'] = $masterData->opd_id;
                $payload['sub_unit'] = $anggaran->sub_unit;
                [$urusan, $bidangUrusan] = $this->extractUrusanCodes($targetKode);
                $payload['urusan'] = $urusan;
                $payload['bidang_urusan'] = $bidangUrusan;
                $payload['nama_komponen'] = $this->truncateText((string) $targetNama, 255);
                if ($targetJenis === 'sub_kegiatan') {
                    $payload['pagu'] = $targetPagu;
                }
            }

            $anggaran->update($payload);
            $anggaran->indikator()->delete();
            foreach (($request->indikator ?? []) as $indikator) {
                $payload = $this->normalizeIndikatorPayload($indikator);
                if (!empty($payload['nama_indikator'])) {
                    $anggaran->indikator()->create($payload);
                }
            }
        });
        return redirect()->back()->with('success', 'Data berhasil diupdate');
    }

    public function storeIndikator(KomponenAnggaran $anggaran)
    {
        $validated = request()->validate([
            'nama_indikator'   => 'required|string',
            'sifat_indikator'  => 'required|in:positif,negatif,akumulatif',
            'target_indikator' => 'required|string|max:100',
            'satuan'           => 'required|string|max:50',
        ], [
            'nama_indikator.required' => 'Nama indikator wajib diisi.',
            'sifat_indikator.required' => 'Sifat indikator wajib dipilih.',
            'sifat_indikator.in' => 'Sifat indikator tidak valid.',
            'target_indikator.required' => 'Target indikator wajib diisi.',
            'satuan.required' => 'Satuan indikator wajib diisi.',
        ]);

        $anggaran->indikator()->create($validated);

        return redirect()->back()->with('success', 'Indikator berhasil ditambahkan.');
    }

    public function updateIndikator(IndikatorAnggaran $indikator)
    {
        $validated = request()->validate([
            'nama_indikator'   => 'required|string',
            'sifat_indikator'  => 'required|in:positif,negatif,akumulatif',
            'target_indikator' => 'required|string|max:100',
            'satuan'           => 'required|string|max:50',
        ], [
            'nama_indikator.required' => 'Nama indikator wajib diisi.',
            'sifat_indikator.required' => 'Sifat indikator wajib dipilih.',
            'sifat_indikator.in' => 'Sifat indikator tidak valid.',
            'target_indikator.required' => 'Target indikator wajib diisi.',
            'satuan.required' => 'Satuan indikator wajib diisi.',
        ]);

        $indikator->update($validated);

        return redirect()->back()->with('success', 'Indikator berhasil diubah.');
    }

    public function destroyIndikator(IndikatorAnggaran $indikator)
    {
        $indikator->delete();

        return redirect()->back()->with('success', 'Indikator berhasil dihapus.');
    }

    public function bulkUpdatePagu(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'pagu'   => 'required|array|max:2000',
            'pagu.*' => 'nullable|integer|min:0',
        ], [
            'pagu.required' => 'Data pagu wajib dikirim.',
            'pagu.*.integer' => 'Nilai pagu harus berupa angka bulat.',
            'pagu.*.min'     => 'Nilai pagu tidak boleh negatif.',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['pagu'] as $id => $value) {
                KomponenAnggaran::where('id', (int) $id)
                    ->where('jenis', 'sub_kegiatan')
                    ->update(['pagu' => (int) ($value ?? 0)]);
            }
        });

        return redirect()->back()->with('success', 'Pagu berhasil disimpan.');
    }

    public function bulkSave(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'pagu' => 'nullable|array|max:2000',
            'pagu.*' => 'nullable|integer|min:0',
            'indikator_target' => 'nullable|array|max:5000',
            'indikator_target.*' => 'nullable|string|max:100',
        ], [
            'pagu.*.integer' => 'Nilai pagu harus berupa angka bulat.',
            'pagu.*.min' => 'Nilai pagu tidak boleh negatif.',
            'indikator_target.*.max' => 'Target indikator maksimal 100 karakter.',
        ]);

        $hasPagu = !empty($validated['pagu']);
        $hasTarget = !empty($validated['indikator_target']);

        if (!$hasPagu && !$hasTarget) {
            return redirect()->back()->with('warning', 'Tidak ada perubahan yang disimpan.');
        }

        DB::transaction(function () use ($validated, $hasPagu, $hasTarget) {
            if ($hasPagu) {
                foreach ($validated['pagu'] as $id => $value) {
                    KomponenAnggaran::where('id', (int) $id)
                        ->where('jenis', 'sub_kegiatan')
                        ->update(['pagu' => (int) ($value ?? 0)]);
                }
            }

            if ($hasTarget) {
                foreach ($validated['indikator_target'] as $id => $value) {
                    IndikatorAnggaran::where('id', (int) $id)
                        ->update(['target_indikator' => $value !== null ? trim((string) $value) : null]);
                }
            }
        });

        return redirect()->back()->with('success', 'Perubahan berhasil disimpan.');
    }

    public function attachFromMaster(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'opd_id' => 'required|exists:opds,id',
            'tahun' => 'nullable|integer',
            'parent_id' => 'nullable|exists:komponen_anggaran,id',
            'master_type' => 'required|in:program,kegiatan,sub_kegiatan',
            'master_id' => 'required|integer',
        ], [
            'master_type.required' => 'Jenis data master wajib dipilih.',
            'master_type.in' => 'Jenis data master tidak valid.',
            'master_id.required' => 'Data master wajib dipilih.',
        ]);

        $opd = Opd::query()->select(['id', 'nama'])->findOrFail((int) $validated['opd_id']);
        $parent = null;

        if (!empty($validated['parent_id'])) {
            $parent = KomponenAnggaran::query()->findOrFail((int) $validated['parent_id']);
        }

        $masterType = $validated['master_type'];
        $masterId = (int) $validated['master_id'];
        $tahun = (int) ($validated['tahun'] ?? date('Y'));

        $master = match ($masterType) {
            'program' => Program::query()->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])->findOrFail($masterId),
            'kegiatan' => Kegiatan::query()->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])->findOrFail($masterId),
            'sub_kegiatan' => SubKegiatan::query()->select(['id', 'opd_id', 'kode_rek', 'nama_rincian', 'pagu'])->findOrFail($masterId),
        };

        if ((int) $master->opd_id !== (int) $opd->id) {
            return redirect()->back()->with('error', 'Data master tidak sesuai dengan kode unit terpilih.');
        }

        if ($masterType === 'program' && $parent) {
            return redirect()->back()->with('error', 'Program harus ditambahkan pada level root.');
        }

        if ($masterType === 'kegiatan') {
            if (!$parent || $parent->jenis !== 'program') {
                return redirect()->back()->with('error', 'Kegiatan hanya bisa ditambahkan dari baris program.');
            }

            if (!$this->isDescendantKode($master->kode_rek, $parent->kode)) {
                return redirect()->back()->with('error', 'Kode rekening kegiatan bukan turunan dari program yang dipilih.');
            }
        }

        if ($masterType === 'sub_kegiatan') {
            if (!$parent || $parent->jenis !== 'kegiatan') {
                return redirect()->back()->with('error', 'Sub kegiatan hanya bisa ditambahkan dari baris kegiatan.');
            }

            if (!$this->isDescendantKode($master->kode_rek, $parent->kode)) {
                return redirect()->back()->with('error', 'Kode rekening sub kegiatan bukan turunan dari kegiatan yang dipilih.');
            }
        }

        $kode = trim((string) $master->kode_rek);
        $jenis = $masterType;
        [$urusan, $bidangUrusan] = $this->extractUrusanCodes($kode);
        $kodeProgram = $jenis === 'program' ? $kode : $this->extractKodeProgram($kode);
        $pagu = $jenis === 'sub_kegiatan' ? (int) ($master->pagu ?? 0) : 0;

        $existing = KomponenAnggaran::query()
            ->where('parent_id', $parent?->id)
            ->where('kode', $kode)
            ->where('jenis', $jenis)
            ->where('opd_id', $opd->id)
            ->first();

        if ($existing) {
            return redirect()->back()->with('warning', 'Data sudah ditambahkan pada unit ini.');
        }

        KomponenAnggaran::create([
            'parent_id' => $parent?->id,
            'kode' => $kode,
            'kode_program' => $kodeProgram,
            'jenis' => $jenis,
            'opd_id' => $opd->id,
            'sub_unit' => $opd->nama,
            'urusan' => $urusan,
            'bidang_urusan' => $bidangUrusan,
            'nama_komponen' => $this->truncateText((string) $master->nama_rincian, 255),
            'pagu' => $pagu,
            'tahun' => $tahun,
        ]);

        return redirect()->back()->with('success', 'Data berhasil ditambahkan.');
    }

    public function destroy(KomponenAnggaran $anggaran)
    {
        $anggaran->delete();
        return redirect()->back()->with('success', 'Data berhasil dihapus');
    }

    private function mapKomponenWithReferenceNames($rows, array $realisasiLookup = [], array $realisasiRefLookup = [])
    {
        return $rows->map(function ($row) use ($realisasiLookup, $realisasiRefLookup) {
            $row->urusan = $row->urusanRef?->nama ?? $row->urusan;
            $row->bidang_urusan = $row->bidangUrusanRef?->nama ?? $row->bidang_urusan;

            $lookupKey = $this->makeRealisasiLookupKey($row->jenis, (string) $row->kode, (int) $row->opd_id);
            $row->realisasi_tw = $realisasiLookup[$lookupKey] ?? [];
            $row->realisasi_ref = $realisasiRefLookup[$lookupKey] ?? null;

            if ($row->relationLoaded('children') && $row->children) {
                $row->setRelation('children', $this->mapKomponenWithReferenceNames($row->children, $realisasiLookup, $realisasiRefLookup));
            }

            return $row;
        });
    }

    private function mergeKomponenTreesForRealisasi(Collection $dpaRows, Collection $renjaRows, ?Collection $renstraRows = null): Collection
    {
        $merged = [];

        foreach ($dpaRows as $row) {
            $key = $this->makeMergeNodeKey($row);
            $merged[$key] = $this->toMergedNode($row, 'dpa');
        }

        foreach ($renjaRows as $row) {
            $key = $this->makeMergeNodeKey($row);
            $incoming = $this->toMergedNode($row, 'renja');

            if (!isset($merged[$key])) {
                $merged[$key] = $incoming;
                continue;
            }

            $merged[$key] = $this->mergeNodePayload($merged[$key], $incoming);
        }

        foreach (($renstraRows ?? collect()) as $row) {
            $key = $this->makeMergeNodeKey($row);
            $incoming = $this->toMergedNode($row, 'renstra');

            if (!isset($merged[$key])) {
                $merged[$key] = $incoming;
                continue;
            }

            $merged[$key] = $this->mergeNodePayload($merged[$key], $incoming);
        }

        return collect(array_values($merged))
            ->sortBy('kode')
            ->values();
    }

    private function makeMergeNodeKey($row): string
    {
        return implode('|', [
            (string) ($row->jenis ?? ''),
            trim((string) ($row->kode ?? '')),
            (int) ($row->opd_id ?? 0),
        ]);
    }

    private function toMergedNode($row, string $source): array
    {
        $children = collect($row->children ?? []);
        $indikator = collect($row->indikator ?? []);

        return [
            'id' => (int) ($row->id ?? 0),
            'kode' => (string) ($row->kode ?? ''),
            'kode_program' => (string) ($row->kode_program ?? ''),
            'jenis' => (string) ($row->jenis ?? ''),
            'opd_id' => (int) ($row->opd_id ?? 0),
            'sub_unit' => (string) ($row->sub_unit ?? ''),
            'urusan' => (string) ($row->urusan ?? ''),
            'bidang_urusan' => (string) ($row->bidang_urusan ?? ''),
            'nama_komponen' => (string) ($row->nama_komponen ?? ''),
            'pagu' => (int) ($row->pagu ?? 0),
            'pagu_dpa' => $source === 'dpa' ? (int) ($row->pagu ?? 0) : 0,
            'pagu_renstra' => $source === 'renstra' ? (int) ($row->pagu ?? 0) : 0,
            'pagu_renja' => $source === 'renja' ? (int) ($row->pagu ?? 0) : 0,
            'realisasi_tw' => $source === 'dpa' ? ($row->realisasi_tw ?? []) : [],
            'realisasi_ref' => $source === 'dpa' ? ($row->realisasi_ref ?? null) : null,
            'indikator' => $this->normalizeMergedIndikator($indikator, $source),
            'children' => $children
                ->map(fn ($child) => $this->toMergedNode($child, $source))
                ->values()
                ->all(),
        ];
    }

    private function mergeNodePayload(array $base, array $incoming): array
    {
        if (empty($base['id']) && !empty($incoming['id'])) {
            $base['id'] = $incoming['id'];
        }

        $base['pagu_dpa'] = (int) ($base['pagu_dpa'] ?? 0) + (int) ($incoming['pagu_dpa'] ?? 0);
        $base['pagu_renstra'] = (int) ($base['pagu_renstra'] ?? 0) + (int) ($incoming['pagu_renstra'] ?? 0);
        $base['pagu_renja'] = (int) ($base['pagu_renja'] ?? 0) + (int) ($incoming['pagu_renja'] ?? 0);

        if (empty($base['realisasi_ref']) && !empty($incoming['realisasi_ref'])) {
            $base['realisasi_ref'] = $incoming['realisasi_ref'];
        }

        if (empty($base['realisasi_tw']) && !empty($incoming['realisasi_tw'])) {
            $base['realisasi_tw'] = $incoming['realisasi_tw'];
        }

        $base['indikator'] = $this->mergeIndikatorPayload(
            (array) ($base['indikator'] ?? []),
            (array) ($incoming['indikator'] ?? [])
        );

        $mergedChildren = [];

        foreach ((array) ($base['children'] ?? []) as $child) {
            $childKey = $this->makeMergeNodeKey((object) $child);
            $mergedChildren[$childKey] = $child;
        }

        foreach ((array) ($incoming['children'] ?? []) as $child) {
            $childKey = $this->makeMergeNodeKey((object) $child);

            if (!isset($mergedChildren[$childKey])) {
                $mergedChildren[$childKey] = $child;
                continue;
            }

            $mergedChildren[$childKey] = $this->mergeNodePayload($mergedChildren[$childKey], $child);
        }

        $base['children'] = collect(array_values($mergedChildren))
            ->sortBy('kode')
            ->values()
            ->all();

        return $base;
    }

    private function normalizeMergedIndikator(Collection $indikator, string $source): array
    {
        return $indikator
            ->map(function ($item) use ($source) {
                $target = (string) ($item->target_indikator ?? '');

                return [
                    'id' => $source === 'dpa' ? (int) ($item->id ?? 0) : null,
                    'nama_indikator' => (string) ($item->nama_indikator ?? ''),
                    'sifat_indikator' => (string) ($item->sifat_indikator ?? ''),
                    'target_indikator' => $target,
                    'target_dpa' => $source === 'dpa' ? $target : '0',
                    'target_renstra' => $source === 'renstra' ? $target : '0',
                    'target_renja' => $source === 'renja' ? $target : '0',
                    'satuan' => (string) ($item->satuan ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    private function mergeIndikatorPayload(array $base, array $incoming): array
    {
        $merged = [];

        foreach ($base as $indikator) {
            $key = $this->makeIndikatorMergeKey($indikator);
            $merged[$key] = $indikator;
        }

        foreach ($incoming as $indikator) {
            $key = $this->makeIndikatorMergeKey($indikator);

            if (!isset($merged[$key])) {
                $merged[$key] = $indikator;
                continue;
            }

            $existing = $merged[$key];

            if (empty($existing['id']) && !empty($indikator['id'])) {
                $existing['id'] = $indikator['id'];
            }

            $existing['target_dpa'] = ((string) ($existing['target_dpa'] ?? '0')) !== '0'
                ? (string) ($existing['target_dpa'] ?? '0')
                : (string) ($indikator['target_dpa'] ?? '0');

            $existing['target_renstra'] = ((string) ($existing['target_renstra'] ?? '0')) !== '0'
                ? (string) ($existing['target_renstra'] ?? '0')
                : (string) ($indikator['target_renstra'] ?? '0');

            $existing['target_renja'] = ((string) ($existing['target_renja'] ?? '0')) !== '0'
                ? (string) ($existing['target_renja'] ?? '0')
                : (string) ($indikator['target_renja'] ?? '0');

            $existing['target_indikator'] = ((string) ($existing['target_dpa'] ?? '0')) !== '0'
                ? (string) ($existing['target_dpa'] ?? '0')
                : (((string) ($existing['target_renstra'] ?? '0')) !== '0'
                    ? (string) ($existing['target_renstra'] ?? '0')
                    : (string) ($existing['target_renja'] ?? '0'));

            $merged[$key] = $existing;
        }

        return array_values($merged);
    }

    private function makeIndikatorMergeKey(array $indikator): string
    {
        return implode('|', [
            trim((string) ($indikator['nama_indikator'] ?? '')),
            trim((string) ($indikator['satuan'] ?? '')),
            trim((string) ($indikator['sifat_indikator'] ?? '')),
        ]);
    }

    private function buildRealisasiLookup(array $opdFilterIds, $tahun, array &$realisasiRefLookup = []): array
    {
        $lookup = [];
        $modelMap = [
            'program' => Program::class,
            'kegiatan' => Kegiatan::class,
            'sub_kegiatan' => SubKegiatan::class,
        ];

        foreach ($modelMap as $jenis => $modelClass) {
            $masterQuery = $modelClass::query()
                ->select(['id', 'opd_id', 'kode_rek'])
                ->where('document_type', 'dpa');

            if (!empty($opdFilterIds)) {
                $masterQuery->whereIn('opd_id', $opdFilterIds);
            }

            if ($tahun) {
                $masterQuery->where('tahun', $tahun);
            }

            $masters = $masterQuery->get();
            if ($masters->isEmpty()) {
                continue;
            }

            foreach ($masters as $masterRef) {
                $refKey = $this->makeRealisasiLookupKey($jenis, (string) $masterRef->kode_rek, (int) $masterRef->opd_id);
                $realisasiRefLookup[$refKey] = [
                    'realisaseable_id' => (int) $masterRef->id,
                    'realisaseable_type' => $modelClass,
                ];
            }

            $masterById = $masters->keyBy('id');
            $masterIds = $masters->pluck('id')->values();

            $realisasiQuery = Realisasi::query()
                ->select(['id', 'realisaseable_id', 'triwulan', 'realisasi_keuangan', 'realisasi_fisik', 'tahun'])
                ->where('realisaseable_type', $modelClass)
                ->whereIn('realisaseable_id', $masterIds);

            if ($tahun) {
                $realisasiQuery->where('tahun', $tahun);
            }

            $realisasiRows = $realisasiQuery->get();

            foreach ($realisasiRows as $row) {
                $master = $masterById->get((int) $row->realisaseable_id);
                if (!$master) {
                    continue;
                }

                $key = $this->makeRealisasiLookupKey($jenis, (string) $master->kode_rek, (int) $master->opd_id);
                $tw = (int) ($row->triwulan ?? 0);

                if ($tw < 1 || $tw > 4) {
                    continue;
                }

                if (!isset($lookup[$key])) {
                    $lookup[$key] = [];
                }

                $lookup[$key][$tw] = [
                    'id' => (int) $row->id,
                    'keuangan' => (float) ($row->realisasi_keuangan ?? 0),
                    'fisik' => (float) ($row->realisasi_fisik ?? 0),
                ];
            }
        }

        return $lookup;
    }

    private function makeRealisasiLookupKey(string $jenis, string $kode, int $opdId): string
    {
        return implode('|', [$jenis, trim($kode), $opdId]);
    }

    private function getRealisasiAnnotationsPayload(string $pageMode, string $documentType, ?int $opdId, ?int $tahun): array
    {
        if ($pageMode !== 'realisasi' || $documentType !== 'dpa' || !$opdId) {
            return [];
        }

        $annotations = ResumeProgramAnnotation::query()
            ->with('evidences')
            ->where('view', 'realisasi-dpa')
            ->where('table_name', 'realisasi')
            ->where('basis', 'opd')
            ->where('entitas', (string) $opdId)
            ->when($tahun !== null, fn ($query) => $query->where('tahun', $tahun))
            ->get();

        $result = [];

        foreach ($annotations as $annotation) {
            $key = $this->buildRealisasiAnnotationKey((int) $opdId, $tahun, (string) ($annotation->program_kode ?? ''), (string) $annotation->program_nama);

            $result[$key] = [
                'faktor_penghambat' => $annotation->faktor_penghambat,
                'faktor_pendorong' => $annotation->faktor_pendorong,
                'faktor_tindak_lanjut' => $annotation->faktor_tindak_lanjut,
                'evidences' => $annotation->evidences->map(function (ResumeProgramEvidence $evidence) {
                    return [
                        'id' => $evidence->id,
                        'sub_kegiatan_kode' => $evidence->sub_kegiatan_kode,
                        'sub_kegiatan_nama' => $evidence->sub_kegiatan_nama,
                        'original_name' => $evidence->original_name,
                        'view_url' => route('anggaran.realisasi-evidence.view', $evidence),
                    ];
                })->values()->all(),
            ];
        }

        return $result;
    }

    private function resolveRealisasiAnnotation(array $data): ResumeProgramAnnotation
    {
        $query = ResumeProgramAnnotation::query()
            ->where('view', 'realisasi-dpa')
            ->where('table_name', 'realisasi')
            ->where('basis', 'opd')
            ->where('entitas', (string) ((int) $data['opd_id']))
            ->where('program_kode', (string) $data['program_kode'])
            ->where('program_nama', (string) $data['program_nama']);

        if (($data['tahun'] ?? null) === null) {
            $query->whereNull('tahun');
        } else {
            $query->where('tahun', (int) $data['tahun']);
        }

        $annotation = $query->first();
        if ($annotation) {
            return $annotation;
        }

        return ResumeProgramAnnotation::query()->create([
            'view' => 'realisasi-dpa',
            'table_name' => 'realisasi',
            'basis' => 'opd',
            'tahun' => $data['tahun'] ?? null,
            'entitas' => (string) ((int) $data['opd_id']),
            'program_kode' => (string) $data['program_kode'],
            'program_nama' => (string) $data['program_nama'],
        ]);
    }

    private function buildRealisasiAnnotationKey(int $opdId, ?int $tahun, string $programKode, string $programNama): string
    {
        $tahunKey = $tahun ?? 0;
        return strtoupper(trim((string) $opdId)).'|'.strtoupper(trim((string) $tahunKey)).'|'.strtoupper(trim($programKode)).'|'.strtoupper(trim($programNama));
    }

    private function normalizeIndikatorPayload(array $indikator): array
    {
        return [
            'nama_indikator' => $indikator['nama_indikator'] ?? $indikator['tolok_ukur'] ?? null,
            'sifat_indikator' => $indikator['sifat_indikator'] ?? null,
            'target_indikator' => $indikator['target_indikator'] ?? null,
            'satuan' => $indikator['satuan'] ?? null,
        ];
    }

    private function isDescendantKode(string $childKode, string $parentKode): bool
    {
        $child = trim($childKode);
        $parent = trim($parentKode);

        if ($child === '' || $parent === '') {
            return false;
        }

        return str_starts_with($child, $parent . '.');
    }

    private function extractKodeProgram(string $kode): string
    {
        $parts = array_values(array_filter(explode('.', $kode), fn ($part) => $part !== ''));

        return implode('.', array_slice($parts, 0, 3));
    }

    private function extractUrusanCodes(string $kode): array
    {
        $parts = array_values(array_filter(explode('.', $kode), fn ($part) => $part !== ''));
        $urusan = $parts[0] ?? '';
        $bidangUrusan = count($parts) >= 2 ? $parts[0] . '.' . $parts[1] : $urusan;

        return [$urusan, $bidangUrusan];
    }

    private function truncateText(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max);
    }

    private function resolveOpdFilterIds($opdId): array
    {
        if (!$opdId) {
            return [];
        }

        $selected = Opd::query()->select(['id', 'kode'])->find($opdId);

        if (!$selected) {
            return [(int) $opdId];
        }

        $parentPrefixMap = [
            // Sekretariat Daerah parent -> semua Bagian/Sub Bagian
            '4.01.0.00.0.00.14.0000' => '4.01',
            // Dinas Kesehatan parent -> semua Puskesmas/RS unit turunannya
            '1.02.2.14.0.00.02.0000' => '1.02.2.14.0.00.02.',
        ];

        $prefix = $parentPrefixMap[$selected->kode] ?? null;

        if (!$prefix) {
            return [$selected->id];
        }

        return Opd::query()
            ->where('kode', 'like', $prefix . '%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
