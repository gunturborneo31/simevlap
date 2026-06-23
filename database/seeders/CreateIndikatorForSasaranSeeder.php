<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Sasaran;
use App\Models\Indikator;

class CreateIndikatorForSasaranSeeder extends Seeder
{
    public function run()
    {
        $path = base_path('referensi/pusat/indikator_sasaran.json');

        if (! File::exists($path)) {
            $this->command->error("File not found: {$path}");
            return;
        }

        $json = json_decode(File::get($path), true);
        if (! is_array($json)) {
            $this->command->error('Invalid JSON in indikator_sasaran.json');
            return;
        }

        foreach ($json as $row) {
            $kodeSasaran = $row['sasaran'] ?? null;
            if (! $kodeSasaran) continue;

            $sasaran = Sasaran::where('kode', $kodeSasaran)->first();
            if (! $sasaran) {
                $this->command->warn("Sasaran not found: {$kodeSasaran}");
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

            // Attach per-year targets (target_YYYY)
            foreach ($row as $key => $val) {
                if (preg_match('/^target_(\d{4})$/', $key, $m)) {
                    $tahun = (int)$m[1];
                    $target = $val;

                    // normalize numeric target: handle comma decimals and ranges
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
                            'indicatorable_type' => Sasaran::class,
                            'indicatorable_id' => $sasaran->id,
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

        $this->command->info('Done: indikator for sasaran imported/attached.');
    }
}
