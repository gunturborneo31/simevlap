<?php

namespace App\Services\Resume2\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Pembacaan `komponen_anggaran` bersama untuk Resume 2: pagu efektif dan perapian nilai.
 * Membutuhkan method normalize() dari ComparesResumeItems.
 */
trait ReadsKomponenAnggaran
{
    /**
     * Pagu efektif per komponen tahun terpilih: sub kegiatan = pagu tersimpan; kegiatan dan program =
     * jumlah pagu sub kegiatannya, atau pagu tersimpan (pagu / pagu_tahunan) bila tidak punya sub kegiatan.
     * Induk ditentukan dari awalan kode sub kegiatan (1.01.01.2.01.0001 → kegiatan 1.01.01.2.01, program 1.01.01),
     * bukan dari kolom kode_program yang di sebagian data Renja tidak sesuai.
     *
     * @param  array<int, string>  $documents
     * @return array{0: array<int, int>, 1: array<int, string>} [pagu per id, "opd_id|kode" per id]
     */
    private function effectivePagu(?int $tahun, array $documents = ['renja', 'dpa']): array
    {
        $rows = DB::table('komponen_anggaran')
            ->whereIn('document_type', $documents)
            ->whereNotNull('opd_id')
            ->when($tahun !== null, fn ($q) => $q->where('tahun', $tahun))
            ->get(['id', 'opd_id', 'document_type', 'jenis', 'kode', 'kode_program', 'pagu', 'pagu_tahunan']);

        $subSum = [];
        foreach ($rows as $r) {
            if ($r->jenis !== 'sub_kegiatan') {
                continue;
            }
            $segments = explode('.', trim((string) $r->kode));
            $prefix = $r->document_type . '|' . $r->opd_id . '|';
            $kegiatanKode = implode('.', array_slice($segments, 0, -1));
            $programKode = implode('.', array_slice($segments, 0, 3));
            $subSum['kegiatan'][$prefix . $kegiatanKode] = ($subSum['kegiatan'][$prefix . $kegiatanKode] ?? 0) + (int) $r->pagu;
            $subSum['program'][$prefix . $programKode] = ($subSum['program'][$prefix . $programKode] ?? 0) + (int) $r->pagu;
        }

        $pagu = [];
        $opdKode = [];
        foreach ($rows as $r) {
            $kode = trim((string) ($r->jenis === 'program' ? ($r->kode_program ?: $r->kode) : $r->kode));
            $opdKode[(int) $r->id] = $r->opd_id . '|' . $this->normalize($kode);

            $tahunan = $this->decodeYearly($r->pagu_tahunan);
            $stored = (int) $r->pagu > 0 ? (int) $r->pagu : (int) ($tahunan[$tahun] ?? 0);
            $pagu[(int) $r->id] = $r->jenis === 'sub_kegiatan'
                ? (int) $r->pagu
                : ($subSum[$r->jenis][$r->document_type . '|' . $r->opd_id . '|' . $kode] ?? $stored);
        }

        return [$pagu, $opdKode];
    }

    /**
     * @return array<int|string, mixed>|null nilai per tahun, mis. {"2025": 75, "2026": 78}
     */
    private function decodeYearly(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Rapikan nilai teks: buang teks "undefined" (sisa bug simpan di form, mis. "CCundefined"), satukan spasi/baris
     * baru, dan anggap kosong, "-", atau teks "NULL" (sisa import SQL) sebagai tidak diisi.
     */
    private function cleanValue(mixed $value): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', str_ireplace('undefined', '', (string) $value)) ?? '');

        return $text === '' || $text === '-' || strcasecmp($text, 'null') === 0 ? null : $text;
    }
}
