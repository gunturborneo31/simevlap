<?php

namespace App\Services\Resume2;

use App\Models\Opd;
use App\Models\Sasaran;
use App\Models\SubKegiatan;
use App\Models\Tujuan;
use App\Services\Resume2\Concerns\ComparesResumeItems;
use App\Services\Resume2\Concerns\ReadsKomponenAnggaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data asli Hasil Pelaksanaan RKPD (tabel 1-6) untuk Resume 2.
 *
 * Sumber data:
 * - Tujuan, sasaran, dan indikatornya (target RPJMD per tahun): tabel tujuan/sasaran, relasi dari menu Relasi
 *   (`data_dasar_relasi`), dan pivot `indikatorables`.
 * - Program Aksi: program RPJMD berjenis "program-aksi".
 * - Renstra / Renja (RKPD) / DPA: `komponen_anggaran` + `indikator_anggaran`.
 * - Realisasi: tabel `realisasi` tingkat sub kegiatan DPA (diinput di menu Realisasi). Nilainya kumulatif sampai
 *   triwulan berjalan; kegiatan dan program dijumlahkan dari sub kegiatannya.
 *
 * Yang belum tersedia di data aplikasi ditampilkan "-" (null), tidak diisi angka perkiraan.
 */
class HasilPelaksanaanData
{
    use ComparesResumeItems;
    use ReadsKomponenAnggaran;

    // ---------------------------------------------------------------------
    // Tabel 1: Tujuan & Sasaran RPJMD
    // ---------------------------------------------------------------------

    /**
     * Indikator tujuan dan sasaran dengan target RPJMD tahun terpilih. Target RKPD dan capaian belum
     * tercatat di aplikasi untuk tingkat tujuan/sasaran, sehingga bernilai null.
     */
    public function tujuanSasaran(?int $tahun): array
    {
        $tujuanRows = Tujuan::query()->get(['id', 'kode', 'uraian']);
        $sasaranRows = Sasaran::query()->get(['id', 'kode', 'uraian']);
        $resolveTujuan = $this->idResolver($tujuanRows);
        $resolveSasaran = $this->idResolver($sasaranRows);

        $indikator = DB::table('indikatorables as ia')
            ->join('indikator as i', 'i.id', '=', 'ia.indikator_id')
            ->whereIn('ia.indicatorable_type', [Tujuan::class, Sasaran::class])
            ->when($tahun !== null, fn ($q) => $q->where('ia.tahun', $tahun))
            ->orderBy('ia.id')
            ->get(['ia.indicatorable_type', 'ia.indicatorable_id', 'i.uraian', 'i.satuan', 'ia.target', 'ia.realisasi']);

        $byOwner = [];
        foreach ($indikator as $row) {
            $owner = $row->indicatorable_type === Tujuan::class
                ? $resolveTujuan((int) $row->indicatorable_id)
                : $resolveSasaran((int) $row->indicatorable_id);
            if ($owner === null) {
                continue;
            }
            $byOwner[$row->indicatorable_type][$owner->id][] = [
                'nama' => trim(preg_replace('/\s+/', ' ', (string) $row->uraian)),
                'satuan' => $this->cleanValue($row->satuan),
                'target_rpjmd' => $this->cleanValue($row->target),
                'target_rkpd' => null,
                'capaian' => $this->cleanValue($row->realisasi),
            ];
        }

        // Relasi tujuan → sasaran dari menu Relasi (ID lama dipetakan lewat nomor kode, lihat idResolver()).
        $sasaranOf = [];
        $linked = [];
        foreach (DB::table('data_dasar_relasi')->where('parent_type', 'tujuan')->where('child_type', 'sasaran')->orderBy('id')->get() as $rel) {
            $t = $resolveTujuan((int) $rel->parent_id);
            $s = $resolveSasaran((int) $rel->child_id);
            if ($t && $s && ! isset($linked[$s->id])) {
                $sasaranOf[$t->id][] = $s;
                $linked[$s->id] = true;
            }
        }

        $placeholder = [['nama' => '-', 'satuan' => null, 'target_rpjmd' => null, 'target_rkpd' => null, 'capaian' => null]];
        $result = [];
        foreach ($tujuanRows->sortBy(fn ($t) => $this->kodeNumber($t->kode))->values() as $i => $t) {
            $sasaran = [];
            if (! empty($byOwner[Tujuan::class][$t->id])) {
                $sasaran[] = ['nama' => 'Indikator Tujuan', 'indikator' => $byOwner[Tujuan::class][$t->id]];
            }
            foreach (collect($sasaranOf[$t->id] ?? [])->sortBy(fn ($s) => $this->kodeNumber($s->kode)) as $s) {
                $sasaran[] = ['nama' => $this->label($s), 'indikator' => $byOwner[Sasaran::class][$s->id] ?? $placeholder];
            }
            $result[] = ['no' => $i + 1, 'nama' => $this->label($t), 'sasaran' => $sasaran ?: [['nama' => '-', 'indikator' => $placeholder]]];
        }

        $orphans = $sasaranRows->reject(fn ($s) => isset($linked[$s->id]))->sortBy(fn ($s) => $this->kodeNumber($s->kode));
        if ($orphans->isNotEmpty()) {
            $result[] = [
                'no' => count($result) + 1,
                'nama' => 'Sasaran belum terhubung ke tujuan',
                'sasaran' => $orphans->map(fn ($s) => ['nama' => $this->label($s), 'indikator' => $byOwner[Sasaran::class][$s->id] ?? $placeholder])->values()->all(),
            ];
        }

        return ['tujuan' => $result];
    }

