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
        .right { text-align: right; }
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
                <th rowspan="2" style="width:130px;">{{ $entityHeaderLabel }}</th>
                <th rowspan="2" style="width:200px;">Program</th>
                <th rowspan="2" style="width:95px;">Pagu RPJMD ({{ $selectedYear ?? 2026 }})</th>
                <th rowspan="2" style="width:95px;">Pagu Renstra ({{ $selectedYear ?? 2026 }})</th>
                <th rowspan="2" style="width:95px;">Pagu RKPD/Renja ({{ $selectedYear ?? 2026 }})</th>
                <th colspan="2">Konsistensi RPJMD - Renstra</th>
                <th colspan="2">Konsistensi RPJMD - RKPD/Renja</th>
                <th colspan="2">Konsistensi Renstra - RKPD/Renja</th>
            </tr>
            <tr class="sub-head">
                <th style="width:95px;">Selisih Anggaran</th>
                <th style="width:70px;">Status</th>
                <th style="width:95px;">Selisih Anggaran</th>
                <th style="width:70px;">Status</th>
                <th style="width:95px;">Selisih Anggaran</th>
                <th style="width:70px;">Status</th>
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
                            'program_name' => '-',
                            'rpjmd_pagu' => 0,
                            'renstra_pagu' => 0,
                            'rkpd_pagu' => 0,
                            'diff_rpjmd_renstra' => 0,
                            'diff_rpjmd_rkpd' => 0,
                            'diff_renstra_rkpd' => 0,
                            'status_rpjmd_renstra' => '-',
                            'status_rpjmd_rkpd' => '-',
                            'status_renstra_rkpd' => '-',
                        ];
                    @endphp
                    <tr>
                        @if ($i === 0)
                            <td rowspan="{{ $lineCount }}" class="center">{{ $group['no'] }}</td>
                            <td rowspan="{{ $lineCount }}">{{ $group['entitas'] }}</td>
                        @endif

                        <td>{{ $line['program_name'] }}</td>
                        <td class="right">{{ number_format((int) $line['rpjmd_pagu'], 0, ',', '.') }}</td>
                        <td class="right">{{ number_format((int) $line['renstra_pagu'], 0, ',', '.') }}</td>
                        <td class="right">{{ number_format((int) $line['rkpd_pagu'], 0, ',', '.') }}</td>
                        <td class="right">{{ number_format((int) $line['diff_rpjmd_renstra'], 0, ',', '.') }}</td>
                        <td class="center">{{ $line['status_rpjmd_renstra'] }}</td>
                        <td class="right">{{ number_format((int) $line['diff_rpjmd_rkpd'], 0, ',', '.') }}</td>
                        <td class="center">{{ $line['status_rpjmd_rkpd'] }}</td>
                        <td class="right">{{ number_format((int) $line['diff_renstra_rkpd'], 0, ',', '.') }}</td>
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
