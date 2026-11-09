<?php

namespace App\Services\Resume2;

use App\Models\Program;
use App\Services\Resume2\Concerns\ComparesResumeItems;
use App\Services\Resume2\Concerns\ReadsKomponenAnggaran;
use Illuminate\Support\Facades\DB;

/**
 * Data asli Konsistensi RPJMD - RKPD (tabel 1-4) untuk Resume 2.
 *
 * Sumber data:
 * - Daftar program: tabel `program` (sama dengan menu Resume).
 *     RPJMD   = program Renstra yang ditandai prioritas (is_prioritas).
 *     Renstra = seluruh program Renstra (dokumen 5 tahunan, tidak difilter tahun).
 *     RKPD    = program Renja/RKPD pada tahun terpilih.
 * - Indikator, target, dan pagu program: `komponen_anggaran` (jenis program) + `indikator_anggaran`,
 *   yaitu data yang diisi lewat menu Dokumen Renstra / Renja, dicocokkan per OPD + kode program.
 *     Renstra: target & pagu diambil dari nilai tahunan (target_tahunan / pagu_tahunan) tahun terpilih.
 *     Renja  : target_indikator; pagu = jumlah pagu sub kegiatannya (aturan yang sama dengan Konsistensi RKPD - APBD
 *              tabel 10, lihat ReadsKomponenAnggaran::effectivePagu()), program.pagu bila program tidak ada di komponen.
 *
 * Perbandingan (tabel 1 & 2) mengikuti Resume SIMEVLAP v1: "Sama" = ada di kedua dokumen,
 * "Tidak Sama" = hanya ada di salah satu dokumen (dihitung dari dua arah).
 */
class KonsistensiRpjmdRkpdData
{
    use ComparesResumeItems;
    use ReadsKomponenAnggaran;

    private const DOCUMENTS = ['rpjmd', 'renstra', 'rkpd'];

    private const COMPARISONS = [
        'rpjmd_renstra' => ['rpjmd', 'renstra'],
        'rpjmd_rkpd' => ['rpjmd', 'rkpd'],
        'renstra_rkpd' => ['renstra', 'rkpd'],
    ];

    /**
     * Tabel 1: jumlah program per entitas, beserta daftar program untuk modal rincian.
     */
    public function jumlahProgram(string $basis, ?int $tahun): array
    {
        [$entities, $groups] = $this->load($basis, $tahun);

        return ['rows' => $this->countRows($entities, $groups, function (array $docPrograms) {
            $items = [];
            foreach ($docPrograms as $program) {
                // Program cocok bila kode + nama sama, atau nama sama (kode berbeda antar dokumen).
                $items[] = [
                    'key' => $this->normalize($program['kode']) . '|' . $this->normalize($program['nama']),
                    'alt' => $this->nameKey($program['nama']),
                    'label' => $this->programLabel($program),
                ];
            }

            return $items;
        })];
    }

    /**
     * Tabel 2: jumlah indikator program per entitas, beserta daftar indikator untuk modal rincian.
     */
    public function jumlahIndikator(string $basis, ?int $tahun): array
    {
        [$entities, $groups] = $this->load($basis, $tahun);

        return ['rows' => $this->countRows($entities, $groups, function (array $docPrograms) {
            $items = [];
            foreach ($docPrograms as $program) {
                foreach ($program['indikator'] as $nameKey => $indikator) {
                    // Indikator dibandingkan per program agar indikator bernama sama di program lain tidak dianggap sama.
                    $items[] = [
                        'key' => $this->normalize($program['kode']) . '|' . $nameKey,
                        'alt' => $this->nameKey($program['nama']) . '|' . $nameKey,
                        'label' => $indikator['nama'] . ($program['kode'] !== '' ? " ({$program['kode']})" : ''),
                    ];
                }
            }

            return $items;
        })];
    }

    /**
     * Tabel 3: indikator dan target per program pada RPJMD, Renstra, dan RKPD, disejajarkan per nama indikator.
     */
    public function indikatorProgram(string $basis, ?int $tahun): array
    {
        [$entities, $groups] = $this->load($basis, $tahun);

        $result = [];
        foreach ($entities as [$groupKey, $label]) {
            $programs = [];
            foreach ($this->alignPrograms($groups[$groupKey] ?? []) as $aligned) {
                $names = [];
                foreach (self::DOCUMENTS as $doc) {
                    foreach ($aligned[$doc]['indikator'] ?? [] as $key => $indikator) {
                        $names[$key] ??= $indikator['nama'];
                    }
                }

                $lines = [];
                foreach (array_keys($names) ?: [null] as $key) {
                    $line = [];
                    foreach (self::DOCUMENTS as $doc) {
                        $indikator = $key === null ? null : ($aligned[$doc]['indikator'][$key] ?? null);
                        $line[$doc] = $indikator === null ? null : [
                            'indikator' => $indikator['nama'],
                            'target' => $indikator['target'],
                            'satuan' => $indikator['satuan'],
                        ];
                    }
                    $line['status'] = [];
                    foreach (self::COMPARISONS as $cmp => [$left, $right]) {
                        $line['status'][$cmp] = $this->statusIndikator($line[$left], $line[$right]);
                    }
                    $lines[] = $line;
                }

                $programs[] = ['nama' => $aligned['label'], 'lines' => $lines];
            }

            if ($programs) {
                $result[] = ['no' => count($result) + 1, 'entitas' => $label, 'programs' => $programs];
            }
        }

        return ['groups' => $result];
    }

