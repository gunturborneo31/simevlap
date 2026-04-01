<?php

namespace Database\Seeders;

use App\Models\Kepmen;
use Illuminate\Database\Seeder;

class KepmenSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['kode' => '1.01.01', 'nama' => 'Program Penunjang Urusan Pemerintahan Daerah', 'tahun' => '2022'],
            ['kode' => '1.01.01.1.01', 'nama' => 'Perencanaan, Penganggaran, dan Evaluasi Kinerja Perangkat Daerah', 'tahun' => '2022'],
            ['kode' => '1.01.01.1.01.0001', 'nama' => 'Penyusunan Dokumen Perencanaan Perangkat Daerah', 'tahun' => '2022'],
            ['kode' => '1.01.02', 'nama' => 'Program Pendidikan', 'tahun' => '2022'],
            ['kode' => '1.01.02.1.01', 'nama' => 'Pengelolaan Pendidikan Sekolah Dasar', 'tahun' => '2022'],
            ['kode' => '1.01.02.1.01.0001', 'nama' => 'Pembangunan Sarana dan Prasarana Sekolah', 'tahun' => '2022'],
            ['kode' => '1.02.02', 'nama' => 'Program Pemenuhan Upaya Kesehatan Perorangan dan Upaya Kesehatan Masyarakat', 'tahun' => '2022'],
            ['kode' => '1.02.02.1.01', 'nama' => 'Pengelolaan Pelayanan Kesehatan Ibu Hamil', 'tahun' => '2022'],
            ['kode' => '1.02.02.1.02', 'nama' => 'Pengelolaan Pelayanan Kesehatan Balita', 'tahun' => '2022'],
        ];
        foreach ($data as $item) {
            Kepmen::firstOrCreate(['kode' => $item['kode']], $item);
        }
    }
}
