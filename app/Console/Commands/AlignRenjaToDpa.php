<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Indikator;
use Illuminate\Support\Str;

class AlignRenjaToDpa extends Command
{
    protected $signature = 'indikator:align-renja-dpa {--opd_id=*} {--threshold=85} {--dry-run}';

    protected $description = 'Align RENJA indikator uraian to matching DPA indikator when texts are similar';

    public function handle(): int
    {
        $opdIds = array_filter((array) $this->option('opd_id'));
        $threshold = (int) $this->option('threshold');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Running align-renja-dpa (threshold={$threshold}%)" . ($dryRun ? ' [dry-run]' : ''));

        $query = Indikator::query()->where('document_type', 'renja');
        if (!empty($opdIds)) $query->whereIn('opd_id', $opdIds);

        $renjaList = $query->get();
        $this->info('RENJA indicators to check: ' . $renjaList->count());

        // preload DPA indicators grouped by opd
        $dpaQuery = Indikator::query()->where('document_type', 'dpa');
        if (!empty($opdIds)) $dpaQuery->whereIn('opd_id', $opdIds);
        $dpaList = $dpaQuery->get()->groupBy('opd_id');

        $updated = 0;

        foreach ($renjaList as $renja) {
            $opdId = $renja->opd_id;
            $candidates = $dpaList->get($opdId, collect());
            if ($candidates->isEmpty()) continue;

            $best = null;
            $bestScore = 0;

            $a = (string) $renja->uraian;
            foreach ($candidates as $dpa) {
                $b = (string) $dpa->uraian;
                // compute percent similarity using similar_text
                similar_text($this->normalize($a), $this->normalize($b), $percent);
                if ($percent > $bestScore) {
                    $bestScore = $percent;
                    $best = $dpa;
                }
            }

            if ($best && $bestScore >= $threshold) {
                if ($this->normalize($a) === $this->normalize((string)$best->uraian)) {
                    // already normalized same
                    continue;
                }

                $this->line("Match: RENJA id={$renja->id} -> DPA id={$best->id} (score={$bestScore}%)");
                $this->line("  RENJA uraian: {$renja->uraian}");
                $this->line("  DPA  uraian: {$best->uraian}");

                if (! $dryRun) {
                    $renja->uraian = $best->uraian;
                    $renja->save();
                    $updated++;
                }
            }
        }

        $this->info('Done. RENJA uraian updated: ' . $updated);
        return 0;
    }

    private function normalize(string $text): string
    {
        $text = Str::ascii($text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]+/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