    // ---------------------------------------------------------------------
    // Tabel 2: Program Aksi Kepala Daerah
    // ---------------------------------------------------------------------

    /**
     * Program aksi RPJMD. Relasi program aksi → program prioritas pendukung belum ada di aplikasi,
     * sehingga kolom pendukung, indikator, target, dan capaian bernilai null.
     */
    public function programAksi(?int $tahun): array
    {
        $aksi = DB::table('program')
            ->where('jenis_program', 'program-aksi')
            ->orderBy('id')
            ->get(['kode_rek', 'nama_rincian'])
            ->unique(fn ($p) => $this->normalize((string) $p->kode_rek) . '|' . $this->nameKey((string) $p->nama_rincian))
            ->sortBy(fn ($p) => $this->kodeNumber((string) $p->kode_rek))
            ->values();

        return ['aksi' => $aksi->map(fn ($p, $i) => [
            'no' => $i + 1,
            'nama' => trim(preg_replace('/\s+/', ' ', (string) $p->nama_rincian)),
            'capaian' => null,
            'anggaran' => null,
            'programs' => [['program' => null, 'indikator' => null, 'target' => null, 'satuan' => null, 'capaian' => null]],
        ])->all()];
    }

    // ---------------------------------------------------------------------
    // Tabel 3: Program Prioritas RPJMD
    // ---------------------------------------------------------------------

