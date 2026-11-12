<?php

namespace App\Console\Commands;

use App\Models\Kegiatan;
use App\Models\Program;
use App\Models\SubKegiatan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Impor realisasi keuangan dari file LRA (JSON) ke realisasi Sub Kegiatan DPA,
 * lalu menjumlahkannya ke Kegiatan dan Program (pagu dan realisasi).
 *
 * Realisasi hanya diinput di level Sub Kegiatan; Kegiatan/Program adalah akumulasinya.
 * Baris "Sub Kegiatan" dicocokkan langsung lewat OPD + kode. Baris "Kegiatan" (file LRA lama tanpa rincian sub)
 * hanya dipakai bila kegiatan tersebut punya tepat satu sub kegiatan.
 */
class ImportLraRealisasi extends Command
{
    protected $signature = 'realisasi:import-lra
        {file : Path file JSON LRA, mis. lra/2026/tw-3.json}
        {--tahun=2026 : Tahun anggaran}
        {--tw=3 : Triwulan}
        {--user=1 : ID user yang dicatat sebagai penginput}
        {--update-pagu : Samakan pagu sub kegiatan DPA (sub_kegiatan & komponen_anggaran) dengan anggaran LRA}
        {--no-aggregate : Jangan jumlahkan ke Kegiatan dan Program}
        {--dry-run : Tampilkan hasil tanpa menyimpan}';

    protected $description = 'Impor realisasi LRA ke Sub Kegiatan DPA lalu jumlahkan ke Kegiatan, Program, dan OPD';

    private int $tahun;
    private int $tw;
    private int $userId;
    private Carbon $now;
    private array $backup = [];

