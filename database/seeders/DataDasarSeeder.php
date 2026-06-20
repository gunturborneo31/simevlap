<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Visi;
use App\Models\Misi;
use App\Models\Tujuan;
use App\Models\Sasaran;

class DataDasarSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('json/master.json'));
        $data = json_decode($json, true);

        // VISI
        $visiMap = [];
        if (!empty($data['visi'])) {
            foreach ($data['visi'] as $v) {
                if (!$v || empty($v['kode visi'])) continue;
                $visi = Visi::create([
                    'kode' => $v['kode visi'],
                    'uraian' => $v['uraian visi'],
                    'document_type' => 'rpjmd',
                    'tahun_awal' => 2026,
                    'tahun_akhir' => 2031,
                ]);
                $visiMap[$v['kode visi']] = $visi->id;
            }
        }

        // MISI
        $misiMap = [];
        if (!empty($data['misi'])) {
            foreach ($data['misi'] as $m) {
                if (!$m || empty($m['kode misi'])) continue;
                // Asosiasi ke visi 1,2,3,4 dst urut
                $visiId = $visiMap['visi ' . substr($m['kode misi'], -1)] ?? null;
                $misi = Misi::create([
                    'visi_id' => $visiId,
                    'kode' => $m['kode misi'],
                    'uraian' => $m['uraian misi'],
                ]);
                $misiMap[$m['kode misi']] = $misi->id;
            }
        }

        // TUJUAN
        $tujuanMap = [];
        if (!empty($data['tujuan'])) {
            foreach ($data['tujuan'] as $t) {
                if (!$t || empty($t['kode tujuan'])) continue;
                // Asosiasi ke misi 1,2,3,4 dst urut
                $misiId = $misiMap['misi ' . substr($t['kode tujuan'], -1)] ?? null;
                $tujuan = Tujuan::create([
                    'misi_id' => $misiId,
                    'kode' => $t['kode tujuan'],
                    'uraian' => $t['uraian tujuan'],
                ]);
                $tujuanMap[$t['kode tujuan']] = $tujuan->id;
            }
        }

        // SASARAN
        $sasaranMap = [];
        if (!empty($data['sasaran'])) {
            foreach ($data['sasaran'] as $s) {
                if (!$s || empty($s['kode sasaran'])) continue;
                // Asosiasi ke tujuan 1,2,3,4 dst urut
                $tujuanId = $tujuanMap['tujuan ' . substr($s['kode sasaran'], -1)] ?? null;
                $sasaran = Sasaran::create([
                    'tujuan_id' => $tujuanId,
                    'kode' => $s['kode sasaran'],
                    'uraian' => $s['uraian sasaran'],
                ]);
                $sasaranMap[$s['kode sasaran']] = $sasaran->id;
            }
        }

        // KONEKTING tujuan-sasaran
        if (!empty($data['konekting'])) {
            foreach ($data['konekting'] as $rel) {
                $tujuanId = $tujuanMap[$rel['kode tujuan']] ?? null;
                $sasaranId = $sasaranMap[$rel['kode sasaran']] ?? null;
                if ($tujuanId && $sasaranId) {
                    DB::table('data_dasar_relasi')->insert([
                        'child_type' => 'sasaran',
                        'child_id' => $sasaranId,
                        'parent_type' => 'tujuan',
                        'parent_id' => $tujuanId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
