<?php

namespace Database\Seeders;

use App\Models\Opd;
use Illuminate\Database\Seeder;

class OpdSeeder extends Seeder
{
    public function run(): void
    {
        $skpdPath = base_path('referensi/apbd/skpd.json');
        $subUnitPath = base_path('referensi/apbd/sub_unit.json');

        if (!file_exists($skpdPath)) {
            $this->command->error('File referensi/apbd/skpd.json not found.');
            return;
        }

        $skpdRows = json_decode(file_get_contents($skpdPath), true) ?: [];

        foreach ($skpdRows as $row) {
            Opd::updateOrCreate(
                ['kode' => $row['KODE_SKPD']],
                ['nama' => $row['NAMA_SKPD']]
            );
        }

        // Tambahkan juga semua sub unit agar data seperti Sekretariat Daerah
        // bisa dipecah dan dipetakan per Bagian berdasarkan KODE_SUB_UNIT.
        if (!file_exists($subUnitPath)) {
            $this->command->warn('File referensi/apbd/sub_unit.json not found. Skip sub unit sync.');
            return;
        }

        $subUnitRows = json_decode(file_get_contents($subUnitPath), true) ?: [];

        foreach ($subUnitRows as $row) {
            $kode = $row['KODE_SUB_UNIT'] ?? null;
            $nama = $row['NAMA_SUB_UNIT'] ?? null;

            if (!$kode || !$nama) {
                continue;
            }

            Opd::updateOrCreate(
                ['kode' => $kode],
                ['nama' => $nama]
            );
        }
    }
}
