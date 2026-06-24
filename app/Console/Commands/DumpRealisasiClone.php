<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class DumpRealisasiClone extends Command
{
    protected $signature = 'dump:realisasi-clone {--opd_id=} {--tahun=}';
    protected $description = 'Dump RealisasiCloneService payload to stdout for debugging';

    public function handle()
    {
        $opdId = $this->option('opd_id') ? (int)$this->option('opd_id') : null;
        $tahun = $this->option('tahun') ? (int)$this->option('tahun') : null;

        $service = app(\App\Services\RealisasiCloneService::class);
        $payload = $service->buildPayloadForOpd($opdId, $tahun, 'realisasi', 'dpa', false);

        $summary = [
            'opd_id' => $opdId,
            'tahun' => $tahun,
            'keys' => array_keys($payload),
            'opds_count' => isset($payload['opds']) ? count($payload['opds']) : 0,
            'data_count' => is_array($payload['data']) ? count($payload['data']) : (method_exists($payload['data'], 'count') ? $payload['data']->count() : 0),
        ];

        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }
}
