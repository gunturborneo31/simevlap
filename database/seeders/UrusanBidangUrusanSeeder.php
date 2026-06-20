<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class UrusanBidangUrusanSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $urusanPath = base_path('referensi/apbd/urusan.json');
        $bidangPath = base_path('referensi/apbd/bidang_urusan.json');

        if (!File::exists($urusanPath) || !File::exists($bidangPath)) {
            $this->command?->warn('File referensi urusan atau bidang urusan tidak ditemukan.');
            return;
        }

        $urusanRaw = json_decode(File::get($urusanPath), true) ?: [];
        $bidangRaw = json_decode(File::get($bidangPath), true) ?: [];

        $urusanPayload = collect($urusanRaw)
            ->map(function (array $item) use ($now) {
                $kode = trim((string) ($item['KODE_URUSAN'] ?? ''));
                $nama = trim((string) ($item['NAMA_URUSAN'] ?? ''));

                if ($kode === '' || $nama === '') {
                    return null;
                }

                return [
                    'kode' => $kode,
                    'nama' => $nama,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter()
            ->unique('kode')
            ->values()
            ->all();

        $bidangPayload = collect($bidangRaw)
            ->map(function (array $item) use ($now) {
                $kode = trim((string) ($item['KODE_BIDANG_URUSAN'] ?? ''));
                $nama = trim((string) ($item['NAMA_BIDANG_URUSAN'] ?? ''));

                if ($kode === '' || $nama === '') {
                    return null;
                }

                return [
                    'kode' => $kode,
                    'nama' => $nama,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter()
            ->unique('kode')
            ->values()
            ->all();

        if (!empty($urusanPayload)) {
            DB::table('urusans')->upsert($urusanPayload, ['kode'], ['nama', 'updated_at']);
        }

        if (!empty($bidangPayload)) {
            DB::table('bidang_urusans')->upsert($bidangPayload, ['kode'], ['nama', 'updated_at']);
        }

        $this->command?->info('Import urusan & bidang urusan selesai.');
    }
}
