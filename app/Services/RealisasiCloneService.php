<?php
namespace App\Services;

use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\KomponenAnggaran;
use App\Models\Realisasi;
use App\Models\ResumeProgramAnnotation;
use App\Models\ResumeProgramEvidence;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class RealisasiCloneService
{
    public function buildPayloadForOpd(?int $opdId, ?int $tahun = null, string $pageMode = 'realisasi', string $documentType = 'dpa', bool $includeRenstra = true): array
    {
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

        if ($includeRenstra && in_array($pageMode, ['realisasi', 'verifikator'], true) && $documentType === 'dpa') {
            $renjaQuery = KomponenAnggaran::with([
                'indikator', 'urusanRef:id,kode,nama', 'bidangUrusanRef:id,kode,nama',
                'children.indikator', 'children.urusanRef:id,kode,nama', 'children.bidangUrusanRef:id,kode,nama',
                'children.children.indikator', 'children.children.urusanRef:id,kode,nama', 'children.children.bidangUrusanRef:id,kode,nama',
            ])->where('document_type', 'renja')->whereNull('parent_id');

            if ($opdId) $renjaQuery->whereIn('opd_id', $opdFilterIds);
            if ($tahun) $renjaQuery->where('tahun', $tahun);

            $renjaData = $this->mapKomponenWithReferenceNames($renjaQuery->orderBy('kode')->get());

            $renstraQuery = KomponenAnggaran::with([
                'indikator', 'urusanRef:id,kode,nama', 'bidangUrusanRef:id,kode,nama',
                'children.indikator', 'children.urusanRef:id,kode,nama', 'children.bidangUrusanRef:id,kode,nama',
                'children.children.indikator', 'children.children.urusanRef:id,kode,nama', 'children.children.bidangUrusanRef:id,kode,nama',
            ])->where('document_type', 'renstra')->whereNull('parent_id');

            if ($opdId) $renstraQuery->whereIn('opd_id', $opdFilterIds);

            $renstraData = $this->mapKomponenWithReferenceNames($renstraQuery->orderBy('kode')->get());
            $data = $this->mergeKomponenTreesForRealisasi($data, $renjaData, $renstraData);
        }

        $opds  = \App\Models\Opd::where('is_active', true)->orderBy('nama')->get(['id','nama','kode']);
        $tahunList = range(date('Y') - 2, date('Y') + 2);

        // master program list
        $programQuery = KomponenAnggaran::where('jenis', 'program')->where('document_type', 'dpa')->whereNull('parent_id');
        if ($opdId) $programQuery->whereIn('opd_id', $opdFilterIds);
        if ($tahun) $programQuery->where('tahun', $tahun);

        $masterProgramList = $programQuery->with(['indikator','bidangUrusanRef:id,kode,nama'])->orderBy('kode')->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'kode' => $p->kode,
                'kode_program' => $p->kode_program,
                'nama' => $p->nama_komponen,
                'bidang' => $p->bidangUrusanRef?->nama ?? $p->bidang_urusan,
                'opd_id' => $p->opd_id,
                'indikator' => $p->indikator->map(fn($i) => [
                    'nama_indikator' => $this->prettifyIndikatorName((string) ($i->nama_indikator ?? '')),
                    'sifat_indikator'=> $i->sifat_indikator,
                    'target_indikator'=> $i->target_indikator,
                    'satuan' => $i->satuan,
                ])->values()->all(),
            ]);

        $programReferensi = collect();
        $kegiatanReferensi = collect();
        $subKegiatanReferensi = collect();

        if ($opdId) {
            $programMasterQuery = Program::query()->select(['id','opd_id','kode_rek','nama_rincian'])->where('document_type','dpa')->whereIn('opd_id',$opdFilterIds);
            $kegiatanMasterQuery = Kegiatan::query()->select(['id','opd_id','kode_rek','nama_rincian'])->where('document_type','dpa')->whereIn('opd_id',$opdFilterIds);
            $subKegiatanMasterQuery = SubKegiatan::query()->select(['id','opd_id','kode_rek','nama_rincian','pagu'])->where('document_type','dpa')->whereIn('opd_id',$opdFilterIds);
            if ($tahun) {
                $programMasterQuery->where('tahun', $tahun);
                $kegiatanMasterQuery->where('tahun', $tahun);
                $subKegiatanMasterQuery->where('tahun', $tahun);
            }

            $programReferensi = $programMasterQuery->orderBy('kode_rek')->get()->map(fn($row) => ['id'=>$row->id,'opd_id'=>$row->opd_id,'kode'=>$row->kode_rek,'nama'=>$row->nama_rincian]);
            $kegiatanReferensi = $kegiatanMasterQuery->orderBy('kode_rek')->get()->map(fn($row) => ['id'=>$row->id,'opd_id'=>$row->opd_id,'kode'=>$row->kode_rek,'nama'=>$row->nama_rincian]);
            $subKegiatanReferensi = $subKegiatanMasterQuery->orderBy('kode_rek')->get()->map(fn($row) => ['id'=>$row->id,'opd_id'=>$row->opd_id,'kode'=>$row->kode_rek,'nama'=>$row->nama_rincian,'pagu'=>(int)($row->pagu ?? 0)]);
        }

        return [
            'data' => $data,
            'opds' => $opds,
            'tahunList' => $tahunList,
            'masterProgramList' => $masterProgramList,
            'masterReferensi' => [
                'program' => $programReferensi,
                'kegiatan' => $kegiatanReferensi,
                'sub_kegiatan' => $subKegiatanReferensi,
            ],
            'pageMode' => $pageMode,
            'documentType' => $documentType,
            'realisasiAnnotations' => $this->getRealisasiAnnotationsPayload($pageMode, $documentType, $opdId ? (int) $opdId : null, $tahun ? (int) $tahun : null),
        ];
    }

    private function buildRealisasiLookup(array $opdFilterIds, $tahun, array &$realisasiRefLookup = []): array
    {
        $lookup = [];
        $modelMap = ['program' => Program::class, 'kegiatan' => Kegiatan::class, 'sub_kegiatan' => SubKegiatan::class];

        foreach ($modelMap as $jenis => $modelClass) {
            $masterQuery = $modelClass::query()->select(['id','opd_id','kode_rek'])->where('document_type','dpa');
            if (!empty($opdFilterIds)) $masterQuery->whereIn('opd_id', $opdFilterIds);
            if ($tahun) $masterQuery->where('tahun', $tahun);
            $masters = $masterQuery->get();
            if ($masters->isEmpty()) continue;

            foreach ($masters as $masterRef) {
                $refKey = $this->makeRealisasiLookupKey($jenis, (string)$masterRef->kode_rek, (int)$masterRef->opd_id);
                $realisasiRefLookup[$refKey] = ['realisaseable_id' => (int)$masterRef->id, 'realisaseable_type' => $modelClass];
            }

            $masterById = $masters->keyBy('id');
            $masterIds = $masters->pluck('id')->values();

            $realisasiQuery = Realisasi::query()->select(['id','realisaseable_id','triwulan','realisasi_keuangan','realisasi_fisik','tahun','is_verified','catatan_verifikator'])
                ->where('realisaseable_type', $modelClass)
                ->whereIn('realisaseable_id', $masterIds);

            if ($tahun) $realisasiQuery->where('tahun', $tahun);
            $realisasiRows = $realisasiQuery->get();

            foreach ($realisasiRows as $row) {
                $master = $masterById->get((int)$row->realisaseable_id);
                if (!$master) continue;
                $key = $this->makeRealisasiLookupKey($jenis, (string)$master->kode_rek, (int)$master->opd_id);
                $tw = (int)($row->triwulan ?? 0);
                if ($tw < 1 || $tw > 4) continue;
                if (!isset($lookup[$key])) $lookup[$key] = [];
                $lookup[$key][$tw] = ['id' => (int)$row->id, 'keuangan' => (float)($row->realisasi_keuangan ?? 0), 'fisik' => (float)($row->realisasi_fisik ?? 0), 'is_verified' => (bool)($row->is_verified ?? false), 'catatan_verifikator' => $row->catatan_verifikator];
            }
        }

        return $lookup;
    }

    private function makeRealisasiLookupKey(string $jenis, string $kode, int $opdId): string
    {
        return implode('|', [$jenis, trim($kode), $opdId]);
    }

    private function mapKomponenWithReferenceNames($rows, array $realisasiLookup = [], array $realisasiRefLookup = [])
    {
        return $rows->map(function ($row) use ($realisasiLookup, $realisasiRefLookup) {
            $row->urusan = $row->urusanRef?->nama ?? $row->urusan;
            $row->bidang_urusan = $row->bidangUrusanRef?->nama ?? $row->bidang_urusan;
            $lookupKey = $this->makeRealisasiLookupKey($row->jenis, (string)$row->kode, (int)$row->opd_id);
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
            if (!isset($merged[$key])) { $merged[$key] = $incoming; continue; }
            $merged[$key] = $this->mergeNodePayload($merged[$key], $incoming);
        }

        foreach (($renstraRows ?? collect()) as $row) {
            $key = $this->makeMergeNodeKey($row);
            $incoming = $this->toMergedNode($row, 'renstra');
            if (!isset($merged[$key])) { $merged[$key] = $incoming; continue; }
            $merged[$key] = $this->mergeNodePayload($merged[$key], $incoming);
        }

        return collect(array_values($merged))->sortBy('kode')->values();
    }

    private function makeMergeNodeKey($row): string
    {
        return implode('|', [(string)($row->jenis ?? ''), trim((string)($row->kode ?? '')), (int)($row->opd_id ?? 0)]);
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
            'children' => $children->map(fn($child) => $this->toMergedNode($child, $source))->values()->all(),
        ];
    }

    private function mergeNodePayload(array $base, array $incoming): array
    {
        if (empty($base['id']) && !empty($incoming['id'])) $base['id'] = $incoming['id'];
        $base['pagu_dpa'] = (int)($base['pagu_dpa'] ?? 0) + (int)($incoming['pagu_dpa'] ?? 0);
        $base['pagu_renstra'] = (int)($base['pagu_renstra'] ?? 0) + (int)($incoming['pagu_renstra'] ?? 0);
        $base['pagu_renja'] = (int)($base['pagu_renja'] ?? 0) + (int)($incoming['pagu_renja'] ?? 0);
        if (empty($base['realisasi_ref']) && !empty($incoming['realisasi_ref'])) $base['realisasi_ref'] = $incoming['realisasi_ref'];
        if (empty($base['realisasi_tw']) && !empty($incoming['realisasi_tw'])) $base['realisasi_tw'] = $incoming['realisasi_tw'];

        $base['indikator'] = $this->mergeIndikatorPayload((array)($base['indikator'] ?? []), (array)($incoming['indikator'] ?? []));

        $mergedChildren = [];
        foreach ((array)($base['children'] ?? []) as $child) {
            $childKey = $this->makeMergeNodeKey((object)$child);
            $mergedChildren[$childKey] = $child;
        }
        foreach ((array)($incoming['children'] ?? []) as $child) {
            $childKey = $this->makeMergeNodeKey((object)$child);
            if (!isset($mergedChildren[$childKey])) { $mergedChildren[$childKey] = $child; continue; }
            $mergedChildren[$childKey] = $this->mergeNodePayload($mergedChildren[$childKey], $child);
        }

        $base['children'] = collect(array_values($mergedChildren))->sortBy('kode')->values()->all();
        return $base;
    }

    private function normalizeMergedIndikator(Collection $indikator, string $source): array
    {
        return $indikator->map(function ($item) use ($source) {
            $target = (string) ($item->target_indikator ?? '');
            return [
                'id' => $source === 'dpa' ? (int) ($item->id ?? 0) : null,
                'nama_indikator' => $this->prettifyIndikatorName((string) ($item->nama_indikator ?? '')),
                'sifat_indikator' => (string) ($item->sifat_indikator ?? ''),
                'target_indikator' => $target,
                'target_dpa' => $source === 'dpa' ? $target : '0',
                'target_renstra' => $source === 'renstra' ? $target : '0',
                'target_renja' => $source === 'renja' ? $target : '0',
                'satuan' => (string) ($item->satuan ?? ''),
            ];
        })->values()->all();
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
            if (!isset($merged[$key])) { $merged[$key] = $indikator; continue; }
            $existing = $merged[$key];
            if (empty($existing['id']) && !empty($indikator['id'])) $existing['id'] = $indikator['id'];
            $existing['target_dpa'] = ((string)($existing['target_dpa'] ?? '0')) !== '0' ? (string)($existing['target_dpa'] ?? '0') : (string)($indikator['target_dpa'] ?? '0');
            $existing['target_renstra'] = ((string)($existing['target_renstra'] ?? '0')) !== '0' ? (string)($existing['target_renstra'] ?? '0') : (string)($indikator['target_renstra'] ?? '0');
            $existing['target_renja'] = ((string)($existing['target_renja'] ?? '0')) !== '0' ? (string)($existing['target_renja'] ?? '0') : (string)($indikator['target_renja'] ?? '0');
            $existing['target_indikator'] = ((string)($existing['target_dpa'] ?? '0')) !== '0' ? (string)($existing['target_dpa'] ?? '0') : (((string)($existing['target_renstra'] ?? '0')) !== '0' ? (string)($existing['target_renstra'] ?? '0') : (string)($existing['target_renja'] ?? '0'));
            $merged[$key] = $existing;
        }
        return array_values($merged);
    }

    private function makeIndikatorMergeKey(array $indikator): string
    {
        $nama = $this->normalizeForKey((string)($indikator['nama_indikator'] ?? ''));
        $satuan = $this->normalizeForKey((string)($indikator['satuan'] ?? ''));
        $sifat = $this->normalizeForKey((string)($indikator['sifat_indikator'] ?? ''));
        return implode('|', [$nama, $satuan, $sifat]);
    }

    private function normalizeForKey(string $text): string
    {
        $text = Str::ascii($text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '', $text);
        return trim((string)$text);
    }

    private function prettifyIndikatorName(string $text): string
    {
        $text = trim($text);
        if ($text === '') return $text;
        if (strpos($text, ' ') !== false) return $text;
        $t = Str::ascii($text);
        $t = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $t);
        $t = preg_replace('/(?<=[A-Za-z])(?=[0-9])|(?<=[0-9])(?=[A-Za-z])/', ' ', $t);
        $t = preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $t);
        return trim($t);
    }

    private function getRealisasiAnnotationsPayload(string $pageMode, string $documentType, ?int $opdId, ?int $tahun): array
    {
        if (!in_array($pageMode, ['realisasi','verifikator'], true) || $documentType !== 'dpa' || !$opdId) {
            return [];
        }

        $annotations = ResumeProgramAnnotation::query()->with('evidences')
            ->where('view','realisasi-dpa')
            ->where('table_name','realisasi')
            ->where('basis','opd')
            ->where('entitas',(string)$opdId)
            ->when($tahun !== null, fn($q) => $q->where('tahun', $tahun))
            ->get();

        $result = [];
        foreach ($annotations as $annotation) {
            $key = $this->buildRealisasiAnnotationKey((int)$opdId, $tahun, (string)($annotation->program_kode ?? ''), (string)($annotation->program_nama ?? ''));
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

    private function buildRealisasiAnnotationKey(int $opdId, ?int $tahun, string $programKode, string $programNama): string
    {
        $tahunKey = $tahun ?? 0;
        return strtoupper(trim((string)$opdId)).'|'.strtoupper(trim((string)$tahunKey)).'|'.strtoupper(trim((string)$programKode)).'|'.strtoupper(trim((string)$programNama));
    }

    private function resolveOpdFilterIds($opdId): array
    {
        if (!$opdId) return [];
        $selected = \App\Models\Opd::query()->select(['id','kode'])->find($opdId);
        if (!$selected) return [(int)$opdId];
        $parentPrefixMap = ['4.01.0.00.0.00.14.0000' => '4.01', '1.02.2.14.0.00.02.0000' => '1.02.2.14.0.00.02.'];
        $prefix = $parentPrefixMap[$selected->kode] ?? null;
        if (!$prefix) return [$selected->id];
        return \App\Models\Opd::query()->where('kode','like',$prefix.'%')->pluck('id')->map(fn($id) => (int)$id)->values()->all();
    }

    private function truncateText(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) return $text;
        return mb_substr($text, 0, $max);
    }
}
