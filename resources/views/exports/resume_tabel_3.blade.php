<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $viewTitle }} - {{ $tableLabel }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2937; }
        h1, p { margin: 0; }
        .header { margin-bottom: 8px; }
        .header h1 { font-size: 14px; }
        .header p { font-size: 10px; color: #334155; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #9ca3af; padding: 4px; vertical-align: top; word-wrap: break-word; }
        thead th { background: #d1fae5; text-align: center; font-weight: 700; }
        .sub-head th { background: #ecfdf5; }
        .index-head th { background: #d1fae5; font-size: 9px; color: #065f46; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $viewTitle }} - {{ $tableLabel }}</h1>
        <p>Export {{ strtoupper($exportFormat ?? 'PDF') }} | Tahun {{ $selectedYear ?? '-' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width:34px;">No</th>
                <th rowspan="2" style="width:120px;">{{ $entityHeaderLabel }}</th>
                <th rowspan="2" style="width:170px;">Program</th>
                <th colspan="2">RPJMD - Tahun {{ $selectedYear ?? 2026 }}</th>
                <th colspan="2">Renstra - Tahun {{ $selectedYear ?? 2026 }}</th>
                <th colspan="2">RKPD - Tahun {{ $selectedYear ?? 2026 }}</th>
                <th rowspan="2" style="width:100px;">Status RPJMD - Renstra</th>
                <th rowspan="2" style="width:100px;">Status RPJMD - RKPD/Renja</th>
                <th rowspan="2" style="width:100px;">Status Renstra - RKPD/Renja</th>
            </tr>
            <tr class="sub-head">
                <th style="width:170px;">Indikator</th>
                <th style="width:60px;">Target</th>
                <th style="width:170px;">Indikator</th>
                <th style="width:60px;">Target</th>
                <th style="width:170px;">Indikator</th>
                <th style="width:60px;">Target</th>
            </tr>
            <tr class="index-head">
                <th>(1)</th>
                <th>(2)</th>
                <th>(3)</th>
                <th>(4)</th>
                <th>(5)</th>
                <th>(6)</th>
                <th>(7)</th>
                <th>(8)</th>
                <th>(9)</th>
                <th>(10)</th>
                <th>(11)</th>
                <th>(12)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $group)
                @php
                    $lines = $group['lines'] ?? [];
                    $lineCount = max(count($lines), 1);
                @endphp

                @for ($i = 0; $i < $lineCount; $i++)
                    @php
                        $line = $lines[$i] ?? [
                            'rpjmd_name' => '', 'rpjmd_target' => '',
                            'renstra_name' => '', 'renstra_target' => '',
                            'rkpd_name' => '', 'rkpd_target' => '',
                            'status_rpjmd_renstra' => '-',
                            'status_rpjmd_rkpd' => '-',
                            'status_renstra_rkpd' => '-',
                        ];
                    @endphp
                    <tr>
                        <td class="center">{{ $group['no'] }}</td>
                        <td>{{ $group['entitas'] }}</td>
                        <td>{{ $group['program_name'] }}</td>

                        <td>{{ $line['rpjmd_name'] }}</td>
                        <td class="center">{{ $line['rpjmd_target'] }}</td>
                        <td>{{ $line['renstra_name'] }}</td>
                        <td class="center">{{ $line['renstra_target'] }}</td>
                        <td>{{ $line['rkpd_name'] }}</td>
                        <td class="center">{{ $line['rkpd_target'] }}</td>
                        <td class="center">{{ $line['status_rpjmd_renstra'] }}</td>
                        <td class="center">{{ $line['status_rpjmd_rkpd'] }}</td>
                        <td class="center">{{ $line['status_renstra_rkpd'] }}</td>
                    </tr>
                @endfor
            @empty
                <tr>
                    <td colspan="12" class="center">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