    public function handle(): int
    {
        $path = base_path($this->argument('file'));
        if (!is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");
            return self::FAILURE;
        }

        $rows = json_decode(file_get_contents($path), true);
        if (!is_array($rows)) {
            $this->error('Isi file bukan JSON yang valid.');
            return self::FAILURE;
        }

        $this->tahun = (int) $this->option('tahun');
        $this->tw = (int) $this->option('tw');
        $this->userId = (int) $this->option('user');
        $this->now = now();
        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();
        try {
            [$stats, $paguBeda, $opdIds, $lraTotal] = $this->importSubKegiatan($rows, basename($path), (bool) $this->option('update-pagu'));

            $this->table(['Hasil impor sub kegiatan', 'Jumlah'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());
            if ($paguBeda) {
                $this->warn(count($paguBeda) . ' sub kegiatan memiliki pagu berbeda dengan anggaran LRA'
                    . ($this->option('update-pagu') ? ' (pagu diperbarui ke anggaran LRA).' : '.'));
            }

            if (!$this->option('no-aggregate')) {
                $this->aggregate($opdIds, $lraTotal);
            }

            if (!$dryRun && $this->backup) {
                // Simpan nilai pagu lama agar perubahan bisa dikembalikan.
                $backupPath = storage_path('app/backup/pagu_lra_' . $this->tahun . '_tw' . $this->tw . '_' . $this->now->format('Ymd_His') . '.json');
                if (!is_dir(dirname($backupPath))) {
                    mkdir(dirname($backupPath), 0755, true);
                }
                file_put_contents($backupPath, json_encode($this->backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->info('Cadangan pagu lama: ' . $backupPath);
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->info($dryRun ? 'Dry run: tidak ada data yang disimpan.' : 'Selesai disimpan.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<string, int>, 1: array<int, array>, 2: array<int, int>, 3: array<int, array{anggaran: float, realisasi: float}>}
     */
    private function importSubKegiatan(array $rows, string $fileName, bool $updatePagu): array
    {
        $opdIdByKode = DB::table('opds')->pluck('id', 'kode');
        $stats = ['diimpor' => 0, 'diperbarui' => 0, 'baris_ganda' => 0, 'lewati_tanpa_opd' => 0, 'lewati_tanpa_sub' => 0, 'lewati_multi_sub' => 0];
        $paguBeda = [];
        $seen = [];
        $opdIds = [];
        $lraTotal = [];

        $subsByKey = [];
        foreach (DB::table('sub_kegiatan')->where('document_type', 'dpa')->where('tahun', $this->tahun)->get(['id', 'opd_id', 'kode_rek', 'pagu']) as $sub) {
            $subsByKey[$sub->opd_id . '|' . trim((string) $sub->kode_rek)][] = $sub;
        }

        foreach ($rows as $row) {
            $kategori = $row['Kategori'] ?? null;
            if (!in_array($kategori, ['Sub Kegiatan', 'Kegiatan'], true)) {
                continue; // termasuk baris judul/nomor kolom
            }

            $kode = trim((string) $row['kode_uraian']);
            $opdId = $opdIdByKode[$row['kode_opd']] ?? null;
            if (!$opdId) {
                $stats['lewati_tanpa_opd']++;
                continue;
            }

            if (isset($seen[$opdId . '|' . $kode])) {
                $stats['baris_ganda']++;
                continue;
            }
            $seen[$opdId . '|' . $kode] = true;

            if ($kategori === 'Sub Kegiatan') {
                $subs = collect($subsByKey[$opdId . '|' . $kode] ?? []);
            } else {
                $subs = collect($subsByKey)
                    ->filter(fn ($v, $key) => str_starts_with($key, $opdId . '|' . $kode . '.'))
                    ->flatten(1);
            }

            if ($subs->isEmpty()) {
                $stats['lewati_tanpa_sub']++;
                continue;
            }
            if ($subs->count() > 1) {
                $stats['lewati_multi_sub']++;
                continue;
            }

            $sub = $subs->first();
            $keuangan = (float) $row['realisasi'];
            $anggaran = (int) round((float) $row['anggaran']);
            $pagu = (float) $sub->pagu;
            $opdIds[$opdId] = $opdId;
            $lraTotal[$opdId]['anggaran'] = ($lraTotal[$opdId]['anggaran'] ?? 0) + $anggaran;
            $lraTotal[$opdId]['realisasi'] = ($lraTotal[$opdId]['realisasi'] ?? 0) + $keuangan;

            if (abs($pagu - $anggaran) > 1) {
                $paguBeda[] = [$row['OPD'] ?? '', $sub->kode_rek, $pagu, $anggaran];

                if ($updatePagu) {
                    $komponen = DB::table('komponen_anggaran')
                        ->where('document_type', 'dpa')
                        ->where('tahun', $this->tahun)
                        ->where('opd_id', $opdId)
                        ->where('jenis', 'sub_kegiatan')
                        ->where('kode', $sub->kode_rek)
                        ->get(['id', 'pagu']);

                    $this->backup[] = [
                        'level' => 'sub_kegiatan',
                        'opd_id' => $opdId,
                        'kode' => $sub->kode_rek,
                        'sub_kegiatan' => ['id' => $sub->id, 'pagu' => $sub->pagu],
                        'komponen_anggaran' => $komponen->map(fn ($k) => ['id' => $k->id, 'pagu' => $k->pagu])->all(),
                        'pagu_baru' => $anggaran,
                    ];

                    DB::table('sub_kegiatan')->where('id', $sub->id)->update(['pagu' => $anggaran, 'updated_at' => $this->now]);
                    DB::table('komponen_anggaran')->whereIn('id', $komponen->pluck('id'))->update(['pagu' => $anggaran, 'updated_at' => $this->now]);
                    $pagu = $anggaran;
                }
            }

            $created = $this->upsertRealisasi(SubKegiatan::class, (int) $sub->id, $opdId, [
                'realisasi_keuangan' => $keuangan,
                'sisa_anggaran' => max($pagu - $keuangan, 0),
            ], 'Impor LRA ' . $fileName);
            $stats[$created ? 'diimpor' : 'diperbarui']++;
        }

        return [$stats, $paguBeda, array_values($opdIds), $lraTotal];
    }

    /**
     * Jumlahkan pagu dan realisasi triwulan sub kegiatan ke kegiatan dan program (berdasarkan awalan kode),
     * lalu tampilkan total per OPD.
     */
    private function aggregate(array $opdIds, array $lraTotal): void
    {
        if (!$opdIds) {
            return;
        }

        $subs = DB::table('sub_kegiatan as s')
            ->leftJoin('realisasi as r', function ($join) {
                $join->on('r.realisaseable_id', '=', 's.id')
                    ->where('r.realisaseable_type', SubKegiatan::class)
                    ->where('r.tahun', $this->tahun)
                    ->where('r.triwulan', $this->tw);
            })
            ->where('s.document_type', 'dpa')
            ->where('s.tahun', $this->tahun)
            ->whereIn('s.opd_id', $opdIds)
            ->get(['s.opd_id', 's.kode_rek', 's.pagu', 'r.id as rid', 'r.realisasi_keuangan', 'r.realisasi_fisik']);

        // level => "opd|kode" => totals
        $sum = ['kegiatan' => [], 'program' => []];
        foreach ($subs as $s) {
            $parts = explode('.', trim((string) $s->kode_rek));
            if (count($parts) < 6) {
                continue;
            }
            $keys = [
                'kegiatan' => $s->opd_id . '|' . implode('.', array_slice($parts, 0, 5)),
                'program' => $s->opd_id . '|' . implode('.', array_slice($parts, 0, 3)),
            ];
            foreach ($keys as $level => $key) {
                $t = $sum[$level][$key] ?? ['pagu' => 0, 'keu' => 0.0, 'fisik' => 0.0, 'n' => 0];
                $t['pagu'] += (int) $s->pagu;
                if ($s->rid) {
                    $t['keu'] += (float) $s->realisasi_keuangan;
                    $t['fisik'] += (float) $s->realisasi_fisik;
                    $t['n']++;
                }
                $sum[$level][$key] = $t;
            }
        }

        $levels = [
            'kegiatan' => ['table' => 'kegiatan', 'model' => Kegiatan::class, 'hasPagu' => false],
            'program' => ['table' => 'program', 'model' => Program::class, 'hasPagu' => true],
        ];
        $stats = [];
        $opdTotal = [];

        foreach ($levels as $level => $cfg) {
            $masters = DB::table($cfg['table'])
                ->where('document_type', 'dpa')
                ->where('tahun', $this->tahun)
                ->whereIn('opd_id', $opdIds)
                ->get(['id', 'opd_id', 'kode_rek'] + ($cfg['hasPagu'] ? [3 => 'pagu'] : []))
                ->groupBy(fn ($m) => $m->opd_id . '|' . trim((string) $m->kode_rek));

            foreach ($sum[$level] as $key => $t) {
                [$opdId, $kode] = explode('|', $key, 2);
                $master = $masters->get($key)?->first();
                if (!$master) {
                    $stats[$level . '_tanpa_master'] = ($stats[$level . '_tanpa_master'] ?? 0) + 1;
                    continue;
                }

                // Pagu komponen anggaran kegiatan/program = jumlah pagu sub kegiatannya.
                $komponen = DB::table('komponen_anggaran')
                    ->where('document_type', 'dpa')
                    ->where('tahun', $this->tahun)
                    ->where('opd_id', $opdId)
                    ->where('jenis', $level)
                    ->where('kode', $kode)
                    ->get(['id', 'pagu']);
                $changedKomponen = $komponen->filter(fn ($k) => (int) $k->pagu !== $t['pagu']);
                $masterPaguChanged = $cfg['hasPagu'] && (int) $master->pagu !== $t['pagu'];

                if ($changedKomponen->isNotEmpty() || $masterPaguChanged) {
                    $this->backup[] = [
                        'level' => $level,
                        'opd_id' => (int) $opdId,
                        'kode' => $kode,
                        $cfg['table'] => $cfg['hasPagu'] ? ['id' => $master->id, 'pagu' => $master->pagu] : null,
                        'komponen_anggaran' => $changedKomponen->map(fn ($k) => ['id' => $k->id, 'pagu' => $k->pagu])->values()->all(),
                        'pagu_baru' => $t['pagu'],
                    ];
                    DB::table('komponen_anggaran')->whereIn('id', $changedKomponen->pluck('id'))->update(['pagu' => $t['pagu'], 'updated_at' => $this->now]);
                    if ($masterPaguChanged) {
                        DB::table($cfg['table'])->where('id', $master->id)->update(['pagu' => $t['pagu'], 'updated_at' => $this->now]);
                    }
                    $stats[$level . '_pagu_diperbarui'] = ($stats[$level . '_pagu_diperbarui'] ?? 0) + 1;
                }

                if ($t['n'] > 0) {
                    $created = $this->upsertRealisasi($cfg['model'], (int) $master->id, (int) $opdId, [
                        'realisasi_keuangan' => $t['keu'],
                        'realisasi_fisik' => round($t['fisik'] / $t['n'], 2),
                        'sisa_anggaran' => max($t['pagu'] - $t['keu'], 0),
                    ], 'Akumulasi sub kegiatan');
                    $stats[$level . ($created ? '_realisasi_dibuat' : '_realisasi_diperbarui')] = ($stats[$level . ($created ? '_realisasi_dibuat' : '_realisasi_diperbarui')] ?? 0) + 1;
                }

                if ($level === 'program') {
                    $opdTotal[$opdId]['pagu'] = ($opdTotal[$opdId]['pagu'] ?? 0) + $t['pagu'];
                    $opdTotal[$opdId]['keu'] = ($opdTotal[$opdId]['keu'] ?? 0) + $t['keu'];
                }
            }
        }

        ksort($stats);
        $this->table(['Hasil akumulasi', 'Jumlah'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        $names = DB::table('opds')->whereIn('id', $opdIds)->pluck('nama', 'id');
        $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
        $tableRows = [];
        $grand = ['pagu' => 0, 'keu' => 0, 'lra_ang' => 0, 'lra_real' => 0];
        foreach ($opdTotal as $opdId => $t) {
            $lra = $lraTotal[$opdId] ?? ['anggaran' => 0, 'realisasi' => 0];
            $tableRows[] = [
                $names[$opdId] ?? $opdId,
                $fmt($t['pagu']),
                $fmt($t['keu']),
                $t['pagu'] > 0 ? number_format($t['keu'] / $t['pagu'] * 100, 2, ',', '.') . '%' : '-',
                abs($t['keu'] - $lra['realisasi']) < 1 ? 'sama' : 'beda ' . $fmt($t['keu'] - $lra['realisasi']),
            ];
            $grand['pagu'] += $t['pagu'];
            $grand['keu'] += $t['keu'];
            $grand['lra_real'] += $lra['realisasi'];
        }
        usort($tableRows, fn ($a, $b) => strcmp((string) $a[0], (string) $b[0]));
        $tableRows[] = ['TOTAL', $fmt($grand['pagu']), $fmt($grand['keu']), $grand['pagu'] > 0 ? number_format($grand['keu'] / $grand['pagu'] * 100, 2, ',', '.') . '%' : '-', abs($grand['keu'] - $grand['lra_real']) < 1 ? 'sama' : 'beda ' . $fmt($grand['keu'] - $grand['lra_real'])];
        $this->table(['OPD', 'Pagu DPA', 'Realisasi TW' . $this->tw, '%', 'vs LRA'], $tableRows);
    }

    /** @return bool true bila baris baru dibuat */
    private function upsertRealisasi(string $type, int $id, int $opdId, array $values, string $catatan): bool
    {
        $values += ['opd_id' => $opdId, 'document_type' => 'dpa', 'updated_at' => $this->now];

        $existing = DB::table('realisasi')
            ->where('realisaseable_type', $type)
            ->where('realisaseable_id', $id)
            ->where('tahun', $this->tahun)
            ->whereNull('tahun_ke')
            ->where('triwulan', $this->tw)
            ->first(['id']);

        if ($existing) {
            if ($type === SubKegiatan::class) {
                unset($values['realisasi_fisik']); // fisik yang sudah diisi tidak ditimpa
            }
            DB::table('realisasi')->where('id', $existing->id)->update($values);
            return false;
        }

        DB::table('realisasi')->insert($values + [
            'realisaseable_type' => $type,
            'realisaseable_id' => $id,
            'tahun' => $this->tahun,
            'tahun_ke' => null,
            'triwulan' => $this->tw,
            'realisasi_fisik' => $values['realisasi_fisik'] ?? 0,
            'catatan' => $catatan,
            'input_by' => $this->userId,
            'created_at' => $this->now,
        ]);
        return true;
    }
}