    /**
     * Tabel 4: pagu program pada RPJMD, Renstra, dan RKPD beserta selisihnya.
     */
    public function paguProgram(string $basis, ?int $tahun): array
    {
        [$entities, $groups] = $this->load($basis, $tahun);

        $result = [];
        foreach ($entities as [$groupKey, $label]) {
            $programs = [];
            foreach ($this->alignPrograms($groups[$groupKey] ?? []) as $aligned) {
                $pagu = [];
                foreach (self::DOCUMENTS as $doc) {
                    $pagu[$doc] = (int) ($aligned[$doc]['pagu'] ?? 0);
                }

                $selisih = [];
                foreach (self::COMPARISONS as $cmp => [$left, $right]) {
                    $selisih[$cmp] = $pagu[$left] - $pagu[$right];
                }

                $programs[] = ['nama' => $aligned['label']] + $pagu + ['selisih' => $selisih];
            }

            if ($programs) {
                $result[] = ['no' => count($result) + 1, 'entitas' => $label, 'programs' => $programs];
            }
        }

        return ['groups' => $result];
    }

    // ---------------------------------------------------------------------
    // Pemuatan data
    // ---------------------------------------------------------------------

    /**
     * Muat entitas (bidang urusan / perangkat daerah) dan program per entitas per dokumen.
     *
     * @return array{0: array<int, array{0: int|string, 1: string}>, 1: array<int|string, array<string, array<string, array>>>}
     *   [entitas, groups[groupKey][rpjmd|renstra|rkpd][programKey] = program]
     */
    private function load(string $basis, ?int $tahun): array
    {
        [$entities, $groupKeyOf] = $this->entities($basis);

        $programs = Program::query()
            ->select(['opd_id', 'document_type', 'is_prioritas', 'tahun', 'kode_rek', 'nama_rincian', 'pagu'])
            ->whereNotNull('opd_id')
            ->whereIn('document_type', ['renstra', 'rkpd', 'renja'])
            ->get();

        $details = $this->komponenDetails($tahun);

        $groups = [];
        foreach ($programs as $program) {
            $groupKey = $groupKeyOf((int) $program->opd_id);
            if ($groupKey === null) {
                continue;
            }

            $isRenstra = $program->document_type === 'renstra';
            if (! $isRenstra && $tahun !== null && (int) $program->tahun !== $tahun) {
                continue;
            }

            $kode = trim((string) $program->kode_rek);
            $detail = $details[($isRenstra ? 'renstra' : 'renja') . '|' . $program->opd_id . '|' . $kode] ?? null;

            $item = [
                'kode' => $kode,
                'nama' => trim((string) $program->nama_rincian),
                'pagu' => $detail['pagu'] ?? (int) $program->pagu,
                'indikator' => $detail['indikator'] ?? [],
            ];

            if ($isRenstra) {
                $this->addProgram($groups[$groupKey]['renstra'], $item);
                if ($program->is_prioritas) {
                    $this->addProgram($groups[$groupKey]['rpjmd'], $item);
                }
            } else {
                $this->addProgram($groups[$groupKey]['rkpd'], $item);
            }
        }

        return [$entities, $groups];
    }

