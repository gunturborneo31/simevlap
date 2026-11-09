<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AllOpdKertasKerjaExport implements WithMultipleSheets
{
    /**
     * @var array<int, array{title:string, rows:array, styles:array, cols:array, merges:array, rowHeights:array}>
     */
    protected array $sheetsPayload;

    /**
     * @param array<int, array{title:string, rows:array, styles:array, cols:array, merges:array, rowHeights:array}> $sheetsPayload
     */
    public function __construct(array $sheetsPayload)
    {
        $this->sheetsPayload = $sheetsPayload;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->sheetsPayload as $payload) {
            $sheets[] = new RealisasiArrayExport(
                $payload['rows'] ?? [],
                $payload['styles'] ?? [],
                $payload['cols'] ?? [],
                $payload['merges'] ?? [],
                $payload['rowHeights'] ?? [],
                (string) ($payload['title'] ?? 'OPD')
            );
        }

        return $sheets;
    }
}