    /**
     * Program prioritas (program Renstra yang ditandai prioritas) per perangkat daerah: pagu dan target tahunan
     * periode Renstra, realisasi keuangan DPA sampai triwulan terpilih. Tahun pertama periode dipakai sebagai
     * kondisi awal; capaian kinerja belum tercatat di tingkat indikator program.
     */
    public function programPrioritas(?int $tahun, int $tw): array
    {
        $komponen = DB::table('komponen_anggaran as ka')
            ->join('program as p', function ($join) {
                $join->on('p.opd_id', '=', 'ka.opd_id')
                    ->on('p.kode_rek', '=', 'ka.kode_program')
                    ->where('p.document_type', 'renstra')
                    ->where('p.is_prioritas', true);
            })
            ->where('ka.document_type', 'renstra')
            ->where('ka.jenis', 'program')
            ->whereNotNull('ka.opd_id')
            ->orderBy('ka.kode_program')
            ->get(['ka.id', 'ka.opd_id', 'ka.kode_program', 'ka.nama_komponen', 'ka.pagu_tahunan'])
            ->unique('id');

        $years = [];
        foreach ($komponen as $k) {
            $years += array_flip(array_map('intval', array_keys($this->decodeYearly($k->pagu_tahunan) ?? [])));
        }
        ksort($years);
        $years = array_keys($years);
        $tahunAwal = $years[0] ?? null;
        $targetYears = array_slice($years, 1);

        $indikator = $this->indikatorRows($komponen->pluck('id')->all());
        $realisasi = $this->realisasi($tahun);
        $opdNames = Opd::query()->pluck('nama', 'id');

        $byOpd = [];
        foreach ($komponen as $k) {
            $paguTahunan = $this->decodeYearly($k->pagu_tahunan) ?? [];
            $pagu = [];
            foreach ($targetYears as $year) {
                $pagu[$year] = isset($paguTahunan[$year]) ? (int) $paguTahunan[$year] : null;
            }

            $lines = [];
            foreach ($indikator[$k->id] ?? [] as $ind) {
                $target = [];
                foreach ($targetYears as $year) {
                    $target[$year] = $this->cleanValue($ind['target_tahunan'][$year] ?? null);
                }
                $lines[] = [
                    'nama' => $ind['nama'],
                    'kondisi_awal' => $tahunAwal !== null ? $this->cleanValue($ind['target_tahunan'][$tahunAwal] ?? null) : null,
                    'target' => $target,
                    'capaian' => null,
                    'rata_kinerja' => null,
                ];
            }

            $real = $realisasi['program'][$k->opd_id . '|' . $this->normalize((string) $k->kode_program)]['keu'][$tw] ?? null;
            $paguTahun = $tahun !== null ? ($paguTahunan[$tahun] ?? null) : null;
            $byOpd[$k->opd_id][] = [
                'nama' => trim($k->kode_program . ' - ' . $k->nama_komponen, ' -'),
                'pagu' => $pagu,
                'realisasi_rp' => $real,
                'rata_rp' => $real !== null && $paguTahun ? round($real / $paguTahun * 100, 2) : null,
                'indikator' => $lines ?: [['nama' => '-', 'kondisi_awal' => null, 'target' => array_fill_keys($targetYears, null), 'capaian' => null, 'rata_kinerja' => null]],
            ];
        }

        $no = 0;
        $opds = [];
        foreach (collect($byOpd)->sortBy(fn ($_, $opdId) => $opdNames[$opdId] ?? '') as $opdId => $programs) {
            foreach ($programs as &$program) {
                $program['no'] = ++$no;
            }
            unset($program);
            $opds[] = ['opd' => $opdNames[$opdId] ?? '-', 'programs' => $programs];
        }

        return ['opds' => $opds, 'tahunAwal' => $tahunAwal, 'years' => $targetYears, 'tahunTarget' => $tahun];
    }

    // ---------------------------------------------------------------------
    // Tabel 4: Realisasi program/kegiatan/sub kegiatan RKPD per bidang urusan
    // ---------------------------------------------------------------------

