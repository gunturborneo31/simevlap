<?php

namespace App\Services\Resume2\Concerns;

use App\Models\BidangUrusan;
use App\Models\Opd;

/**
 * Helper bersama untuk tabel konsistensi Resume 2: daftar entitas (bidang urusan / perangkat daerah)
 * dan perbandingan dua daftar item ala Resume SIMEVLAP v1.
 */
trait ComparesResumeItems
{
    /**
     * Daftar entitas beserta fungsi pemetaan opd_id → kunci entitas.
     * Bidang urusan diambil unik per kode; OPD masuk ke bidang lewat dua segmen awal kodenya (mis. "1.01").
     *
     * @return array{0: array<int, array{0: int|string, 1: string}>, 1: callable(?int): int|string|null}
     */
    private function entities(string $basis): array
    {
        if ($basis === 'perangkat-daerah') {
            $entities = Opd::query()
                ->orderBy('nama')
                ->get(['id', 'nama'])
                ->map(fn (Opd $opd) => [(int) $opd->id, (string) $opd->nama])
                ->all();

            return [$entities, fn (?int $opdId) => $opdId ?: null];
        }

        $entities = BidangUrusan::query()
            ->orderBy('kode')
            ->get(['kode', 'nama'])
            ->unique('kode')
            ->map(fn (BidangUrusan $b) => [
                (string) $b->kode,
                trim(preg_replace('/^\s*URUSAN\s+PEMERINTAHAN\s+BIDANG\s+/i', '', (string) $b->nama) ?? ''),
            ])
            ->values()
            ->all();

        $bidangByOpd = Opd::query()
            ->get(['id', 'kode'])
            ->mapWithKeys(function (Opd $opd) {
                $segments = array_values(array_filter(explode('.', (string) $opd->kode), fn ($s) => $s !== ''));

                return [$opd->id => count($segments) >= 2 ? $segments[0] . '.' . $segments[1] : null];
            });

        return [$entities, fn (?int $opdId) => $bidangByOpd[$opdId] ?? null];
    }

    /**
     * Pasangkan item kiri dan kanan satu-lawan-satu: pertama yang kunci utamanya (`key`) sama, lalu (bila item
     * membawa `kode`) yang kodenya sama, lalu sisanya lewat kunci cadangan (`alt`). Setiap item kanan hanya
     * dipakai sekali, sehingga jumlah "sama" tidak pernah melebihi jumlah item di salah satu sisi.
     * "Tidak sama" = item yang tidak punya pasangan, dari kedua sisi, seperti Resume v1.
     *
     * @param  array<int, array{key: string, alt: string, label: string}>  $left
     * @param  array<int, array{key: string, alt: string, label: string}>  $right
     * @return array{0: array, 1: array} [item kiri yang punya pasangan, item tanpa pasangan dari kedua sisi]
     */
    private function compare(array $left, array $right): array
    {
        $sama = [];
        $beda = [];
        foreach ($this->pair($left, $right) as [$l, $r]) {
            if ($l !== null && $r !== null) {
                $sama[] = $l;
            } else {
                $beda[] = $l ?? $r;
            }
        }

        return [$sama, $beda];
    }

    /**
     * Pasangan item kiri-kanan dengan aturan yang sama seperti compare(): [kiri, kanan] untuk yang berpasangan,
     * [kiri, null] / [null, kanan] untuk yang tidak. Urutan mengikuti item kiri, lalu sisa item kanan.
     *
     * @return array<int, array{0: ?array, 1: ?array}>
     */
    private function pair(array $left, array $right): array
    {
        $left = array_values($left);
        $right = array_values($right);

        // Tahap pencocokan: kunci utama, lalu kode saja (bila item membawa `kode`), lalu kunci cadangan.
        $fields = ['key', 'kode', 'alt'];
        $rightIndex = array_fill_keys($fields, []);
        foreach ($right as $i => $item) {
            foreach ($fields as $field) {
                if (($item[$field] ?? '') !== '') {
                    $rightIndex[$field][$item[$field]][] = $i;
                }
            }
        }

        $usedRight = [];
        $partnerOf = [];
        foreach ($fields as $field) {
            foreach ($left as $li => $item) {
                if (isset($partnerOf[$li]) || ($item[$field] ?? '') === '') {
                    continue;
                }
                foreach ($rightIndex[$field][$item[$field]] ?? [] as $ri) {
                    if (! isset($usedRight[$ri])) {
                        $usedRight[$ri] = true;
                        $partnerOf[$li] = $ri;
                        break;
                    }
                }
            }
        }

        $pairs = [];
        foreach ($left as $li => $item) {
            $pairs[] = [$item, isset($partnerOf[$li]) ? $right[$partnerOf[$li]] : null];
        }
        foreach ($right as $ri => $item) {
            if (! isset($usedRight[$ri])) {
                $pairs[] = [null, $item];
            }
        }

        return $pairs;
    }

    private function nameKey(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', $this->normalize($value)) ?? '';
    }

    private function normalize(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($value)) ?? '');
    }
}
