<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class RealisasiArrayExport implements FromArray, WithEvents
{
    protected array $rows;
    protected array $styles;
    protected array $cols;
    protected array $merges;
    protected array $rowHeights;

    public function __construct(array $rows = [], array $styles = [], array $cols = [], array $merges = [], array $rowHeights = [])
    {
        $this->rows = $rows;
        $this->styles = $styles;
        $this->cols = $cols;
        $this->merges = $merges;
        $this->rowHeights = $rowHeights;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Apply per-cell styles captured from frontend
                foreach ($this->styles as $addr => $st) {
                    // address key expected as "r,c"
                    [$r, $c] = array_map('intval', explode(',', (string) $addr));
                    $cell = Coordinate::stringFromColumnIndex($c + 1) . ($r + 1);

                    $styleArray = [];
                    // header override
                    if (!empty($st['isHeader'])) {
                        $styleArray['fill'] = [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => ltrim('D1D5DB', '#')],
                        ];
                        $styleArray['font'] = ['bold' => true, 'color' => ['rgb' => '000000']];
                    } else {
                        if (!empty($st['bg'])) {
                            $styleArray['fill'] = [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => ltrim(str_replace('#', '', (string)$st['bg']), '#')],
                            ];
                        }
                        if (!empty($st['color'])) {
                            $styleArray['font'] = ['color' => ['rgb' => ltrim(str_replace('#', '', (string)$st['color']), '#')]];
                        }
                    }

                    // common alignment and wrap
                    $styleArray['alignment'] = ['wrapText' => true, 'vertical' => 'center', 'horizontal' => (!empty($st['align']) ? $st['align'] : 'center')];

                    // borders default thin
                    $styleArray['borders'] = [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                    ];

                    if (!empty($styleArray)) {
                        $sheet->getStyle($cell)->applyFromArray($styleArray);
                    }
                }

                // set column widths if provided (expects numeric widths)
                foreach ($this->cols as $i => $w) {
                    $col = Coordinate::stringFromColumnIndex($i + 1);
                    try {
                        $sheet->getColumnDimension($col)->setWidth((float)$w ?: 15);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }

                // apply merges if provided (expects objects {s:{r,c}, e:{r,c}})
                if (!empty($this->merges) && is_array($this->merges)) {
                    foreach ($this->merges as $m) {
                        try {
                            $start = Coordinate::stringFromColumnIndex(($m['s']['c'] ?? 0) + 1) . (($m['s']['r'] ?? 0) + 1);
                            $end = Coordinate::stringFromColumnIndex(($m['e']['c'] ?? 0) + 1) . (($m['e']['r'] ?? 0) + 1);
                            $sheet->mergeCells(sprintf('%s:%s', $start, $end));
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                }

                // apply row heights if provided (array index => height in points)
                if (!empty($this->rowHeights) && is_array($this->rowHeights)) {
                    foreach ($this->rowHeights as $r => $h) {
                        try {
                            $sheet->getRowDimension((int)$r + 1)->setRowHeight((float)$h);
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                }

                // ensure all occupied cells wrap text
                $lastRow = max(1, count($this->rows));
                $lastCol = max(1, count($this->rows[0] ?? []));
                $range = 'A1:' . Coordinate::stringFromColumnIndex($lastCol) . $lastRow;
                $sheet->getStyle($range)->getAlignment()->setWrapText(true)->setVertical('center');
            },
        ];
    }
}
