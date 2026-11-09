<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Kegiatan;
use App\Models\KomponenAnggaran;
use App\Models\Opd;
use App\Models\Program;
use App\Models\SubKegiatan;
use Illuminate\Support\Facades\DB;

$year = 2026;
$file = __DIR__ . '/../referensi/realisasi/2026/pagu dpa 2026.json';

if (!file_exists($file)) {
    echo "File tidak ditemukan: {$file}\n";
    exit(1);
}

$raw = (string) file_get_contents($file);
$rows = json_decode($raw, true);
if (!is_array($rows)) {
    $rows = json_decode('[' . trim($raw) . ']', true);
}

if (!is_array($rows)) {
    echo "Gagal parse JSON: {$file}\n";
    exit(1);
}

$normalizePagu = static function ($value): int {
    if (is_int($value)) {
        return $value;
    }

    if (is_float($value)) {
        return (int) round($value);
    }

    $s = trim((string) $value);
    if ($s === '') {
        return 0;
    }

    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);

    if (!is_numeric($s)) {
        return 0;
    }

    return (int) round((float) $s);
};

$extractKodeProgram = static function (string $kodeSub): string {
    $parts = array_values(array_filter(explode('.', $kodeSub), static fn ($part) => $part !== ''));
    return implode('.', array_slice($parts, 0, 3));
};

$extractKodeKegiatan = static function (string $kodeSub): string {
    $parts = array_values(array_filter(explode('.', $kodeSub), static fn ($part) => $part !== ''));
    return implode('.', array_slice($parts, 0, 5));
};

$extractUrusan = static function (string $kode): array {
    $parts = array_values(array_filter(explode('.', $kode), static fn ($part) => $part !== ''));
    $urusan = $parts[0] ?? '';
    $bidang = count($parts) >= 2 ? $parts[0] . '.' . $parts[1] : $urusan;
    return [$urusan, $bidang];
};

$truncate = static function (string $text, int $max): string {
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return mb_substr($text, 0, $max);
};

$opdMap = Opd::query()->select(['id', 'kode', 'nama'])->get()->keyBy('kode');

$grouped = [];
$skippedNoOpd = 0;
$skippedNoSub = 0;

foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }

    $kodeSubKegiatan = trim((string) ($row['KODE SUB KEGIATAN'] ?? ''));
    if ($kodeSubKegiatan === '') {
        $skippedNoSub++;
        continue;
    }

    $kodeSubUnit = trim((string) ($row['KODE SUB UNIT'] ?? ''));
    $kodeSkpd = trim((string) ($row['KODE SKPD'] ?? ''));
    $targetKodeOpd = $kodeSubUnit !== '' ? $kodeSubUnit : $kodeSkpd;

    $opd = $opdMap->get($targetKodeOpd);
    if (!$opd) {
        $skippedNoOpd++;
        continue;
    }

    $key = $opd->id . '|' . $kodeSubKegiatan;
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'opd_id' => (int) $opd->id,
            'opd_kode' => (string) $opd->kode,
            'opd_nama' => (string) $opd->nama,
            'kode_sub_kegiatan' => $kodeSubKegiatan,
            'nama_sub_kegiatan' => trim((string) ($row['NAMA SUB KEGIATAN'] ?? '')),
            'kode_kegiatan' => trim((string) ($row['KODE KEGIATAN'] ?? '')),
            'nama_kegiatan' => trim((string) ($row['NAMA KEGIATAN'] ?? '')),
            'kode_program' => trim((string) ($row['KODE PROGRAM'] ?? '')),
            'nama_program' => trim((string) ($row['NAMA PROGRAM'] ?? '')),
            'sub_unit' => trim((string) ($row['NAMA SUB UNIT'] ?? '')),
            'pagu' => 0,
        ];
    }

    $grouped[$key]['pagu'] += $normalizePagu($row['PAGU'] ?? 0);
}

