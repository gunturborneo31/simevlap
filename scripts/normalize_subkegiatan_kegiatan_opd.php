<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Kegiatan;
use App\Models\Program;
use App\Models\SubKegiatan;

$opts = getopt('', [
    'file::',
    'tahun::',
    'document_type::',
    'dry-run',
]);

$filePath = $opts['file'] ?? (__DIR__ . '/../referensi/realisasi/2026/tw_2_missing_subkegiatan_grouped.json');
$tahun = isset($opts['tahun']) ? (int) $opts['tahun'] : 2026;
$documentType = (string) ($opts['document_type'] ?? 'dpa');
$dryRun = array_key_exists('dry-run', $opts);

if (!file_exists($filePath)) {
    echo "File tidak ditemukan: {$filePath}\n";
    exit(1);
}

$json = json_decode((string) file_get_contents($filePath), true);
if (!is_array($json) || !isset($json['groups']) || !is_array($json['groups'])) {
    echo "Format grouped JSON tidak valid: {$filePath}\n";
    exit(1);
}

$updatedSub = 0;
$createdKegiatan = 0;
$alreadyOk = 0;
$skippedNoSub = 0;
$skippedNoKodeKegiatan = 0;

$sampleUpdated = [];

foreach ($json['groups'] as $group) {
    $opdId = (int) ($group['opd_id'] ?? 0);
    $kodeSub = trim((string) ($group['kode_sub_kegiatan'] ?? ''));
    if ($opdId <= 0 || $kodeSub === '') {
        continue;
    }

    $subs = SubKegiatan::query()
        ->where('opd_id', $opdId)
        ->where('kode_rek', $kodeSub)
        ->where('document_type', $documentType)
        ->where('tahun', $tahun)
        ->get();

    if ($subs->isEmpty()) {
        $skippedNoSub++;
        continue;
    }

    $parts = explode('.', $kodeSub);
    if (count($parts) < 5) {
        $skippedNoKodeKegiatan++;
        continue;
    }

    $kodeKegiatan = implode('.', array_slice($parts, 0, 5));

    $targetKegiatan = Kegiatan::query()
        ->where('opd_id', $opdId)
        ->where('kode_rek', $kodeKegiatan)
        ->where('document_type', $documentType)
        ->where('tahun', $tahun)
        ->first();

    if (!$targetKegiatan) {
        $targetKegiatan = Kegiatan::query()
            ->where('opd_id', $opdId)
            ->where('kode_rek', $kodeKegiatan)
            ->first();
    }

    if (!$targetKegiatan) {
        $refSub = SubKegiatan::query()
            ->where('kode_rek', $kodeSub)
            ->where('document_type', $documentType)
            ->where('tahun', $tahun)
            ->first();

        if (!$refSub) {
            $refSub = SubKegiatan::query()->where('kode_rek', $kodeSub)->first();
        }

        $refKegiatan = null;
        if ($refSub && $refSub->kegiatan_id) {
            $refKegiatan = Kegiatan::query()->find($refSub->kegiatan_id);
        }

        $programCode = implode('.', array_slice($parts, 0, 3));
        $program = Program::query()
            ->where('opd_id', $opdId)
            ->where('kode_rek', $programCode)
            ->where('document_type', $documentType)
            ->where('tahun', $tahun)
            ->first();

        if (!$program) {
            $program = Program::query()
                ->where('opd_id', $opdId)
                ->where('kode_rek', $programCode)
                ->first();
        }

        $payload = [
            'program_id' => $program?->id,
            'opd_id' => $opdId,
            'kepmen_id' => $refKegiatan?->kepmen_id ?? 1,
            'document_type' => $documentType,
            'kode_rek' => $kodeKegiatan,
            'nama_rincian' => (string) ($refKegiatan?->nama_rincian ?: ('Kegiatan ' . $kodeKegiatan . ' (otomatis)')),
            'tahun_awal' => $refKegiatan?->tahun_awal,
            'tahun_akhir' => $refKegiatan?->tahun_akhir,
            'target_t1' => $refKegiatan?->target_t1,
            'target_t2' => $refKegiatan?->target_t2,
            'target_t3' => $refKegiatan?->target_t3,
            'target_t4' => $refKegiatan?->target_t4,
            'target_t5' => $refKegiatan?->target_t5,
            'target_tahunan' => $refKegiatan?->target_tahunan,
            'tahun' => $tahun,
            'catatan_evaluasi' => $refKegiatan?->catatan_evaluasi,
        ];

        if ($dryRun) {
            $targetKegiatan = new Kegiatan($payload);
            $targetKegiatan->id = -1;
        } else {
            $targetKegiatan = Kegiatan::query()->create($payload);
        }

        $createdKegiatan++;
    }

    foreach ($subs as $sub) {
        $currentKegiatan = $sub->kegiatan_id ? Kegiatan::query()->find($sub->kegiatan_id) : null;
        if ($currentKegiatan && (int) $currentKegiatan->opd_id === $opdId && (int) $sub->kegiatan_id === (int) $targetKegiatan->id) {
            $alreadyOk++;
            continue;
        }

        if (!$dryRun) {
            $sub->kegiatan_id = $targetKegiatan->id;
            $sub->save();
        }

        $updatedSub++;

        if (count($sampleUpdated) < 20) {
            $sampleUpdated[] = "sub_id={$sub->id} opd_id={$opdId} kode_sub={$kodeSub} kegiatan_baru={$targetKegiatan->id}";
        }
    }
}

echo "Normalisasi relasi SubKegiatan->Kegiatan selesai.\n";
echo "- Mode: " . ($dryRun ? 'DRY RUN' : 'APPLY') . "\n";
echo "- Source file: {$filePath}\n";
echo "- Updated sub_kegiatan: {$updatedSub}\n";
echo "- Created kegiatan: {$createdKegiatan}\n";
echo "- Already OK: {$alreadyOk}\n";
echo "- Skipped no sub (target tidak ditemukan): {$skippedNoSub}\n";
echo "- Skipped invalid kode kegiatan: {$skippedNoKodeKegiatan}\n";

if (!empty($sampleUpdated)) {
    echo "\nContoh updated:\n";
    foreach ($sampleUpdated as $line) {
        echo "  - {$line}\n";
    }
}
