<?php

namespace Database\Seeders;

use App\Models\Iku;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class IkuSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = base_path('referensi/pusat/iku.json');

        if (!File::exists($filePath)) {
            return;
        }

        $rows = json_decode(File::get($filePath), true);

        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (empty($row['Indikator'])) {
                continue;
            }

            Iku::updateOrCreate(
                [
                    'indikator' => trim((string) $row['Indikator']),
                ],
                [
                    'satuan' => isset($row['Satuan']) ? trim((string) $row['Satuan']) : '-',
                    'capaian_2024' => $this->toText($row['Capaian_Tahun_2024'] ?? ''),
                    'target_2025' => $this->toText($row['target_2025'] ?? ''),
                    'target_2026' => $this->toText($row['target_2026'] ?? ''),
                    'target_2027' => $this->toText($row['target_2027'] ?? ''),
                    'target_2028' => $this->toText($row['target_2028'] ?? ''),
                    'target_2029' => $this->toText($row['target_2029'] ?? ''),
                    'target_2030' => $this->toText($row['target_2030'] ?? ''),
                ]
            );
        }
    }

    private function toText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}