    /**
     * Program → kegiatan → sub kegiatan Renja (RKPD) perangkat daerah dalam satu bidang urusan, dengan indikator,
     * target, pagu, dan realisasi DPA sampai triwulan terpilih. Predikat (Permendagri 86/2017) dinilai terhadap
     * target proporsional sampai triwulan berjalan.
     */
    public function realisasiBidang(string $bidangKode, ?int $tahun, int $tw): array
    {
        [, $groupKeyOf] = $this->entities('bidang-urusan');
        $opdIds = Opd::query()->pluck('id')->filter(fn ($id) => $groupKeyOf((int) $id) === $bidangKode)->values()->all();

        $komponen = DB::table('komponen_anggaran')
            ->where('document_type', 'renja')
            ->whereIn('opd_id', $opdIds ?: [0])
            ->when($tahun !== null, fn ($q) => $q->where('tahun', $tahun))
            ->get(['id', 'opd_id', 'jenis', 'kode', 'kode_program', 'nama_komponen']);

        [$paguById] = $this->effectivePagu($tahun, ['renja']);
        $indikator = $this->indikatorRows($komponen->pluck('id')->all());
        $realisasi = $this->realisasi($tahun);
        $dpaNames = $this->dpaNames($tahun, $opdIds);
        $opdNames = Opd::query()->whereIn('id', $opdIds ?: [0])->pluck('nama', 'id');
        $faktorTw = $tw / 4;

        $groups = [];
        foreach ($komponen->groupBy('opd_id')->sortBy(fn ($_, $opdId) => $opdNames[$opdId] ?? '') as $opdId => $rows) {
            $lines = [];
            foreach ($rows->sortBy(fn ($r) => $this->naturalKey((string) $r->kode))->values() as $r) {
                $kode = trim((string) ($r->jenis === 'program' ? ($r->kode_program ?: $r->kode) : $r->kode));
                $pagu = $paguById[$r->id] ?? 0;
                $real = $realisasi[$r->jenis][$opdId . '|' . $this->normalize($kode)] ?? null;
                $keuangan = $real['keu'][$tw] ?? null;
                $fisik = $real['fisik'][$tw] ?? null;
                $persenKeuangan = $keuangan !== null && $pagu > 0 ? round($keuangan / $pagu * 100, 2) : null;

                $lines[] = [
                    'level' => $r->jenis,
                    'kode' => $kode,
                    'nama' => $dpaNames[$r->jenis . '|' . $opdId . '|' . $this->normalize($kode)] ?? trim((string) $r->nama_komponen),
                    'indikator' => array_column($indikator[$r->id] ?? [], 'nama'),
                    'satuan' => array_map(fn ($i) => $i['satuan'] ?? '-', $indikator[$r->id] ?? []),
                    'target' => array_map(fn ($i) => $i['target'] ?? '-', $indikator[$r->id] ?? []),
                    'pagu' => $pagu,
                    'capaian' => null,
                    'persen_kinerja' => $fisik,
                    'realisasi' => $keuangan,
                    'persen_keuangan' => $persenKeuangan,
                    'predikat_kinerja' => $fisik === null ? null : $this->predikat($fisik / $faktorTw),
                    'predikat_keuangan' => $persenKeuangan === null ? null : $this->predikat($persenKeuangan / $faktorTw),
                ];
            }
            $groups[] = ['opd' => $opdNames[$opdId] ?? '-', 'lines' => $lines];
        }

        return ['groups' => $groups];
    }

    // ---------------------------------------------------------------------
    // Tabel 5 & 6: Evaluasi hasil RKPD / Renja
    // ---------------------------------------------------------------------

    /**
     * Tabel 5: evaluasi program RKPD (Renja) per bidang urusan. Program belum dipetakan ke sasaran RPJMD di aplikasi,
     * sehingga pengelompokan memakai bidang urusan.
     */
    public function evaluasiRkpd(?int $tahun): array
    {
        [$entities, $groupKeyOf] = $this->entities('bidang-urusan');
        $context = $this->evaluasiContext($tahun, ['program']);
        $opdNames = Opd::query()->pluck('nama', 'id');

        $byGroup = [];
        foreach ($context['renja'] as $r) {
            $groupKey = $groupKeyOf((int) $r->opd_id);
            if ($groupKey === null) {
                continue;
            }
            $byGroup[$groupKey][] = [
                'kode' => $r->kode_norm,
                'nama' => trim((string) $r->nama_komponen),
                'opd' => $opdNames[$r->opd_id] ?? '-',
                'indikator' => $this->evaluasiLines($r, $context, $tahun),
            ];
        }

        $groups = [];
        foreach ($entities as [$groupKey, $label]) {
            if (empty($byGroup[$groupKey])) {
                continue;
            }
            $programs = $byGroup[$groupKey];
            usort($programs, fn ($a, $b) => strnatcmp($a['kode'], $b['kode']) ?: strcmp($a['opd'], $b['opd']));
            $groups[] = ['no' => count($groups) + 1, 'nama' => $label, 'programs' => $programs];
        }

        return ['sasaran' => $groups, 'groupLabel' => 'Bidang Urusan'];
    }

