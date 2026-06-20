<?php
namespace App\Http\Controllers;

use App\Models\IndikatorAnggaran;
use App\Models\Kegiatan;
use App\Models\KomponenAnggaran;
use App\Models\Opd;
use App\Models\Program;
use App\Models\SubKegiatan;
use App\Http\Requests\StoreKomponenAnggaranRequest;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RenjaController extends Controller
{
    public function index()
    {
        $opdId = request('opd_id');
        $tahun = request('tahun');
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
            ->where('document_type', 'renja')
            ->whereNull('parent_id');

        if ($opdId) {
            $query->whereIn('opd_id', $opdFilterIds);
        }
        if ($tahun) {
            $query->where('tahun', $tahun);
        }

        $data = $query->orderBy('kode')->get();
        $data = $this->mapKomponenWithReferenceNames($data);
        $opds = \App\Models\Opd::where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'kode']);
        $tahunList = range(date('Y') - 2, date('Y') + 2);

        // Ambil daftar program RENJA yang terhubung dengan OPD yang dipilih
        // atau semua program jika belum ada filter OPD
        $programQuery = KomponenAnggaran::where('jenis', 'program')
            ->where('document_type', 'renja')
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
                ->where('document_type', 'renja')
                ->whereIn('opd_id', $opdFilterIds);

            $kegiatanMasterQuery = Kegiatan::query()
                ->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])
                ->where('document_type', 'renja')
                ->whereIn('opd_id', $opdFilterIds);

            $subKegiatanMasterQuery = SubKegiatan::query()
                ->select(['id', 'opd_id', 'kode_rek', 'nama_rincian', 'pagu'])
                ->where('document_type', 'renja')
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

        return Inertia::render('DataDasar/Dokumen/Renja/Index', [
            'data'              => $data,
            'opds'              => $opds,
            'tahunList'         => $tahunList,
            'masterProgramList' => $masterProgramList,
            'masterReferensi' => [
                'program' => $programReferensi,
                'kegiatan' => $kegiatanReferensi,
                'sub_kegiatan' => $subKegiatanReferensi,
            ],
        ]);
    }

    public function store(StoreKomponenAnggaranRequest $request)
    {
        DB::transaction(function () use ($request) {
            $komponen = KomponenAnggaran::create($request->only([
                'parent_id', 'kode', 'kode_program', 'jenis', 'opd_id', 'sub_unit', 'urusan', 'bidang_urusan', 'nama_komponen', 'tahun', 'document_type'
            ]) + ['document_type' => 'renja']);
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
            $payload['document_type'] = $payload['document_type'] ?? 'renja';

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

    private function mapKomponenWithReferenceNames($rows)
    {
        return $rows->map(function ($row) {
            $row->urusan = $row->urusanRef?->nama ?? $row->urusan;
            $row->bidang_urusan = $row->bidangUrusanRef?->nama ?? $row->bidang_urusan;

            if ($row->relationLoaded('children') && $row->children) {
                $row->setRelation('children', $this->mapKomponenWithReferenceNames($row->children));
            }

            return $row;
        });
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
            return [];
        }

        $children = Opd::query()
            ->select(['id', 'kode'])
            ->where('kode', 'like', $selected->kode . '%')
            ->pluck('id')
            ->values()
            ->all();

        return array_merge([$selected->id], $children);
    }
}
