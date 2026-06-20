<?php
namespace App\Http\Controllers;

use App\Models\IndikatorAnggaran;
use App\Models\Kegiatan;
use App\Models\KomponenAnggaran;
use App\Models\Opd;
use App\Models\Program;
use App\Http\Requests\StoreKomponenAnggaranRequest;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RenstraController extends Controller
{
    const TAHUN_LIST = [2025, 2026, 2027, 2028, 2029, 2030];

    public function index()
    {
        $opdId  = request('opd_id');
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
            ->where('document_type', 'renstra')
            ->whereNull('parent_id');

        if ($opdId) {
            $query->whereIn('opd_id', $opdFilterIds);
        }

        $data = $query->orderBy('kode')->get();
        $data = $this->mapKomponenWithReferenceNames($data);

        $opds = \App\Models\Opd::where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'kode']);

        $masterProgramList = KomponenAnggaran::where('jenis', 'program')
            ->where('document_type', 'renstra')
            ->whereNull('parent_id')
            ->when($opdId, fn ($q) => $q->whereIn('opd_id', $opdFilterIds))
            ->with(['indikator', 'bidangUrusanRef:id,kode,nama'])
            ->orderBy('kode')
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'kode'         => $p->kode,
                'kode_program' => $p->kode_program,
                'nama'         => $p->nama_komponen,
                'bidang'       => $p->bidangUrusanRef?->nama ?? $p->bidang_urusan,
                'opd_id'       => $p->opd_id,
                'indikator'    => $p->indikator->map(fn ($i) => [
                    'nama_indikator'   => $i->nama_indikator,
                    'sifat_indikator'  => $i->sifat_indikator,
                    'target_indikator' => $i->target_indikator,
                    'target_tahunan'   => $i->target_tahunan ?? [],
                    'satuan'           => $i->satuan,
                ])->values()->all(),
            ]);

        $programReferensi   = collect();
        $kegiatanReferensi  = collect();

        if ($opdId) {
            $programReferensi = Program::query()
                ->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])
                ->where('document_type', 'renstra')
                ->whereIn('opd_id', $opdFilterIds)
                ->orderBy('kode_rek')->get()
                ->map(fn ($r) => ['id' => $r->id, 'opd_id' => $r->opd_id, 'kode' => $r->kode_rek, 'nama' => $r->nama_rincian]);

            $kegiatanReferensi = Kegiatan::query()
                ->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])
                ->where('document_type', 'renstra')
                ->whereIn('opd_id', $opdFilterIds)
                ->orderBy('kode_rek')->get()
                ->map(fn ($r) => ['id' => $r->id, 'opd_id' => $r->opd_id, 'kode' => $r->kode_rek, 'nama' => $r->nama_rincian]);

        }

        return Inertia::render('DataDasar/Dokumen/Renstra/Index', [
            'data'              => $data,
            'opds'              => $opds,
            'tahunList'         => self::TAHUN_LIST,
            'masterProgramList' => $masterProgramList,
            'masterReferensi'   => [
                'program'      => $programReferensi,
                'kegiatan'     => $kegiatanReferensi,
            ],
        ]);
    }

    public function store(StoreKomponenAnggaranRequest $request)
    {
        DB::transaction(function () use ($request) {
            $komponen = KomponenAnggaran::create($request->only([
                'parent_id', 'kode', 'kode_program', 'jenis', 'opd_id', 'sub_unit', 'urusan', 'bidang_urusan', 'nama_komponen',
            ]) + ['document_type' => 'renstra', 'tahun' => null]);

            foreach (($request->indikator ?? []) as $indikator) {
                $payload = $this->normalizeIndikatorPayload($indikator);
                if (!empty($payload['nama_indikator'])) {
                    $komponen->indikator()->create($payload);
                }
            }
        });
        return redirect()->back()->with('success', 'Data berhasil disimpan');
    }

    public function update(StoreKomponenAnggaranRequest $request, KomponenAnggaran $renstra)
    {
        $validated = $request->validated();
        $masterType = $request->input('master_type');
        $masterId   = $request->input('master_id');
        $masterData = null;

        if ($masterType && $masterId) {
            $masterData = match ($masterType) {
                'program'      => Program::query()->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])->findOrFail((int) $masterId),
                'kegiatan'     => Kegiatan::query()->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])->findOrFail((int) $masterId),
                default        => null,
            };

            if ($masterData && (int) $masterData->opd_id !== (int) ($validated['opd_id'] ?? $renstra->opd_id)) {
                return redirect()->back()->with('error', 'Data master tidak sesuai dengan kode unit terpilih.');
            }
        }

        $targetKode = $masterData?->kode_rek ?? $validated['kode'];
        $targetJenis = $renstra->jenis;
        $targetNama  = $masterData?->nama_rincian ?? $validated['nama_komponen'];

        $duplicate = KomponenAnggaran::query()
            ->where('id', '!=', $renstra->id)
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->where('kode', $targetKode)
            ->where('jenis', $targetJenis)
            ->where('opd_id', $validated['opd_id'] ?? $renstra->opd_id)
            ->first();

        if ($duplicate) {
            return redirect()->back()->with('warning', 'Data sudah ditambahkan pada unit ini.');
        }

        DB::transaction(function () use ($request, $renstra, $masterData, $targetKode, $targetJenis, $targetNama) {
            $payload = $request->only(['parent_id', 'kode', 'kode_program', 'jenis', 'opd_id', 'sub_unit', 'urusan', 'bidang_urusan', 'nama_komponen']);
            $payload['document_type'] = 'renstra';

            if ($masterData) {
                $payload['parent_id']    = $renstra->parent_id;
                $payload['kode']         = $targetKode;
                $payload['kode_program'] = $targetJenis === 'program' ? $targetKode : $this->extractKodeProgram($targetKode);
                $payload['jenis']        = $targetJenis;
                $payload['opd_id']       = $masterData->opd_id;
                $payload['sub_unit']     = $renstra->sub_unit;
                [$urusan, $bidangUrusan] = $this->extractUrusanCodes($targetKode);
                $payload['urusan']       = $urusan;
                $payload['bidang_urusan'] = $bidangUrusan;
                $payload['nama_komponen'] = $this->truncateText((string) $targetNama, 255);
            }

            $renstra->update($payload);
            $renstra->indikator()->delete();

            foreach (($request->indikator ?? []) as $indikator) {
                $p = $this->normalizeIndikatorPayload($indikator);
                if (!empty($p['nama_indikator'])) {
                    $renstra->indikator()->create($p);
                }
            }
        });

        return redirect()->back()->with('success', 'Data berhasil diupdate');
    }

    public function storeIndikator(KomponenAnggaran $renstra)
    {
        $validated = request()->validate([
            'nama_indikator'   => 'required|string',
            'sifat_indikator'  => 'required|in:positif,negatif,akumulatif',
            'target_indikator' => 'nullable|string|max:100',
            'satuan'           => 'required|string|max:50',
        ]);

        $renstra->indikator()->create($validated);
        return redirect()->back()->with('success', 'Indikator berhasil ditambahkan.');
    }

    public function updateIndikator(IndikatorAnggaran $indikator)
    {
        $validated = request()->validate([
            'nama_indikator'   => 'required|string',
            'sifat_indikator'  => 'required|in:positif,negatif,akumulatif',
            'target_indikator' => 'nullable|string|max:100',
            'satuan'           => 'required|string|max:50',
        ]);

        $indikator->update($validated);
        return redirect()->back()->with('success', 'Indikator berhasil diubah.');
    }

    public function destroyIndikator(IndikatorAnggaran $indikator)
    {
        $indikator->delete();
        return redirect()->back()->with('success', 'Indikator berhasil dihapus.');
    }

    public function bulkSave(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'pagu_tahunan'                => 'nullable|array',
            'pagu_tahunan.*'              => 'nullable|array',
            'pagu_tahunan.*.*'            => 'nullable|integer|min:0',
            'indikator_target_tahunan'    => 'nullable|array',
            'indikator_target_tahunan.*'  => 'nullable|array',
            'indikator_target_tahunan.*.*' => 'nullable|string|max:100',
        ]);

        $hasPagu   = !empty($validated['pagu_tahunan']);
        $hasTarget = !empty($validated['indikator_target_tahunan']);

        if (!$hasPagu && !$hasTarget) {
            return redirect()->back()->with('warning', 'Tidak ada perubahan yang disimpan.');
        }

        DB::transaction(function () use ($validated, $hasPagu, $hasTarget) {
            if ($hasPagu) {
                foreach ($validated['pagu_tahunan'] as $id => $yearsData) {
                    $komponen = KomponenAnggaran::where('id', (int) $id)
                        ->where('jenis', 'kegiatan')
                        ->where('document_type', 'renstra')
                        ->first();
                    if (!$komponen) continue;

                    $current = $komponen->pagu_tahunan ?? [];
                    foreach ($yearsData as $tahun => $nilai) {
                        $current[(string) $tahun] = (int) ($nilai ?? 0);
                    }
                    $komponen->update(['pagu_tahunan' => $current]);
                }
            }

            if ($hasTarget) {
                foreach ($validated['indikator_target_tahunan'] as $id => $yearsData) {
                    $ind = IndikatorAnggaran::find((int) $id);
                    if (!$ind) continue;

                    $current = $ind->target_tahunan ?? [];
                    foreach ($yearsData as $tahun => $nilai) {
                        $current[(string) $tahun] = $nilai !== null ? trim((string) $nilai) : null;
                    }
                    $ind->update(['target_tahunan' => $current]);
                }
            }
        });

        return redirect()->back()->with('success', 'Perubahan berhasil disimpan.');
    }

    public function attachFromMaster(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'opd_id'      => 'required|exists:opds,id',
            'parent_id'   => 'nullable|exists:komponen_anggaran,id',
            'master_type' => 'required|in:program,kegiatan',
            'master_id'   => 'required|integer',
        ]);

        $opd    = Opd::query()->select(['id', 'nama'])->findOrFail((int) $validated['opd_id']);
        $parent = null;

        if (!empty($validated['parent_id'])) {
            $parent = KomponenAnggaran::query()->findOrFail((int) $validated['parent_id']);
        }

        $masterType = $validated['master_type'];
        $masterId   = (int) $validated['master_id'];

        $master = match ($masterType) {
            'program'      => Program::query()->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])->findOrFail($masterId),
            'kegiatan'     => Kegiatan::query()->select(['id', 'opd_id', 'kode_rek', 'nama_rincian'])->findOrFail($masterId),
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

        $kode       = trim((string) $master->kode_rek);
        $jenis      = $masterType;
        [$urusan, $bidangUrusan] = $this->extractUrusanCodes($kode);
        $kodeProgram = $jenis === 'program' ? $kode : $this->extractKodeProgram($kode);

        $existing = KomponenAnggaran::query()
            ->where('parent_id', $parent?->id)
            ->where('kode', $kode)
            ->where('jenis', $jenis)
            ->where('opd_id', $opd->id)
            ->where('document_type', 'renstra')
            ->first();

        if ($existing) {
            return redirect()->back()->with('warning', 'Data sudah ditambahkan pada unit ini.');
        }

        KomponenAnggaran::create([
            'parent_id'     => $parent?->id,
            'kode'          => $kode,
            'kode_program'  => $kodeProgram,
            'jenis'         => $jenis,
            'opd_id'        => $opd->id,
            'sub_unit'      => $opd->nama,
            'urusan'        => $urusan,
            'bidang_urusan' => $bidangUrusan,
            'nama_komponen' => $this->truncateText((string) $master->nama_rincian, 255),
            'pagu'          => 0,
            'pagu_tahunan'  => array_fill_keys(array_map('strval', self::TAHUN_LIST), 0),
            'document_type' => 'renstra',
            'tahun'         => null,
        ]);

        return redirect()->back()->with('success', 'Data berhasil ditambahkan.');
    }

    public function destroy(KomponenAnggaran $renstra)
    {
        $renstra->delete();
        return redirect()->back()->with('success', 'Data berhasil dihapus');
    }

    // ── Shared helpers (same as RenjaController) ─────────────────────────────

    private function mapKomponenWithReferenceNames($rows)
    {
        return $rows->map(function ($row) {
            $row->urusan       = $row->urusanRef?->nama ?? $row->urusan;
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
            'nama_indikator'   => $indikator['nama_indikator'] ?? null,
            'sifat_indikator'  => $indikator['sifat_indikator'] ?? null,
            'target_indikator' => $indikator['target_indikator'] ?? null,
            'satuan'           => $indikator['satuan'] ?? null,
        ];
    }

    private function isDescendantKode(string $childKode, string $parentKode): bool
    {
        return str_starts_with(trim($childKode), trim($parentKode) . '.');
    }

    private function extractKodeProgram(string $kode): string
    {
        $parts = array_values(array_filter(explode('.', $kode), fn ($p) => $p !== ''));
        return implode('.', array_slice($parts, 0, 3));
    }

    private function extractUrusanCodes(string $kode): array
    {
        $parts = array_values(array_filter(explode('.', $kode), fn ($p) => $p !== ''));
        $urusan      = $parts[0] ?? '';
        $bidangUrusan = count($parts) >= 2 ? $parts[0] . '.' . $parts[1] : $urusan;
        return [$urusan, $bidangUrusan];
    }

    private function truncateText(string $text, int $max): string
    {
        return mb_strlen($text) <= $max ? $text : mb_substr($text, 0, $max);
    }

    private function resolveOpdFilterIds($opdId): array
    {
        if (!$opdId) return [];

        $selected = Opd::query()->select(['id', 'kode'])->find($opdId);
        if (!$selected) return [];

        $children = Opd::query()
            ->select(['id', 'kode'])
            ->where('kode', 'like', $selected->kode . '%')
            ->pluck('id')
            ->values()
            ->all();

        return array_merge([$selected->id], $children);
    }

    private function applyRenjaPagu2026ToKegiatan($rows, array $opdFilterIds)
    {
        $renjaKegiatan = KomponenAnggaran::query()
            ->select(['opd_id', 'kode', 'pagu_tahunan'])
            ->where('document_type', 'renja')
            ->where('jenis', 'kegiatan')
            ->when(!empty($opdFilterIds), fn ($q) => $q->whereIn('opd_id', $opdFilterIds))
            ->get();

        $renjaPagu2026ByOpdKode = [];
        foreach ($renjaKegiatan as $row) {
            $kodeDigits = $this->normalizeKodeDigits($row->kode);
            if ($kodeDigits === '') {
                continue;
            }

            $key = $row->opd_id . '|' . $kodeDigits;
            $renjaPagu2026ByOpdKode[$key] = (int) ($row->pagu_tahunan['2026'] ?? 0);
        }

        $inject = function ($items) use (&$inject, $renjaPagu2026ByOpdKode) {
            return $items->map(function ($item) use (&$inject, $renjaPagu2026ByOpdKode) {
                if ($item->jenis === 'kegiatan') {
                    $key = $item->opd_id . '|' . $this->normalizeKodeDigits($item->kode);
                    if (array_key_exists($key, $renjaPagu2026ByOpdKode)) {
                        $paguTahunan = is_array($item->pagu_tahunan) ? $item->pagu_tahunan : [];
                        $paguTahunan['2026'] = $renjaPagu2026ByOpdKode[$key];
                        $item->pagu_tahunan = $paguTahunan;
                    }
                }

                if ($item->relationLoaded('children') && $item->children) {
                    $item->setRelation('children', $inject($item->children));
                }

                return $item;
            });
        };

        return $inject($rows);
    }

    private function normalizeKodeDigits(?string $kode): string
    {
        return preg_replace('/\D+/', '', (string) $kode) ?? '';
    }
}
