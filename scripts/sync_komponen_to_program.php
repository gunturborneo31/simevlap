<?php
/**
 * Sync KomponenAnggaran (document_type=renstra, jenis=program)
 * into `program` table as Program rows (idempotent).
 *
 * Usage:
 * php scripts/sync_komponen_to_program.php [--dry-run] [--force]
 *
 * --dry-run : do not write to database, only report actions
 * --force   : overwrite existing Program fields (name, pagu)
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KomponenAnggaran;
use App\Models\Program;
use Illuminate\Support\Str;

$opts = getopt('', ['dry-run', 'force']);
$dryRun = array_key_exists('dry-run', $opts);
$force = array_key_exists('force', $opts);

echo "Sync KomponenAnggaran -> program (Renstra programs)\n";
echo "Dry run: " . ($dryRun ? 'yes' : 'no') . ", Force: " . ($force ? 'yes' : 'no') . "\n";

$query = KomponenAnggaran::where('document_type', 'renstra')->where('jenis', 'program')->orderBy('id');
$total = $query->count();
echo "Found {$total} komponen_anggaran (renstra / program)\n";

$created = 0;
$updated = 0;
$skipped = 0;

foreach ($query->cursor() as $k) {
    $kode = trim((string) ($k->kode ?? ''));
    $opdId = $k->opd_id ?? null;
    if ($kode === '') {
        echo "- Skipping id={$k->id} (empty kode)\n";
        $skipped++;
        continue;
    }

    $existing = Program::where('kode_rek', $kode)->where('opd_id', $opdId)->first();

    if ($existing) {
        $changes = [];
        if (!$existing->is_prioritas) {
            $changes['is_prioritas'] = 1;
        }
        if (($existing->document_type ?? '') !== 'renstra') {
            $changes['document_type'] = 'renstra';
        }
        if ($force) {
            $changes['nama_rincian'] = $k->nama_komponen;
            if (property_exists($k, 'pagu')) $changes['pagu'] = $k->pagu ?? 0;
        }

        if (!empty($changes)) {
            echo "- Update Program opd={$opdId} kode={$kode} id={$existing->id} -> ";
            echo json_encode($changes, JSON_UNESCAPED_UNICODE) . "\n";
            if (!$dryRun) {
                $existing->update($changes);
            }
            $updated++;
        } else {
            $skipped++;
        }
        continue;
    }

    // create new Program
    $data = [
        'opd_id' => $opdId,
        'kepmen_id' => null,
        'document_type' => 'renstra',
        'jenis_program' => 'program',
        'kode_rek' => $kode,
        'nama_rincian' => $k->nama_komponen,
        'deskripsi' => null,
        'pagu' => $k->pagu ?? 0,
        'tahun' => $k->tahun ?? null,
        'is_prioritas' => 1,
    ];

    echo "- Create Program opd={$opdId} kode={$kode} -> ";
    echo json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    if (!$dryRun) {
        Program::create($data);
    }
    $created++;
}

echo "\nResult: created={$created}, updated={$updated}, skipped={$skipped}, total={$total}\n";

return 0;
