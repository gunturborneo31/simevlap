<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Tujuan;
use App\Models\Indikator;

class CreateIndikatorForTujuanSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('referensi/pusat/indikator_tujuan.json');

        if (! File::exists($path)) {
            $this->command->error("File not found: {$path}");
            return;
        }

        $json = json_decode(File::get($path), true);
        if (! is_array($json)) {
            $this->command->error('Invalid JSON in indikator_tujuan.json');
            return;
        }

        foreach ($json as $row) {
            $kodeTujuan = $row['tujuan'] ?? ($row['kode_tujuan'] ?? null);
            if (! $kodeTujuan) continue;

            $tujuan = Tujuan::where('kode', $kodeTujuan)->first();
            if (! $tujuan) {
                $this->command->warn("Tujuan not found: {$kodeTujuan}");
                continue;
            }

            $uraian = trim($row['indikator'] ?? '');
            if ($uraian === '') continue;

            $indikator = Indikator::firstOrCreate(
                ['uraian' => $uraian],
                [
                    'jenis_indikator' => 'IKU',
                    'satuan' => $row['satuan'] ?? '-',
                    'jenis' => 'outcome',
                    'sifat' => 'maximize',
                    'keterangan' => null,
                ]
            );

            foreach ($row as $key => $val) {
                if (preg_match('/^target_(\d{4})$/', $key, $m)) {
                    $tahun = (int)$m[1];
                    $target = $val;

                    $targetNum = 0;
                    if ($target !== null && $target !== '') {
                        $s = (string) $target;
                        $s = str_replace([',','–','—'], ['.','-','-'], $s);
                        preg_match_all('/-?\d+(?:\.\d+)?/', $s, $matches);
                        if (! empty($matches[0])) {
                            $nums = array_map('floatval', $matches[0]);
                            $targetNum = array_sum($nums) / count($nums);
                        }
                    }

                    DB::table('indikatorables')->updateOrInsert(
                        [
                            'indikator_id' => $indikator->id,
                            'indicatorable_type' => Tujuan::class,
                            'indicatorable_id' => $tujuan->id,
                            'tahun' => $tahun,
                        ],
                        [
                            'target' => $targetNum,
                            'realisasi' => null,
                            'triwulan' => null,
                            'catatan' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        $this->command->info('Done: indikator for tujuan imported/attached.');
    }
}