    /**
     * Pagu dan indikator tingkat program dari komponen_anggaran, dikunci "dokumen|opd_id|kode_program".
     *
     * @return array<string, array{pagu: ?int, indikator: array<string, array{nama: string, target: ?string, satuan: ?string}>}>
     */
    private function komponenDetails(?int $tahun): array
    {
        $komponen = DB::table('komponen_anggaran')
            ->where('jenis', 'program')
            ->whereNotNull('opd_id')
            ->where(function ($q) use ($tahun) {
                $q->where('document_type', 'renstra')
                    ->orWhere(fn ($renja) => $renja->where('document_type', 'renja')
                        ->when($tahun !== null, fn ($w) => $w->where('tahun', $tahun)));
            })
            ->get(['id', 'opd_id', 'document_type', 'kode', 'kode_program', 'pagu', 'pagu_tahunan']);

        // Pagu Renja: jumlah pagu sub kegiatan (sama dengan Konsistensi RKPD - APBD tabel 10).
        [$renjaPagu] = $this->effectivePagu($tahun, ['renja']);

        $indikatorByKomponen = DB::table('indikator_anggaran')
            ->whereIn('komponen_anggaran_id', $komponen->pluck('id'))
            ->orderBy('id')
            ->get(['komponen_anggaran_id', 'nama_indikator', 'satuan', 'target_indikator', 'target_tahunan'])
            ->groupBy('komponen_anggaran_id');

        $details = [];
        foreach ($komponen as $k) {
            $isRenstra = $k->document_type === 'renstra';
            $kode = trim((string) ($k->kode_program ?: $k->kode));
            $paguTahunan = $this->decodeYearly($k->pagu_tahunan);

            if ($isRenstra) {
                // Renstra 5 tahunan: pakai pagu tahun terpilih; di luar periode Renstra = 0.
                $pagu = $paguTahunan !== null && $tahun !== null ? (int) ($paguTahunan[$tahun] ?? 0) : (int) $k->pagu;
            } else {
                $pagu = $renjaPagu[$k->id] ?? 0;
            }

            $indikator = [];
            foreach ($indikatorByKomponen[$k->id] ?? [] as $row) {
                $nama = trim((string) $row->nama_indikator);
                $key = $this->nameKey($nama);
                if ($key === '' || isset($indikator[$key])) {
                    continue;
                }
                $targetTahunan = $this->decodeYearly($row->target_tahunan);
                $target = $isRenstra && $targetTahunan !== null && $tahun !== null
                    ? ($targetTahunan[$tahun] ?? null)
                    : ($row->target_indikator ?? ($tahun !== null ? ($targetTahunan[$tahun] ?? null) : null));

                $indikator[$key] = [
                    'nama' => $nama,
                    'target' => $this->cleanValue($target),
                    'satuan' => $this->cleanValue($row->satuan),
                ];
            }

            $details[($isRenstra ? 'renstra' : 'renja') . '|' . $k->opd_id . '|' . $kode] = ['pagu' => $pagu, 'indikator' => $indikator];
        }

        return $details;
    }

    /**
     * Program unik per kode + nama. Program yang sama dari beberapa OPD dalam satu bidang digabung:
     * pagu dijumlahkan dan indikatornya disatukan.
     */
    private function addProgram(?array &$list, array $item): void
    {
        $list ??= [];
        $key = $this->normalize($item['kode']) . '|' . $this->normalize($item['nama']);
        if ($key === '|') {
            return;
        }

        if (! isset($list[$key])) {
            $list[$key] = $item;
            return;
        }

        $list[$key]['pagu'] += $item['pagu'];
        $list[$key]['indikator'] += $item['indikator'];
    }

    // ---------------------------------------------------------------------
    // Perbandingan
    // ---------------------------------------------------------------------

    /**
     * Susun baris tabel jumlah (tabel 1 & 2) dari daftar item per dokumen.
     *
     * @param  callable(array<string, array>): array<int, array{key: string, alt: string, label: string}>  $itemsOf
     */
    private function countRows(array $entities, array $groups, callable $itemsOf): array
    {
        $rows = [];
        foreach ($entities as $index => [$groupKey, $label]) {
            $items = [];
            foreach (self::DOCUMENTS as $doc) {
                $items[$doc] = $itemsOf($groups[$groupKey][$doc] ?? []);
            }

            $cells = array_map(fn (array $list) => array_column($list, 'label'), $items);
            foreach (self::COMPARISONS as $cmp => [$left, $right]) {
                [$sama, $beda] = $this->compare($items[$left], $items[$right]);
                $cells[$cmp . '_sama'] = array_column($sama, 'label');
                $cells[$cmp . '_beda'] = array_column($beda, 'label');
            }

            $rows[] = ['no' => $index + 1, 'entitas' => $label, 'cells' => $cells];
        }

        return $rows;
    }

    /**
     * Sejajarkan program dari ketiga dokumen per kode program (fallback: nama) untuk tabel 3 & 4.
     *
     * @return array<int, array{label: string, rpjmd?: array, renstra?: array, rkpd?: array}>
     */
    private function alignPrograms(array $docs): array
    {
        $aligned = [];
        foreach (self::DOCUMENTS as $doc) {
            foreach ($docs[$doc] ?? [] as $program) {
                $key = $program['kode'] !== '' ? 'k:' . $this->normalize($program['kode']) : 'n:' . $this->nameKey($program['nama']);
                $aligned[$key]['label'] ??= $this->programLabel($program);
                if (isset($aligned[$key][$doc])) {
                    $aligned[$key][$doc]['pagu'] += $program['pagu'];
                    $aligned[$key][$doc]['indikator'] += $program['indikator'];
                } else {
                    $aligned[$key][$doc] = $program;
                }
            }
        }
        ksort($aligned, SORT_NATURAL);

        return array_values($aligned);
    }

    /**
     * Status indikator: sama bila keduanya tidak ada, atau keduanya ada dengan target yang sama.
     */
    private function statusIndikator(?array $left, ?array $right): string
    {
        if ($left === null || $right === null) {
            return $left === $right ? 'Sama' : 'Tidak Sama';
        }

        return $this->normalize((string) $left['target']) === $this->normalize((string) $right['target']) ? 'Sama' : 'Tidak Sama';
    }

    // ---------------------------------------------------------------------
    // Helper teks
    // ---------------------------------------------------------------------

    private function programLabel(array $program): string
    {
        return trim($program['kode'] . ' - ' . $program['nama'], ' -');
    }
}
