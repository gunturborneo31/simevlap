<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use App\Models\ResumeProgramAnnotation;

class ImportPermasalahan extends Command
{
    protected $signature = 'import:permasalahan {file} {--year=2026} {--table=berdasarkan-bidang-urusan}';

    protected $description = 'Import permasalahan SQL dump into resume_program_annotations';

    public function handle()
    {
        $file = $this->argument('file');
        $year = (int) $this->option('year');
        $tableName = $this->option('table');

        $fs = new Filesystem();
        if (! $fs->exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $contents = $fs->get($file);

        // Find all INSERT INTO b VALUES(...) statements
        preg_match_all('/INSERT INTO\s+\w+\s+VALUES\s*\((.*?)\);/si', $contents, $matches);

        $rows = $matches[1] ?? [];

        $this->info('Found '.count($rows).' rows to import.');

        $success = 0;
        $failures = [];

        foreach ($rows as $i => $rowStr) {
            // prepare for CSV parsing: remove trailing/leading spaces
            $line = trim($rowStr);
            // Use str_getcsv with enclosure ' and delimiter ,
            $cols = str_getcsv($line, ',', "'");

            // Expected columns (from file): id, kode_progra, nama_progra, unit_id, pagu, id_permasalahan, faktor_penghambat, faktor_pendorong, tindak_lanjut, keterangan, tahun
            if (count($cols) < 11) {
                $failures[] = ['row' => $i+1, 'reason' => 'unexpected column count', 'raw' => $line];
                continue;
            }

            [$id, $kode_progra, $nama_progra, $unit_id, $pagu, $id_permasalahan, $faktor_penghambat, $faktor_pendorong, $tindak_lanjut, $keterangan, $tahun] = $cols;

            $data = [
                'view' => 'rekap-permasalahan',
                'table_name' => $tableName,
                'basis' => 'bidang-urusan',
                'tahun' => $year,
                'entitas' => $unit_id ?: null,
                'program_kode' => $kode_progra ?: null,
                'program_nama' => $nama_progra ?: null,
                'faktor_penghambat' => $faktor_penghambat ?: null,
                'faktor_pendorong' => $faktor_pendorong ?: null,
                'faktor_tindak_lanjut' => $tindak_lanjut ?: null,
            ];

            try {
                ResumeProgramAnnotation::create($data);
                $success++;
            } catch (\Throwable $e) {
                $failures[] = ['row' => $i+1, 'reason' => $e->getMessage(), 'data' => $data];
            }
        }

        $this->info("Import completed. Success: {$success}, Failures: ".count($failures));

        if (! empty($failures)) {
            $this->line('Failures detail:');
            foreach ($failures as $f) {
                $this->line(sprintf("Row %s: %s", $f['row'], $f['reason']));
                if (isset($f['raw'])) {
                    $this->line(' Raw: '.$f['raw']);
                }
            }
        }

        return 0;
    }
}
