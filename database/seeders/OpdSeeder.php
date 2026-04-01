<?php

namespace Database\Seeders;

use App\Models\Opd;
use Illuminate\Database\Seeder;

class OpdSeeder extends Seeder
{
    public function run(): void
    {
        $opds = [
            ['kode' => '1.01', 'nama' => 'Dinas Pendidikan', 'singkatan' => 'Disdik'],
            ['kode' => '1.02', 'nama' => 'Dinas Kesehatan', 'singkatan' => 'Dinkes'],
            ['kode' => '2.01', 'nama' => 'Dinas Pekerjaan Umum dan Penataan Ruang', 'singkatan' => 'DPUPR'],
            ['kode' => '2.02', 'nama' => 'Dinas Perumahan dan Kawasan Permukiman', 'singkatan' => 'Disperkim'],
            ['kode' => '4.01', 'nama' => 'Badan Perencanaan Pembangunan Daerah', 'singkatan' => 'Bappeda'],
            ['kode' => '4.02', 'nama' => 'Badan Pengelolaan Keuangan dan Aset Daerah', 'singkatan' => 'BPKAD'],
            ['kode' => '5.01', 'nama' => 'Sekretariat Daerah', 'singkatan' => 'Setda'],
            ['kode' => '5.02', 'nama' => 'Sekretariat DPRD', 'singkatan' => 'Setwan'],
        ];
        foreach ($opds as $opd) {
            Opd::firstOrCreate(['kode' => $opd['kode']], $opd);
        }
    }
}
