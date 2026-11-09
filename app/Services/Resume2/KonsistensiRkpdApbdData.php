<?php

namespace App\Services\Resume2;

use App\Services\Resume2\Concerns\ComparesResumeItems;
use App\Services\Resume2\Concerns\ReadsKomponenAnggaran;
use Illuminate\Support\Facades\DB;

/**
 * Data asli Konsistensi Keg RKPD - APBD untuk Resume 2.
 *
 * Sumber data: `komponen_anggaran` + `indikator_anggaran`, yaitu data yang diisi lewat menu Dokumen Renja dan DPA.
 * - RKPD/Renja = komponen dokumen Renja pada tahun terpilih.
 * - APBD       = komponen dokumen DPA pada tahun terpilih.
 *
 * Item RKPD dan APBD dipasangkan satu-lawan-satu: kode + nama sama, lalu kode sama (kode mengikuti nomenklatur
 * baku, sehingga nama yang salah tidak memutus pasangan), lalu nama sama di dalam program yang sama
 * (untuk kode yang salah/placeholder).
 */
class KonsistensiRkpdApbdData
{
    use ComparesResumeItems;
    use ReadsKomponenAnggaran;

    /**
     * Tabel 1-3: jumlah program / kegiatan / sub kegiatan RKPD dan APBD per entitas,
     * beserta daftar namanya untuk modal rincian.
     *
     * @param  'program'|'kegiatan'|'sub_kegiatan'  $level
     */
    public function jumlah(string $level, string $basis, ?int $tahun): array
    {
        [$entities, $lists] = $this->loadItems($level, $basis, $tahun);

        $rows = [];
        foreach ($entities as $index => [$groupKey, $label]) {
            $rkpd = array_values($lists[$groupKey]['rkpd'] ?? []);
            $apbd = array_values($lists[$groupKey]['apbd'] ?? []);
            [$sama, $beda] = $this->compare($rkpd, $apbd);

            $rows[] = [
                'no' => $index + 1,
                'entitas' => $label,
                'cells' => [
                    'rkpd' => array_column($rkpd, 'label'),
                    'apbd' => array_column($apbd, 'label'),
                    'rkpd_apbd_sama' => array_column($sama, 'label'),
                    'rkpd_apbd_beda' => array_column($beda, 'label'),
                ],
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * Tabel 4-6 (dan 7-9 dengan $withTarget): indikator program / kegiatan / sub kegiatan RKPD dan APBD.
     * Item RKPD dan APBD dipasangkan dengan aturan yang sama seperti tabel 1-3, lalu indikator di dalam
     * setiap pasangan disejajarkan per nama indikator.
     *
     * Status per baris: "Sama" bila indikator ada di kedua dokumen (dan, untuk $withTarget, targetnya sama)
     * atau sama-sama tidak ada; selain itu "Tidak Sama".
     *
     * @param  'program'|'kegiatan'|'sub_kegiatan'  $level
     */
    public function indikator(string $level, string $basis, ?int $tahun, bool $withTarget = false): array
    {
        [$entities, $lists] = $this->loadItems($level, $basis, $tahun);
        $indikatorByKomponen = $this->indikatorByKomponen($lists);

        $groups = [];
        foreach ($entities as [$groupKey, $label]) {
            $items = [];
            foreach ($this->pair($lists[$groupKey]['rkpd'] ?? [], $lists[$groupKey]['apbd'] ?? []) as [$rkpd, $apbd]) {
                $rkpdIndikator = $this->indikatorOf($rkpd, $indikatorByKomponen);
                $apbdIndikator = $this->indikatorOf($apbd, $indikatorByKomponen);

                $lines = [];
                foreach (array_keys($rkpdIndikator + $apbdIndikator) ?: [null] as $key) {
                    $line = [
                        'rkpd' => $key === null ? null : ($rkpdIndikator[$key] ?? null),
                        'apbd' => $key === null ? null : ($apbdIndikator[$key] ?? null),
                    ];
                    $line['status'] = $this->status($line['rkpd'], $line['apbd'], $withTarget);
                    $lines[] = $line;
                }

                // Nama mengikuti APBD (DPA) bila ada, karena DPA memakai nomenklatur yang sudah ditetapkan.
                $items[] = ['nama' => ($apbd ?? $rkpd)['label'], 'lines' => $lines];
            }

            if ($items) {
                $groups[] = ['no' => count($groups) + 1, 'entitas' => $label, 'items' => $items];
            }
        }

        return ['groups' => $groups];
    }

    /**
     * Tabel 10: pagu program → kegiatan → sub kegiatan pada RKPD dan APBD, beserta konsistensinya terhadap
     * RPJMD (tingkat program) dan Renstra (tingkat program & kegiatan).
     *
     * Pagu RKPD/APBD dihitung dari sub kegiatan lalu dijumlahkan ke atas, sehingga pagu program selalu sama
     * dengan jumlah kegiatannya; pagu tersimpan hanya dipakai bila item tidak punya sub kegiatan.
     * Pagu Renstra memakai nilai tahun terpilih; RPJMD = Renstra untuk program yang ditandai prioritas.
     */
    public function pagu(string $basis, ?int $tahun): array
    {
        $levels = ['program', 'kegiatan', 'sub_kegiatan'];
        $lists = [];
        foreach ($levels as $level) {
            [$entities, $lists[$level]] = $this->loadItems($level, $basis, $tahun);
        }

        [$paguById, $opdKodeById] = $this->effectivePagu($tahun);
        [$renstraPagu, $rpjmdKeys] = $this->renstraPagu($tahun);

        $groups = [];
        foreach ($entities as [$groupKey, $label]) {
            // Pasangkan item RKPD-APBD per level, lalu susun pohon program → kegiatan → sub kegiatan lewat kode.
            $nodes = [];
            foreach ($levels as $level) {
                foreach ($this->pair($lists[$level][$groupKey]['rkpd'] ?? [], $lists[$level][$groupKey]['apbd'] ?? []) as [$rkpd, $apbd]) {
                    $main = $apbd ?? $rkpd;
                    $rkpdPagu = $this->sumPagu($rkpd, $paguById);
                    $apbdPagu = $this->sumPagu($apbd, $paguById);

                    $renstra = null;
                    $rpjmd = null;
                    if ($level !== 'sub_kegiatan') {
                        $renstra = 0;
                        foreach ($this->opdKodes($rkpd, $apbd, $opdKodeById) as $opdKode) {
                            $renstra += $renstraPagu[$level][$opdKode] ?? 0;
                            if ($level === 'program' && isset($rpjmdKeys[$opdKode])) {
                                $rpjmd = ($rpjmd ?? 0) + ($renstraPagu[$level][$opdKode] ?? 0);
                            }
                        }
                        if ($level === 'program') {
                            $rpjmd ??= 0;
                        }
                    }

                    $nodes[$level][] = [
                        'uid' => $level . '#' . count($nodes[$level] ?? []),
                        'level' => $level,
                        'kode' => $main['kode_raw'],
                        'nama' => $main['nama'],
                        'rkpd' => $rkpdPagu,
                        'apbd' => $apbdPagu,
                        'selisih' => [
                            'rkpd_apbd' => $rkpdPagu - $apbdPagu,
                            'rpjmd_rkpd' => $rpjmd === null ? null : $rpjmd - $rkpdPagu,
                            'renstra_rkpd' => $renstra === null ? null : $renstra - $rkpdPagu,
                        ],
                    ];
                }
            }

            $lines = $this->flattenTree($nodes);
            if ($lines) {
                $groups[] = ['no' => count($groups) + 1, 'entitas' => $label, 'lines' => $lines];
            }
        }

        return ['groups' => $groups];
    }

    /**
     * Urutkan baris: setiap program diikuti kegiatannya, setiap kegiatan diikuti sub kegiatannya.
     * Item yang induknya tidak ditemukan tetap ditampilkan di akhir.
     */
    private function flattenTree(array $nodes): array
    {
        $parentOf = fn (string $kode, string $level) => $level === 'kegiatan'
            ? implode('.', array_slice(explode('.', $kode), 0, -2))   // 1.01.01.2.01 → 1.01.01
            : implode('.', array_slice(explode('.', $kode), 0, -1));  // 1.01.01.2.01.0001 → 1.01.01.2.01

        $children = [];
        foreach (['kegiatan', 'sub_kegiatan'] as $level) {
            foreach ($nodes[$level] ?? [] as $node) {
                $children[$level][$this->normalize($parentOf($node['kode'], $level))][] = $node;
            }
        }

        $byKode = fn (array $a, array $b) => strnatcmp($a['kode'], $b['kode']);
        $lines = [];
        $placed = [];
        // Setiap baris ditempatkan sekali saja: bila dua induk berkode sama (kode dobel), anaknya
        // hanya muncul di bawah induk pertama.
        $place = function (array $node) use (&$lines, &$placed): bool {
            if (isset($placed[$node['uid']])) {
                return false;
            }
            $placed[$node['uid']] = true;
            $lines[] = $node;

            return true;
        };

        $programs = $nodes['program'] ?? [];
        usort($programs, $byKode);
        foreach ($programs as $program) {
            $place($program);
            $kegiatanList = $children['kegiatan'][$this->normalize($program['kode'])] ?? [];
            usort($kegiatanList, $byKode);
            foreach ($kegiatanList as $kegiatan) {
                if (! $place($kegiatan)) {
                    continue;
                }
                $subList = $children['sub_kegiatan'][$this->normalize($kegiatan['kode'])] ?? [];
                usort($subList, $byKode);
                foreach ($subList as $sub) {
                    $place($sub);
                }
            }
        }

        // Item yang induknya tidak ada di daftar (mis. kode tidak lengkap) tetap ditampilkan.
        foreach (['kegiatan', 'sub_kegiatan'] as $level) {
            foreach ($nodes[$level] ?? [] as $node) {
                $place($node);
            }
        }

        return array_map(function (array $line) {
            unset($line['uid']);

            return $line;
        }, $lines);
    }

    /**
     * Pagu Renstra tahun terpilih per "opd_id|kode" untuk program dan kegiatan, serta program RPJMD
     * (program Renstra yang ditandai prioritas).
     *
     * @return array{0: array<string, array<string, int>>, 1: array<string, true>}
     */
    private function renstraPagu(?int $tahun): array
    {
        $pagu = [];
        $rows = DB::table('komponen_anggaran')
            ->where('document_type', 'renstra')
            ->whereIn('jenis', ['program', 'kegiatan'])
            ->whereNotNull('opd_id')
            ->get(['opd_id', 'jenis', 'kode', 'kode_program', 'pagu', 'pagu_tahunan']);
        foreach ($rows as $r) {
            $kode = trim((string) ($r->jenis === 'program' ? ($r->kode_program ?: $r->kode) : $r->kode));
            $tahunan = $this->decodeYearly($r->pagu_tahunan);
            // Renstra 5 tahunan: pakai pagu tahun terpilih; di luar periode Renstra = 0.
            $value = $tahunan !== null && $tahun !== null ? (int) ($tahunan[$tahun] ?? 0) : (int) $r->pagu;
            $key = $r->opd_id . '|' . $this->normalize($kode);
            $pagu[$r->jenis][$key] = ($pagu[$r->jenis][$key] ?? 0) + $value;
        }

        $rpjmd = DB::table('program')
            ->where('document_type', 'renstra')
            ->where('is_prioritas', true)
            ->whereNotNull('opd_id')
            ->get(['opd_id', 'kode_rek'])
            ->mapWithKeys(fn ($p) => [$p->opd_id . '|' . $this->normalize((string) $p->kode_rek) => true])
            ->all();

        return [$pagu, $rpjmd];
    }

    private function sumPagu(?array $item, array $paguById): int
    {
        return array_sum(array_map(fn (int $id) => $paguById[$id] ?? 0, $item['ids'] ?? []));
    }

    /**
     * "opd_id|kode" unik dari komponen sebuah pasangan item, untuk mencari pagu Renstra/RPJMD-nya.
     *
     * @return array<int, string>
     */
    private function opdKodes(?array $rkpd, ?array $apbd, array $opdKodeById): array
    {
        $keys = [];
        foreach ([$rkpd, $apbd] as $item) {
            foreach ($item['ids'] ?? [] as $id) {
                $keys[$opdKodeById[$id] ?? ''] = true;
            }
        }
        unset($keys['']);

        return array_keys($keys);
    }

    /**
     * Item komponen per entitas per dokumen, unik per kode + nama.
     *
     * @return array{0: array<int, array{0: int|string, 1: string}>, 1: array<int|string, array<string, array<string, array{key: string, alt: string, label: string, ids: array<int, int>}>>>}
     */
    private function loadItems(string $level, string $basis, ?int $tahun): array
    {
        [$entities, $groupKeyOf] = $this->entities($basis);

        $komponen = DB::table('komponen_anggaran')
            ->where('jenis', $level)
            ->whereIn('document_type', ['renja', 'dpa'])
            ->whereNotNull('opd_id')
            ->when($tahun !== null, fn ($q) => $q->where('tahun', $tahun))
            ->orderBy('kode')
            ->get(['id', 'opd_id', 'document_type', 'kode', 'kode_program', 'nama_komponen']);

        $lists = [];
        foreach ($komponen as $k) {
            $groupKey = $groupKeyOf((int) $k->opd_id);
            if ($groupKey === null) {
                continue;
            }

            $kodeProgram = trim((string) ($k->kode_program ?: $k->kode));
            $kode = $level === 'program' ? $kodeProgram : trim((string) $k->kode);
            $nama = trim((string) $k->nama_komponen);
            $key = $this->normalize($kode) . '|' . $this->normalize($nama);
            if ($key === '|') {
                continue;
            }

            $doc = $k->document_type === 'dpa' ? 'apbd' : 'rkpd';
            $lists[$groupKey][$doc][$key] ??= [
                'key' => $key,
                // Kode mengikuti nomenklatur baku (Permendagri 90): kode sama = item sama walau namanya beda,
                // mis. nama kegiatan Renja yang bergeser satu baris dari kodenya.
                'kode' => $this->normalize($kode),
                // Cadangan terakhir: nama yang sama (kegiatan/sub kegiatan: di dalam program yang sama).
                'alt' => $level === 'program' ? $this->nameKey($nama) : $this->normalize($kodeProgram) . '|' . $this->nameKey($nama),
                'label' => trim($kode . ' - ' . $nama, ' -'),
                'kode_raw' => $kode,
                'nama' => $nama,
                'ids' => [],
            ];
            // Item yang sama dari beberapa OPD dalam satu bidang digabung; indikatornya ikut digabung.
            $lists[$groupKey][$doc][$key]['ids'][] = (int) $k->id;
        }

        return [$entities, $lists];
    }

    /**
     * @return array<int, array<int, object>> indikator_anggaran dikelompokkan per komponen_anggaran_id
     */
    private function indikatorByKomponen(array $lists): array
    {
        $ids = [];
        foreach ($lists as $docs) {
            foreach ($docs as $items) {
                foreach ($items as $item) {
                    array_push($ids, ...$item['ids']);
                }
            }
        }

        return DB::table('indikator_anggaran')
            ->whereIn('komponen_anggaran_id', $ids)
            ->orderBy('id')
            ->get(['komponen_anggaran_id', 'nama_indikator', 'satuan', 'target_indikator'])
            ->groupBy('komponen_anggaran_id')
            ->all();
    }

    /**
     * Indikator unik (per nama yang dinormalisasi) milik satu item.
     *
     * @return array<string, array{indikator: string, target: ?string, satuan: ?string}>
     */
    private function indikatorOf(?array $item, array $indikatorByKomponen): array
    {
        $result = [];
        foreach ($item['ids'] ?? [] as $id) {
            foreach ($indikatorByKomponen[$id] ?? [] as $row) {
                $nama = trim((string) $row->nama_indikator);
                $key = $this->nameKey($nama);
                if ($key === '' || isset($result[$key])) {
                    continue;
                }
                $result[$key] = [
                    'indikator' => $nama,
                    'target' => $this->cleanValue($row->target_indikator),
                    'satuan' => $this->cleanValue($row->satuan),
                ];
            }
        }

        return $result;
    }

    private function status(?array $rkpd, ?array $apbd, bool $withTarget): string
    {
        if ($rkpd === null || $apbd === null) {
            return $rkpd === $apbd ? 'Sama' : 'Tidak Sama';
        }

        return ! $withTarget || $this->normalize((string) $rkpd['target']) === $this->normalize((string) $apbd['target'])
            ? 'Sama'
            : 'Tidak Sama';
    }
}