    /**
     * Tabel 6: evaluasi program dan kegiatan Renja satu perangkat daerah.
     */
    public function evaluasiRenja(int $opdId, ?int $tahun): array
    {
        $context = $this->evaluasiContext($tahun, ['program', 'kegiatan'], $opdId);

        $rows = collect($context['renja'])->sortBy(fn ($r) => $this->naturalKey($r->kode_norm))->values();
        $lines = [];
        foreach ($rows as $r) {
            foreach ($this->evaluasiLines($r, $context, $tahun) as $i => $line) {
                $lines[] = ['level' => $r->jenis, 'nama' => $i === 0 ? trim($r->kode_norm . ' - ' . $r->nama_komponen) : ''] + $line;
            }
        }

        return [
            'sasaran' => null,
            'opd' => Opd::query()->whereKey($opdId)->value('nama') ?? '-',
            'lines' => $lines,
        ];
    }

    /**
     * Data bersama tabel evaluasi: komponen Renja (tahun terpilih), padanan Renstra per "opd|kode", pagu efektif,
     * indikator, dan realisasi DPA.
     *
     * @param  array<int, string>  $levels
     */
    private function evaluasiContext(?int $tahun, array $levels, ?int $opdId = null): array
    {
        $renja = DB::table('komponen_anggaran')
            ->where('document_type', 'renja')
            ->whereIn('jenis', $levels)
            ->whereNotNull('opd_id')
            ->when($opdId !== null, fn ($q) => $q->where('opd_id', $opdId))
            ->when($tahun !== null, fn ($q) => $q->where('tahun', $tahun))
            ->get(['id', 'opd_id', 'jenis', 'kode', 'kode_program', 'nama_komponen']);
        $dpaNames = $this->dpaNames($tahun, $opdId !== null ? [$opdId] : null);
        $renja = $renja->map(function ($r) use ($dpaNames) {
            $r->kode_norm = trim((string) ($r->jenis === 'program' ? ($r->kode_program ?: $r->kode) : $r->kode));
            $r->nama_komponen = $dpaNames[$r->jenis . '|' . $r->opd_id . '|' . $this->normalize($r->kode_norm)] ?? $r->nama_komponen;

            return $r;
        });

        $renstra = DB::table('komponen_anggaran')
            ->where('document_type', 'renstra')
            ->whereIn('jenis', $levels)
            ->whereNotNull('opd_id')
            ->when($opdId !== null, fn ($q) => $q->where('opd_id', $opdId))
            ->get(['id', 'opd_id', 'jenis', 'kode', 'kode_program', 'pagu', 'pagu_tahunan']);
        $renstraByKey = [];
        foreach ($renstra as $r) {
            $kode = trim((string) ($r->jenis === 'program' ? ($r->kode_program ?: $r->kode) : $r->kode));
            $renstraByKey[$r->jenis . '|' . $r->opd_id . '|' . $this->normalize($kode)] ??= $r;
        }

        [$paguById] = $this->effectivePagu($tahun, ['renja']);

        return [
            'renja' => $renja,
            'renstra' => $renstraByKey,
            'pagu' => $paguById,
            'indikator' => $this->indikatorRows(array_merge($renja->pluck('id')->all(), $renstra->pluck('id')->all())),
            'realisasi' => $this->realisasi($tahun),
        ];
    }

