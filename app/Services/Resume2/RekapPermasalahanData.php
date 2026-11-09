<?php

namespace App\Services\Resume2;

use App\Models\Opd;
use App\Services\Resume2\Concerns\ComparesResumeItems;
use App\Services\Resume2\Concerns\ReadsKomponenAnggaran;
use Illuminate\Support\Facades\DB;

/**
 * Data asli Rekap Permasalahan untuk Resume 2.
 *
 * Sumber data:
 * - Catatan faktor pendorong, penghambat, dan tindak lanjut per program: `resume_program_annotations`
 *   (view "rekap-permasalahan"); entitasnya berupa kode OPD.
 * - Daftar program: program dokumen terpilih (Renstra / Renja / DPA) di `komponen_anggaran`.
 *
 * Program yang belum punya catatan ditampilkan "-"; catatan yang programnya tidak ada di dokumen terpilih tetap
 * ditampilkan agar tidak ada catatan yang hilang. Catatan tidak menyimpan triwulan.
 */
class RekapPermasalahanData
{
    use ComparesResumeItems;
    use ReadsKomponenAnggaran;

    private const FIELDS = [
        'pendorong' => 'faktor_pendorong',
        'penghambat' => 'faktor_penghambat',
        'tindak_lanjut' => 'faktor_tindak_lanjut',
    ];

    /**
     * Tabel 1 (per perangkat daerah) dan tabel 2 (per bidang urusan).
     *
     * @param  'perangkat-daerah'|'bidang-urusan'  $basis
     * @param  'renstra'|'renja'|'dpa'  $dokumen
     */
    public function rekap(string $basis, string $dokumen, ?int $tahun): array
    {
        [$entities, $groupKeyOf] = $this->entities($basis);
        $opdNames = Opd::query()->pluck('nama', 'id');
        $opdByKode = Opd::query()->pluck('id', 'kode')->mapWithKeys(fn ($id, $kode) => [trim((string) $kode) => (int) $id]);

        // Program per OPD dari dokumen terpilih (Renstra 5 tahunan tidak difilter tahun).
        $programs = [];
        $komponen = DB::table('komponen_anggaran')
            ->where('document_type', $dokumen)
            ->where('jenis', 'program')
            ->whereNotNull('opd_id')
            ->when($dokumen !== 'renstra' && $tahun !== null, fn ($q) => $q->where('tahun', $tahun))
            ->get(['opd_id', 'kode', 'kode_program', 'nama_komponen']);
        foreach ($komponen as $k) {
            $kode = trim((string) ($k->kode_program ?: $k->kode));
            $programs[(int) $k->opd_id][$this->normalize($kode)] ??= ['kode' => $kode, 'nama' => trim((string) $k->nama_komponen)];
        }

        // Catatan per OPD + program; beberapa catatan untuk program yang sama digabung tanpa duplikat.
        $notes = [];
        $annotations = DB::table('resume_program_annotations')
            ->where('view', 'rekap-permasalahan')
            ->when($tahun !== null, fn ($q) => $q->where('tahun', $tahun))
            ->orderBy('id')
            ->get(['entitas', 'program_kode', 'program_nama', ...array_values(self::FIELDS)]);
        foreach ($annotations as $a) {
            $opdId = $opdByKode[trim((string) $a->entitas)] ?? (ctype_digit((string) $a->entitas) ? (int) $a->entitas : null);
            if ($opdId === null) {
                continue;
            }
            $kodeKey = $this->normalize((string) $a->program_kode);
            $programs[$opdId][$kodeKey] ??= ['kode' => trim((string) $a->program_kode), 'nama' => trim((string) $a->program_nama)];
            foreach (self::FIELDS as $field => $column) {
                $text = $this->cleanValue($a->{$column});
                if ($text !== null) {
                    $notes[$opdId][$kodeKey][$field][$this->nameKey($text)] = $text;
                }
            }
        }

        $byGroup = [];
        foreach ($programs as $opdId => $list) {
            $groupKey = $groupKeyOf((int) $opdId);
            if ($groupKey === null) {
                continue;
            }
            foreach ($list as $kodeKey => $program) {
                $row = [
                    'kode' => $program['kode'],
                    'nama' => trim($program['kode'] . ' - ' . $program['nama'], ' -'),
                    'opd' => $opdNames[$opdId] ?? '-',
                ];
                foreach (array_keys(self::FIELDS) as $field) {
                    $row[$field] = array_values($notes[$opdId][$kodeKey][$field] ?? []);
                }
                $byGroup[$groupKey][] = $row;
            }
        }

        $groups = [];
        foreach ($entities as [$groupKey, $label]) {
            if (empty($byGroup[$groupKey])) {
                continue;
            }
            $rows = $byGroup[$groupKey];
            usort($rows, fn ($a, $b) => strcmp($a['opd'], $b['opd']) ?: strnatcmp($a['kode'], $b['kode']));
            $groups[] = ['no' => count($groups) + 1, 'nama' => $label, 'programs' => $rows];
        }

        return ['groups' => $groups];
    }
}
