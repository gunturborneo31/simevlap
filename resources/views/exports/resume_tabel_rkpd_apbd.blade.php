<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $viewTitle }} - {{ $tableLabel }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1f2937; }
        table { width:100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #9ca3af; padding: 4px 5px; vertical-align: middle; word-wrap: break-word; }
        thead th { background: #d1fae5; text-align:center; font-weight:700; }
        .sub-head th { background: #ecfdf5; font-weight:700; }
        .col-no { width: 40px; text-align:center; }
        .col-entity { width: 220px; text-align:left; }
        .text-center { text-align:center; }
    </style>
</head>
<body>
    <h1>{{ $viewTitle }} - {{ $tableLabel }}</h1>
    <p>Export {{ strtoupper($exportFormat ?? 'PDF') }} | Tahun {{ $selectedYear ?? '-' }}</p>
    <p><strong>Basis:</strong> {{ $entityHeaderLabel }} | <strong>Metrik:</strong> {{ $metricLabel }}</p>

    <table>
        <thead>
            <tr>
                <th rowspan="2" class="col-no">No</th>
                <th rowspan="2" class="col-entity">{{ $entityHeaderLabel }}</th>
                <th rowspan="1">RKPD/Renja (Tahun {{ $selectedYear ?? '-' }})</th>
                <th rowspan="1">APBD (Tahun {{ $selectedYear ?? '-' }})</th>
                <th colspan="2">Konsistensi RKPD/Renja - APBD</th>
            </tr>
            <tr class="sub-head">
                <th>Jumlah {{ $metricLabel }}</th>
                <th>Jumlah {{ $metricLabel }}</th>
                <th>Jumlah {{ $metricLabel }} Yang Sama</th>
                <th>Jumlah {{ $metricLabel }} Yang Tidak Sama</th>
            </tr>
            <tr class="sub-head">
                <th>(1)</th>
                <th>(2)</th>
                <th>(3)</th>
                <th>(4)</th>
                <th>(5)</th>
                <th>(6)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="text-center">{{ $row['no'] }}</td>
                    <td>{{ $row['entitas'] }}</td>
                    <td class="text-center">{{ $row['rkpd_total'] }}</td>
                    <td class="text-center">{{ $row['dpa_total'] }}</td>
                    <td class="text-center">{{ $row['same_rkpd_dpa'] }}</td>
                    <td class="text-center">{{ $row['diff_rkpd_dpa'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>