    /**
     * Baris evaluasi untuk satu komponen Renja: satu baris per indikator Renja (dipadankan ke indikator Renstra
     * lewat nama). Nilai rupiah milik program/kegiatan sehingga hanya diisi di baris pertama.
     *
     * Kolom kinerja (realisasi per triwulan, tahun berjalan, s.d. tahun lalu) belum tercatat di aplikasi → null.
     * Realisasi rupiah s.d. tahun lalu juga belum ada, sehingga kumulatif = realisasi tahun ini.
     */
    private function evaluasiLines(object $r, array $context, ?int $tahun): array
    {
        $key = $r->opd_id . '|' . $this->normalize($r->kode_norm);
        $renstra = $context['renstra'][$r->jenis . '|' . $key] ?? null;
        $renstraIndikator = [];
        foreach ($renstra ? ($context['indikator'][$renstra->id] ?? []) : [] as $ind) {
            $renstraIndikator[$this->nameKey($ind['nama'])] = $ind;
        }

        $paguTahunan = $renstra ? ($this->decodeYearly($renstra->pagu_tahunan) ?? []) : [];
        $targetPeriodeRp = $renstra ? (isset($paguTahunan[$tahun]) ? (int) $paguTahunan[$tahun] : null) : null;
        $real = $context['realisasi'][$r->jenis][$key]['keu'] ?? null;

        $twRp = [];
        $previous = 0;
        foreach ([1, 2, 3, 4] as $tw) {
            $cumulative = $real[$tw] ?? null;
            $twRp[] = $cumulative === null ? null : $cumulative - $previous;
            $previous = $cumulative ?? $previous;
        }
        // Realisasi tahun berjalan = nilai kumulatif triwulan terakhir yang sudah diinput.
        $realisasiTahun = $real === null ? null : $real[max(array_keys($real))];

        $indikator = $context['indikator'][$r->id] ?? [];
        $lines = [];
        foreach ($indikator ?: [['nama' => '-', 'target' => null, 'satuan' => null]] as $i => $ind) {
            $first = $i === 0;
            $renstraInd = $renstraIndikator[$this->nameKey($ind['nama'])] ?? null;
            $lines[] = [
                'indikator' => $ind['nama'],
                'satuan' => $ind['satuan'],
                'target_periode' => [
                    'k' => $renstraInd ? $this->cleanValue($renstraInd['target_tahunan'][$tahun] ?? null) : null,
                    'rp' => $first ? $targetPeriodeRp : null,
                ],
                'realisasi_lalu' => ['k' => null, 'rp' => null],
                'target_tahun' => ['k' => $ind['target'], 'rp' => $first ? ($context['pagu'][$r->id] ?? null) : null],
                'tw' => array_map(fn ($rp) => ['k' => null, 'rp' => $first ? $rp : null], $twRp),
                'realisasi_tahun' => ['k' => null, 'rp' => $first ? $realisasiTahun : null],
                'kumulatif' => ['k' => null, 'rp' => $first ? $realisasiTahun : null],
                'tingkat' => [
                    'k' => null,
                    'rp' => $first && $realisasiTahun !== null && $targetPeriodeRp ? round($realisasiTahun / $targetPeriodeRp * 100, 2) : null,
                ],
            ];
        }

        return $lines;
    }