$programCreated = 0;
$programUpdated = 0;
$kegiatanCreated = 0;
$kegiatanUpdated = 0;
$subCreated = 0;
$subUpdated = 0;
$kompProgramCreated = 0;
$kompProgramUpdated = 0;
$kompKegCreated = 0;
$kompKegUpdated = 0;
$kompSubCreated = 0;
$kompSubUpdated = 0;

DB::beginTransaction();
try {
    foreach ($grouped as $g) {
        $kodeProgram = $g['kode_program'] !== '' ? $g['kode_program'] : $extractKodeProgram($g['kode_sub_kegiatan']);
        $kodeKegiatan = $g['kode_kegiatan'] !== '' ? $g['kode_kegiatan'] : $extractKodeKegiatan($g['kode_sub_kegiatan']);

        [$progUrusan, $progBidang] = $extractUrusan($kodeProgram);
        [$kegUrusan, $kegBidang] = $extractUrusan($kodeKegiatan);
        [$subUrusan, $subBidang] = $extractUrusan($g['kode_sub_kegiatan']);

        $program = Program::query()->where([
            'opd_id' => $g['opd_id'],
            'kode_rek' => $kodeProgram,
            'document_type' => 'dpa',
            'tahun' => $year,
        ])->first();

        if (!$program) {
            $program = Program::create([
                'opd_id' => $g['opd_id'],
                'kepmen_id' => 1,
                'document_type' => 'dpa',
                'kode_rek' => $kodeProgram,
                'nama_rincian' => $truncate($g['nama_program'] !== '' ? $g['nama_program'] : ('Program ' . $kodeProgram), 500),
                'pagu' => 0,
                'tahun' => $year,
            ]);
            $programCreated++;
        } else {
            if ($g['nama_program'] !== '' && $program->nama_rincian !== $g['nama_program']) {
                $program->nama_rincian = $truncate($g['nama_program'], 500);
                $program->save();
            }
            $programUpdated++;
        }

        $kegiatan = Kegiatan::query()->where([
            'opd_id' => $g['opd_id'],
            'kode_rek' => $kodeKegiatan,
            'document_type' => 'dpa',
            'tahun' => $year,
        ])->first();

        if (!$kegiatan) {
            $kegiatan = Kegiatan::create([
                'program_id' => $program->id,
                'opd_id' => $g['opd_id'],
                'kepmen_id' => 1,
                'document_type' => 'dpa',
                'kode_rek' => $kodeKegiatan,
                'nama_rincian' => $truncate($g['nama_kegiatan'] !== '' ? $g['nama_kegiatan'] : ('Kegiatan ' . $kodeKegiatan), 500),
                'tahun' => $year,
            ]);
            $kegiatanCreated++;
        } else {
            $dirty = false;
            if ((int) $kegiatan->program_id !== (int) $program->id) {
                $kegiatan->program_id = $program->id;
                $dirty = true;
            }
            if ($g['nama_kegiatan'] !== '' && $kegiatan->nama_rincian !== $g['nama_kegiatan']) {
                $kegiatan->nama_rincian = $truncate($g['nama_kegiatan'], 500);
                $dirty = true;
            }
            if ($dirty) {
                $kegiatan->save();
            }
            $kegiatanUpdated++;
        }

        $sub = SubKegiatan::query()->where([
            'opd_id' => $g['opd_id'],
            'kode_rek' => $g['kode_sub_kegiatan'],
            'document_type' => 'dpa',
            'tahun' => $year,
        ])->first();

        if (!$sub) {
            SubKegiatan::create([
                'kegiatan_id' => $kegiatan->id,
                'opd_id' => $g['opd_id'],
                'kepmen_id' => 1,
                'document_type' => 'dpa',
                'kode_rek' => $g['kode_sub_kegiatan'],
                'nama_rincian' => $truncate($g['nama_sub_kegiatan'] !== '' ? $g['nama_sub_kegiatan'] : ('Sub Kegiatan ' . $g['kode_sub_kegiatan']), 500),
                'pagu' => (int) $g['pagu'],
                'tahun_awal' => $year,
                'tahun_akhir' => $year,
                'target_t1' => 0,
                'target_t2' => 0,
                'target_t3' => 0,
                'target_t4' => 0,
                'target_t5' => 0,
                'target_tahunan' => 0,
                'tahun' => $year,
                'catatan_evaluasi' => null,
            ]);
            $subCreated++;
        } else {
            $sub->kegiatan_id = $kegiatan->id;
            if ($g['nama_sub_kegiatan'] !== '') {
                $sub->nama_rincian = $truncate($g['nama_sub_kegiatan'], 500);
            }
            $sub->pagu = (int) $g['pagu'];
            $sub->save();
            $subUpdated++;
        }

        $kompProgram = KomponenAnggaran::query()->where([
            'opd_id' => $g['opd_id'],
            'document_type' => 'dpa',
            'jenis' => 'program',
            'parent_id' => null,
            'kode' => $kodeProgram,
            'tahun' => $year,
        ])->first();

        if (!$kompProgram) {
            $kompProgram = KomponenAnggaran::create([
                'opd_id' => $g['opd_id'],
                'document_type' => 'dpa',
                'parent_id' => null,
                'kode' => $kodeProgram,
                'kode_program' => $kodeProgram,
                'jenis' => 'program',
                'sub_unit' => $g['sub_unit'] !== '' ? $g['sub_unit'] : $g['opd_nama'],
                'urusan' => $progUrusan,
                'bidang_urusan' => $progBidang,
                'tahun' => $year,
                'nama_komponen' => $truncate($g['nama_program'] !== '' ? $g['nama_program'] : ('Program ' . $kodeProgram), 255),
                'pagu' => 0,
            ]);
            $kompProgramCreated++;
        } else {
            if ($g['nama_program'] !== '' && $kompProgram->nama_komponen !== $g['nama_program']) {
                $kompProgram->nama_komponen = $truncate($g['nama_program'], 255);
                $kompProgram->save();
            }
            $kompProgramUpdated++;
        }

        $kompKeg = KomponenAnggaran::query()->where([
            'opd_id' => $g['opd_id'],
            'document_type' => 'dpa',
            'jenis' => 'kegiatan',
            'parent_id' => $kompProgram->id,
            'kode' => $kodeKegiatan,
            'tahun' => $year,
        ])->first();

        if (!$kompKeg) {
            $kompKeg = KomponenAnggaran::create([
                'opd_id' => $g['opd_id'],
                'document_type' => 'dpa',
                'parent_id' => $kompProgram->id,
                'kode' => $kodeKegiatan,
                'kode_program' => $kodeProgram,
                'jenis' => 'kegiatan',
                'sub_unit' => $g['sub_unit'] !== '' ? $g['sub_unit'] : $g['opd_nama'],
                'urusan' => $kegUrusan,
                'bidang_urusan' => $kegBidang,
                'tahun' => $year,
                'nama_komponen' => $truncate($g['nama_kegiatan'] !== '' ? $g['nama_kegiatan'] : ('Kegiatan ' . $kodeKegiatan), 255),
                'pagu' => 0,
            ]);
            $kompKegCreated++;
        } else {
            $dirty = false;
            if ((int) $kompKeg->parent_id !== (int) $kompProgram->id) {
                $kompKeg->parent_id = $kompProgram->id;
                $dirty = true;
            }
            if ($g['nama_kegiatan'] !== '' && $kompKeg->nama_komponen !== $g['nama_kegiatan']) {
                $kompKeg->nama_komponen = $truncate($g['nama_kegiatan'], 255);
                $dirty = true;
            }
            if ($dirty) {
                $kompKeg->save();
            }
            $kompKegUpdated++;
        }

        $kompSub = KomponenAnggaran::query()->where([
            'opd_id' => $g['opd_id'],
            'document_type' => 'dpa',
            'jenis' => 'sub_kegiatan',
            'parent_id' => $kompKeg->id,
            'kode' => $g['kode_sub_kegiatan'],
            'tahun' => $year,
        ])->first();

        if (!$kompSub) {
            KomponenAnggaran::create([
                'opd_id' => $g['opd_id'],
                'document_type' => 'dpa',
                'parent_id' => $kompKeg->id,
                'kode' => $g['kode_sub_kegiatan'],
                'kode_program' => $kodeProgram,
                'jenis' => 'sub_kegiatan',
                'sub_unit' => $g['sub_unit'] !== '' ? $g['sub_unit'] : $g['opd_nama'],
                'urusan' => $subUrusan,
                'bidang_urusan' => $subBidang,
                'tahun' => $year,
                'nama_komponen' => $truncate($g['nama_sub_kegiatan'] !== '' ? $g['nama_sub_kegiatan'] : ('Sub Kegiatan ' . $g['kode_sub_kegiatan']), 255),
                'pagu' => (int) $g['pagu'],
            ]);
            $kompSubCreated++;
        } else {
            $kompSub->pagu = (int) $g['pagu'];
            if ($g['nama_sub_kegiatan'] !== '') {
                $kompSub->nama_komponen = $truncate($g['nama_sub_kegiatan'], 255);
            }
            $kompSub->save();
            $kompSubUpdated++;
        }
    }

    $kegiatanRows = KomponenAnggaran::query()
        ->where('document_type', 'dpa')
        ->where('tahun', $year)
        ->where('jenis', 'kegiatan')
        ->get(['id']);

    foreach ($kegiatanRows as $keg) {
        $sum = (int) KomponenAnggaran::query()
            ->where('document_type', 'dpa')
            ->where('tahun', $year)
            ->where('jenis', 'sub_kegiatan')
            ->where('parent_id', $keg->id)
            ->sum('pagu');

        KomponenAnggaran::query()->where('id', $keg->id)->update(['pagu' => $sum]);
    }

    $programRows = KomponenAnggaran::query()
        ->where('document_type', 'dpa')
        ->where('tahun', $year)
        ->where('jenis', 'program')
        ->whereNull('parent_id')
        ->get(['id']);

    foreach ($programRows as $prog) {
        $sum = (int) KomponenAnggaran::query()
            ->where('document_type', 'dpa')
            ->where('tahun', $year)
            ->where('jenis', 'kegiatan')
            ->where('parent_id', $prog->id)
            ->sum('pagu');

        KomponenAnggaran::query()->where('id', $prog->id)->update(['pagu' => $sum]);
    }

    $programModels = Program::query()
        ->where('document_type', 'dpa')
        ->where('tahun', $year)
        ->get(['id']);

    foreach ($programModels as $programModel) {
        $sum = (int) DB::table('sub_kegiatan')
            ->join('kegiatan', 'sub_kegiatan.kegiatan_id', '=', 'kegiatan.id')
            ->where('kegiatan.program_id', $programModel->id)
            ->where('sub_kegiatan.document_type', 'dpa')
            ->where('sub_kegiatan.tahun', $year)
            ->sum('sub_kegiatan.pagu');

        Program::query()->where('id', $programModel->id)->update(['pagu' => $sum]);
    }

    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "SYNC_DONE\n";
echo "ROW_SOURCE=" . count($rows) . "\n";
echo "GROUPED_SUB=" . count($grouped) . "\n";
echo "SKIP_NO_SUB={$skippedNoSub}\n";
echo "SKIP_NO_OPD={$skippedNoOpd}\n";
echo "PROGRAM_CREATED={$programCreated}|UPDATED={$programUpdated}\n";
echo "KEGIATAN_CREATED={$kegiatanCreated}|UPDATED={$kegiatanUpdated}\n";
echo "SUB_CREATED={$subCreated}|UPDATED={$subUpdated}\n";
echo "KOMP_PROG_CREATED={$kompProgramCreated}|UPDATED={$kompProgramUpdated}\n";
echo "KOMP_KEG_CREATED={$kompKegCreated}|UPDATED={$kompKegUpdated}\n";
echo "KOMP_SUB_CREATED={$kompSubCreated}|UPDATED={$kompSubUpdated}\n";