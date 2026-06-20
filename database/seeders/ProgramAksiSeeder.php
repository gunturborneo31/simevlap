<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Program;

class ProgramAksiSeeder extends Seeder
{
    public function run(): void
    {
        $path = 'd:/bappelitbangda mahulu/rpjmd/tabel/program_aksi.csv';
        if (!file_exists($path)) {
            $this->command->error('File CSV tidak ditemukan: ' . $path);
            return;
        }
        $lines = file($path);
        $header = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'tabel;') || str_starts_with($line, ';;;')) continue;
            $row = str_getcsv($line, ";");
            if (!$header && isset($row[0]) && trim($row[0]) === 'kode_rek') {
                $header = $row;
                continue;
            }
            if (!$header) continue;
            // Data baris
            if (count($row) < 5 || trim($row[0]) === '' || trim($row[2]) === '') continue;
            $data = array_combine($header, $row + array_fill(0, count($header), ''));
            Program::create([
                'opd_id' => null,
                'kepmen_id' => null,
                'document_type' => $data['documen_type'] ?? 'rpjmd',
                'jenis_program' => $data['jenis_program'] ?? 'program-aksi',
                'kode_rek' => $data['kode_rek'],
                'nama_rincian' => $data['uraian'],
                'pagu' => $data['pagu'] ?? 0,
                'is_prioritas' => 1,
            ]);
        }
        $this->command->info('Import program-aksi selesai.');
    }
}
