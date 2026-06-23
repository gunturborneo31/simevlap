<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Indikator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class DedupeIndikator extends Command
{
    protected $signature = 'indikator:dedupe {--document_type=*} {--opd_id=*} {--dry-run}';

    protected $description = 'Deduplicate indikator by normalized text and merge indikatorables relations';

    public function handle(): int
    {
        $this->info('Starting indikator deduplication...');

        $documentTypes = array_filter((array) $this->option('document_type'));
        $opdIds = array_filter((array) $this->option('opd_id'));
        $dryRun = (bool) $this->option('dry-run');

        $query = Indikator::query();
        if (!empty($documentTypes)) $query->whereIn('document_type', $documentTypes);
        if (!empty($opdIds)) $query->whereIn('opd_id', $opdIds);

        $indikators = $query->get()->groupBy(function (Indikator $i) {
            return ($i->opd_id ?? 'null') . '|' . ($i->document_type ?? 'null');
        });

        $totalMerged = 0;

        foreach ($indikators as $groupKey => $group) {
            $this->line("Processing group: {$groupKey} (" . $group->count() . ")");

            $map = [];
            foreach ($group as $item) {
                $norm = $this->normalize($item->uraian ?? '');
                $map[$norm][] = $item;
            }

            foreach ($map as $norm => $items) {
                if (count($items) <= 1) continue;

                // choose master: prefer longest uraian (likely properly spaced)
                usort($items, function ($a, $b) {
                    return strlen((string) $b->uraian) <=> strlen((string) $a->uraian);
                });

                $master = array_shift($items);
                $dups = $items;

                $this->line("Found duplicate set (normalized='{$norm}'), master id={$master->id}, dup_count=" . count($dups));

                foreach ($dups as $dup) {
                    if ($dryRun) {
                        $this->line("[dry-run] would reassign indikatorables from {$dup->id} -> {$master->id} and delete id {$dup->id}");
                        continue;
                    }

                    DB::transaction(function () use ($dup, $master, &$totalMerged) {
                        DB::table('indikatorables')->where('indikator_id', $dup->id)->update(['indikator_id' => $master->id]);
                        $dup->delete();
                        $totalMerged++;
                    });
                }
            }
        }

        $this->info('Done. Merged duplicates: ' . $totalMerged);

        return 0;
    }

    private function normalize(?string $text): string
    {
        $text = (string) $text;
        $text = Str::ascii($text);
        $text = strtolower($text);
        // remove all non-alphanumeric characters
        $text = preg_replace('/[^a-z0-9]+/', '', $text);
        return $text;
    }
}
