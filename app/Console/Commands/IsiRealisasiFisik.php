<?php

namespace App\Console\Commands;

use App\Http\Controllers\KomponenAnggaranController;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Isi awal realisasi fisik (kinerja) DPA sesuai aturan, agar OPD tinggal menyesuaikan dengan realita.
 * Hanya baris realisasi yang fisiknya masih 0 yang diisi; isian OPD tidak ditimpa.
 *
 * Aturan (target = indikator pertama komponen):
 * - Satuan bulan    : TW1 = 3, TW2 = 6, TW3 = 9, TW4 = 12 (maksimal target).
 * - Satuan semester : TW1 = 0, TW2 = 1, TW3 = 1, TW4 = 2 (maksimal target).
 * - Satuan lain     : floor(target x realisasi keuangan TW / pagu DPA), tanpa angka desimal.
 * Sama dengan nilai awal input di halaman realisasi DPA (computeDefaultFisik).
 */
class IsiRealisasiFisik extends Command
{
    protected $signature = 'realisasi:isi-fisik
        {--tahun=2026 : Tahun anggaran}
        {--tw= : Triwulan tertentu (kosong = semua triwulan)}
        {--opd= : ID OPD tertentu (kosong = semua OPD)}
        {--pembulatan=bawah : bawah (floor, tanpa desimal), biasa (round), atau desimal (2 angka)}
        {--dry-run : Tampilkan hasil tanpa menyimpan}';

    protected $description = 'Isi awal realisasi fisik DPA dari target dan capaian keuangan (hanya yang masih 0)';

    private array $updates = [];
    private array $stats = ['diisi' => 0, 'hasil_0' => 0, 'sudah_diisi_opd' => 0, 'tanpa_target' => 0];

    public function handle(KomponenAnggaranController $komponen): int
    {
        // Payload dibangun sebagai superadmin agar tidak terbatas scope OPD user.
        Auth::setUser(User::query()->role('superadmin')->firstOrFail());

        $tahun = (int) $this->option('tahun');
        $onlyTw = $this->option('tw') ? (int) $this->option('tw') : null;
        $opdIds = $this->option('opd')
            ? [(int) $this->option('opd')]
            : Opd::query()->where('is_active', true)->pluck('id')->all();

        foreach ($opdIds as $opdId) {
            $payload = $komponen->buildRealisasiPayloadForOpd($opdId, $tahun, 'realisasi', 'dpa', true, false);
            $nodes = json_decode(json_encode($payload['data'] ?? []), true);
            $nodes = $nodes['data'] ?? $nodes;
            $this->walk(is_array($nodes) ? $nodes : [], $onlyTw);
        }

        $this->table(['Hasil', 'Jumlah'], collect($this->stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        if ($this->option('dry-run')) {
            $this->info('Dry run: tidak ada data yang disimpan.');
            return self::SUCCESS;
        }

        DB::transaction(function () {
            $now = now();
            foreach ($this->updates as $id => $fisik) {
                DB::table('realisasi')->where('id', $id)->where('realisasi_fisik', 0)->update(['realisasi_fisik' => $fisik, 'updated_at' => $now]);
            }
        });
        $this->info(count($this->updates) . ' baris realisasi fisik disimpan.');

        return self::SUCCESS;
    }

    private function walk(array $nodes, ?int $onlyTw): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $indikator = $node['indikator'][0] ?? [];
            $target = $this->toNumber($indikator['target_dpa'] ?? ($indikator['target_indikator'] ?? null));
            $satuan = strtolower((string) ($indikator['satuan'] ?? ''));

            foreach ((array) ($node['realisasi_tw'] ?? []) as $tw => $value) {
                $tw = (int) $tw;
                if (!is_array($value) || empty($value['id']) || ($onlyTw && $tw !== $onlyTw)) {
                    continue;
                }
                if ((float) ($value['fisik'] ?? 0) > 0) {
                    $this->stats['sudah_diisi_opd']++;
                    continue;
                }
                if ($target <= 0) {
                    $this->stats['tanpa_target']++;
                    continue;
                }

                $fisik = $this->defaultFisik($node, $tw, $target, $satuan);
                if ($fisik > 0) {
                    $this->updates[(int) $value['id']] = $fisik;
                    $this->stats['diisi']++;
                } else {
                    $this->stats['hasil_0']++;
                }
            }

            $this->walk((array) ($node['children'] ?? []), $onlyTw);
        }
    }

    private function defaultFisik(array $node, int $tw, float $target, string $satuan): float
    {
        if (str_contains($satuan, 'semester')) {
            return min(floor($tw / 2), $target);
        }
        if (str_contains($satuan, 'bulan')) {
            return min($tw * 3, $target);
        }

        $pagu = $this->pagu($node);
        if ($pagu <= 0) {
            return 0;
        }
        $persen = min(max($this->keuangan($node, $tw) / $pagu, 0), 1);

        $nilai = $target * $persen;

        return match ($this->option('pembulatan')) {
            'biasa' => round($nilai),
            'desimal' => round($nilai, 2),
            default => floor($nilai),
        };
    }

    /** Pagu DPA: sub kegiatan = pagu sendiri; kegiatan/program = jumlah turunannya (bila ada). */
    private function pagu(array $node): float
    {
        $own = (float) ($node['pagu_dpa'] ?? ($node['pagu'] ?? 0));
        $jenis = $node['jenis'] ?? '';
        if ($jenis === 'sub_kegiatan') {
            return $own;
        }
        $childJenis = $jenis === 'kegiatan' ? 'sub_kegiatan' : ($jenis === 'program' ? 'kegiatan' : null);
        if (!$childJenis) {
            return $own;
        }
        $sum = 0.0;
        foreach ((array) ($node['children'] ?? []) as $child) {
            if (is_array($child) && ($child['jenis'] ?? '') === $childJenis) {
                $sum += $this->pagu($child);
            }
        }
        return $sum > 0 ? $sum : $own;
    }

    /** Realisasi keuangan TW: jumlah turunan bila ada nilainya, selain itu nilai sendiri. */
    private function keuangan(array $node, int $tw): float
    {
        $own = (float) ($node['realisasi_tw'][$tw]['keuangan'] ?? 0);
        $children = (array) ($node['children'] ?? []);
        if (!$children) {
            return $own;
        }
        $sum = 0.0;
        foreach ($children as $child) {
            if (is_array($child)) {
                $sum += $this->keuangan($child, $tw);
            }
        }
        return $sum > 0 ? $sum : $own;
    }

    private function toNumber(mixed $value): float
    {
        $clean = str_replace(',', '.', trim((string) $value));
        return is_numeric($clean) ? (float) $clean : 0;
    }
}
