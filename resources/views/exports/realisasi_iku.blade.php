<html>
<head>
    <meta charset="utf-8" />
    <title>Realisasi IKU</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #444; padding: 6px; font-size: 12px; }
        th { background: #d9d9d9; text-align: center; font-weight: bold; }
        .section-row { background: #ffc000; font-weight: bold; }
        .data-row:nth-child(even) { background: #f9f9f9; }
    </style>
</head>
<body>
    <h3>{{ $viewTitle ?? 'Realisasi IKU' }} - Tahun {{ $selectedYear ?? '' }}</h3>
    <table>
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>Indikator</th>
                <th style="width:120px">Target RKPD {{ $selectedYear ?? '' }}</th>
                <th style="width:130px">Realisasi Tahun 2025</th>
                <th style="width:130px">Capaian Tahun {{ $selectedYear ?? '' }} (%)</th>
                <th style="width:140px">Faktor Pendorong</th>
                <th style="width:140px">Faktor Penghambat</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                @if(!empty($row['is_section']))
                    <tr class="section-row">
                        <td style="text-align:center">{{ $row['section_no'] ?? '' }}</td>
                        <td colspan="6">{{ $row['label'] ?? '' }}</td>
                    </tr>
                @else
                    <tr class="data-row">
                        <td style="text-align:center">{{ $row['no'] ?? '' }}</td>
                        <td>{{ $row['indikator'] ?? '-' }}</td>
                        <td style="text-align:center">{{ $row['target_rkpd'] ?? '-' }}</td>
                        <td style="text-align:center">{{ $row['realisasi'] ?? '-' }}</td>
                        <td style="text-align:center">{{ isset($row['capaian']) && $row['capaian'] !== null ? number_format($row['capaian'], 2).'%' : '-' }}</td>
                        <td>{{ $row['faktor_pendorong'] ?? '-' }}</td>
                        <td>{{ $row['faktor_penghambat'] ?? '-' }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>