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
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #9ca3af; padding: 4px; vertical-align: top; word-wrap: break-word; }
        thead th { background: #d1fae5; text-align: center; font-weight: 700; }
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
                <th rowspan="2" style="width:220px;">{{ $entityHeaderLabel }}</th>
                <th rowspan="2" style="width:260px;">Program</th>
                <th colspan="2" style="width:280px;">RKPD/Renja (Tahun {{ $selectedYear ?? '-' }})</th>
                <th colspan="2" style="width:280px;">APBD (Tahun {{ $selectedYear ?? '-' }})</th>
                <th rowspan="2" style="width:120px;">Status Konsistensi RKPD/Renja - APBD</th>
            </tr>
            <tr>
                <th style="width:200px;">Indikator Program</th>
                <th style="width:80px;">Target</th>
                <th style="width:200px;">Indikator Program</th>
                <th style="width:80px;">Target</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $group)
                @php
                    $lines = $group['lines'] ?? [];
                    $lineCount = max(count($lines), 1);
                @endphp

                @for ($i = 0; $i < $lineCount; $i++)
                    @php $line = $lines[$i] ?? ['rkpd_name' => '', 'rkpd_target' => '', 'dpa_name' => '', 'dpa_target' => '', 'status' => '-']; @endphp
                    <tr>
                        <td class="center">{{ $group['no'] }}</td>
                        <td>{{ $group['entitas'] }}</td>
                        <td>{{ $group['program_name'] ?? '-' }}</td>
                        <td>
                            {{ $line['rkpd_name'] }}
                            <div style="color:#555; font-size:90%; margin-top:4px;">Target: {{ $line['rkpd_target'] !== '' && $line['rkpd_target'] !== null ? $line['rkpd_target'] : '-' }}</div>
                        </td>
                        <td class="center">{{ $line['rkpd_target'] !== '' && $line['rkpd_target'] !== null ? $line['rkpd_target'] : '-' }}</td>
                        <td>
                            {{ $line['dpa_name'] }}
                            <div style="color:#555; font-size:90%; margin-top:4px;">Target: {{ $line['dpa_target'] !== '' && $line['dpa_target'] !== null ? $line['dpa_target'] : '-' }}</div>
                        </td>
                        <td class="center">{{ $line['dpa_target'] !== '' && $line['dpa_target'] !== null ? $line['dpa_target'] : '-' }}</td>
                        <td class="center">{{ $line['status'] }}</td>
                    </tr>
                @endfor
            @empty
                <tr>
                    <td colspan="8" class="center">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>