    // ---------------------------------------------------------------------
    // Opsi filter
    // ---------------------------------------------------------------------

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function bidangOptions(): array
    {
        [$entities] = $this->entities('bidang-urusan');

        return array_map(fn ($e) => ['value' => (string) $e[0], 'label' => $e[1]], $entities);
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function opdOptions(): array
    {
        return Opd::query()->orderBy('nama')->get(['id', 'nama'])
            ->map(fn (Opd $o) => ['value' => (int) $o->id, 'label' => (string) $o->nama])
            ->all();
    }

    // ---------------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------------

    /**
     * Realisasi DPA tahun terpilih per "opd|kode" untuk sub kegiatan, kegiatan, dan program.
     * Nilai keuangan kumulatif per triwulan (triwulan tanpa input memakai nilai triwulan sebelumnya, hanya sampai
     * triwulan terakhir yang sudah diinput);
     * kegiatan/program = jumlah sub kegiatannya. Fisik (%) kegiatan/program = rata-rata sub kegiatannya;
     * null bila sepanjang tahun belum ada realisasi fisik yang diisi.
     *
     * @return array<string, array<string, array{keu: array<int, float>, fisik: array<int, float>}>>
     */
    private function realisasi(?int $tahun): array
    {
        $rows = DB::table('realisasi as r')
            ->join('sub_kegiatan as s', 's.id', '=', 'r.realisaseable_id')
            ->where('r.realisaseable_type', SubKegiatan::class)
            ->where('s.document_type', 'dpa')
            ->when($tahun !== null, fn ($q) => $q->where('r.tahun', $tahun))
            ->get(['s.opd_id', 's.kode_rek', 'r.triwulan', 'r.realisasi_keuangan', 'r.realisasi_fisik']);

        $raw = [];
        $lastTw = 0;
        $fisikDiisi = false;
        foreach ($rows as $row) {
            $tw = (int) $row->triwulan;
            if ($tw >= 1 && $tw <= 4) {
                $raw[$row->opd_id . '|' . $this->normalize((string) $row->kode_rek)][$tw] = [(float) $row->realisasi_keuangan, (float) $row->realisasi_fisik];
                $lastTw = max($lastTw, $tw);
                $fisikDiisi = $fisikDiisi || (float) $row->realisasi_fisik > 0;
            }
        }

        $result = ['sub_kegiatan' => [], 'kegiatan' => [], 'program' => []];
        if ($lastTw === 0) {
            return $result;
        }
        $fisikCount = [];
        foreach ($raw as $key => $byTw) {
            [$opdId, $kode] = explode('|', $key, 2);
            $segments = explode('.', $kode);
            $parents = [
                'sub_kegiatan' => $key,
                'kegiatan' => $opdId . '|' . implode('.', array_slice($segments, 0, -1)),
                'program' => $opdId . '|' . implode('.', array_slice($segments, 0, 3)),
            ];

            // Triwulan setelah triwulan terakhir yang sudah diinput (di seluruh data) dibiarkan kosong.
            $keu = null;
            $fisik = null;
            foreach (range(1, $lastTw) as $tw) {
                [$keu, $fisik] = $byTw[$tw] ?? [$keu, $fisik];
                if ($keu === null) {
                    continue;
                }
                foreach ($parents as $level => $parentKey) {
                    $result[$level][$parentKey]['keu'][$tw] = ($result[$level][$parentKey]['keu'][$tw] ?? 0) + $keu;
                    $result[$level][$parentKey]['fisik'][$tw] = ($result[$level][$parentKey]['fisik'][$tw] ?? 0) + $fisik;
                    $fisikCount[$level][$parentKey][$tw] = ($fisikCount[$level][$parentKey][$tw] ?? 0) + 1;
                }
            }
        }

        foreach ($fisikCount as $level => $keys) {
            foreach ($keys as $key => $counts) {
                foreach ($counts as $tw => $n) {
                    // Bila sepanjang tahun belum ada satu pun realisasi fisik yang diisi (semua 0), angka 0 berarti
                    // "belum diinput", bukan capaian nol → dikosongkan.
                    $result[$level][$key]['fisik'][$tw] = $fisikDiisi ? round($result[$level][$key]['fisik'][$tw] / $n, 2) : null;
                }
            }
        }

        return $result;
    }

    /**
     * Nama komponen DPA per "jenis|opd|kode". Nama kegiatan di sebagian data Renja bergeser satu baris dari kodenya,
     * sedangkan DPA memakai nomenklatur yang benar, sehingga nama DPA didahulukan untuk kode yang sama.
     *
     * @param  array<int, int>|null  $opdIds
     * @return array<string, string>
     */
    private function dpaNames(?int $tahun, ?array $opdIds = null): array
    {
        return DB::table('komponen_anggaran')
            ->where('document_type', 'dpa')
            ->whereNotNull('opd_id')
            ->when($opdIds !== null, fn ($q) => $q->whereIn('opd_id', $opdIds ?: [0]))
            ->when($tahun !== null, fn ($q) => $q->where('tahun', $tahun))
            ->get(['opd_id', 'jenis', 'kode', 'kode_program', 'nama_komponen'])
            ->mapWithKeys(function ($r) {
                $kode = trim((string) ($r->jenis === 'program' ? ($r->kode_program ?: $r->kode) : $r->kode));

                return [$r->jenis . '|' . $r->opd_id . '|' . $this->normalize($kode) => trim((string) $r->nama_komponen)];
            })
            ->all();
    }

    /**
     * Indikator unik per komponen.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<int, array{nama: string, target: ?string, satuan: ?string, target_tahunan: array}>>
     */
    private function indikatorRows(array $ids): array
    {
        $result = [];
        $seen = [];
        foreach (array_chunk(array_values(array_unique($ids)), 2000) as $chunk) {
            $rows = DB::table('indikator_anggaran')
                ->whereIn('komponen_anggaran_id', $chunk)
                ->orderBy('id')
                ->get(['komponen_anggaran_id', 'nama_indikator', 'satuan', 'target_indikator', 'target_tahunan']);
            foreach ($rows as $row) {
                $nama = trim(preg_replace('/\s+/', ' ', (string) $row->nama_indikator));
                $key = $this->nameKey($nama);
                if ($key === '' || isset($seen[$row->komponen_anggaran_id][$key])) {
                    continue;
                }
                $seen[$row->komponen_anggaran_id][$key] = true;
                $result[$row->komponen_anggaran_id][] = [
                    'nama' => $nama,
                    'target' => $this->cleanValue($row->target_indikator),
                    'satuan' => $this->cleanValue($row->satuan),
                    'target_tahunan' => $this->decodeYearly($row->target_tahunan) ?? [],
                ];
            }
        }

        return $result;
    }

    /**
     * Cari baris tujuan/sasaran dari ID yang tersimpan di indikatorables / data_dasar_relasi. Data tersebut masih
     * memakai ID lama (sebelum data dasar diimpor ulang), sehingga ID yang tidak ditemukan dipetakan ke baris
     * dengan nomor kode yang sama (ID lama N = "Sasaran N" / "tujuan N").
     *
     * @return callable(int): ?object
     */
    private function idResolver(Collection $rows): callable
    {
        $byId = $rows->keyBy('id');
        $byNumber = $rows->keyBy(fn ($r) => $this->kodeNumber((string) $r->kode));

        return fn (int $id) => $byId[$id] ?? $byNumber[$id] ?? null;
    }

    private function kodeNumber(string $kode): int
    {
        return preg_match('/(\d+)\s*$/', $kode, $m) ? (int) $m[1] : PHP_INT_MAX;
    }

    private function label(object $row): string
    {
        $kode = trim((string) $row->kode);

        return ($kode !== '' ? ucfirst($kode) . ' : ' : '') . trim(preg_replace('/\s+/', ' ', (string) $row->uraian));
    }

    /**
     * Kunci urut kode rekening alami: "1.01.02" < "1.01.02.2.01" < "1.01.02.2.01.0001" < "1.01.10".
     */
    private function naturalKey(string $kode): string
    {
        return implode('.', array_map(fn ($s) => str_pad($s, 6, '0', STR_PAD_LEFT), explode('.', trim($kode))));
    }

    /**
     * Predikat capaian sesuai skala Permendagri 86/2017.
     */
    private function predikat(float $persen): string
    {
        return match (true) {
            $persen >= 91 => 'Sangat Tinggi',
            $persen >= 76 => 'Tinggi',
            $persen >= 66 => 'Sedang',
            $persen >= 51 => 'Rendah',
            default => 'Sangat Rendah',
        };
    }
}
