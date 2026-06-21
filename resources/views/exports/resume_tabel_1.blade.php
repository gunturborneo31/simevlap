<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $viewTitle }} - {{ $tableLabel }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        h1, h2, p {
            margin: 0;
            padding: 0;
        }

        .header {
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 16px;
            margin-bottom: 2px;
        }

        .header p {
            font-size: 11px;
            color: #334155;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #9ca3af;
            padding: 4px 5px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        thead th {
            background: #d1fae5;
            text-align: center;
            font-weight: 700;
        }

        .sub-head th {
            background: #ecfdf5;
            font-weight: 700;
        }

        .index-head th {
            background: #d1fae5;
            font-size: 10px;
            font-weight: 600;
            color: #065f46;
        }

        .col-no {
            width: 40px;
            text-align: center;
        }

        .col-entity {
            width: 190px;
            text-align: left;
        }

        .text-center {
            text-align: center;
        }

        .meta {
            margin-bottom: 8px;
        }

        .meta span {
            display: inline-block;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $viewTitle }} - {{ $tableLabel }}</h1>
        <p>Export {{ strtoupper($exportFormat ?? 'PDF') }} | Tahun {{ $selectedYear ?? '-' }}</p>
    </div>

    <div class="meta">
        <span><strong>Basis:</strong> {{ $entityHeaderLabel }}</span>
        <span><strong>Metrik:</strong> {{ $metricLabel }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" class="col-no">No</th>
                <th rowspan="2" class="col-entity">{{ $entityHeaderLabel }}</th>
                <th rowspan="2">RPJMD (2026-2030) - Jumlah {{ $metricLabel }}</th>
                <th rowspan="2">Renstra (Tahun 2026) - Jumlah {{ $metricLabel }}</th>
                <th rowspan="2">RKPD/Renja (Tahun 2026) - Jumlah {{ $metricLabel }}</th>
                <th colspan="2">Konsistensi RPJMD - Renstra</th>
                <th colspan="2">Konsistensi RPJMD - RKPD/Renja</th>
                <th colspan="2">Status Konsistensi Renstra - RKPD/Renja</th>
            </tr>
            <tr class="sub-head">
                <th>Jumlah {{ $metricLabel }} Yang Sama</th>
                <th>Jumlah {{ $metricLabel }} Yang Tidak Sama</th>
                <th>Jumlah {{ $metricLabel }} Yang Sama</th>
                <th>Jumlah {{ $metricLabel }} Yang Tidak Sama</th>
                <th>Jumlah {{ $metricLabel }} Yang Sama</th>
                <th>Jumlah {{ $metricLabel }} Yang Tidak Sama</th>
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
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="text-center">{{ $row['no'] }}</td>
                    <td>{{ $row['entitas'] }}</td>
                    <td class="text-center">{{ $row['rpjmd_total'] }}</td>
                    <td class="text-center">{{ $row['renstra_total'] }}</td>
                    <td class="text-center">{{ $row['rkpd_total'] }}</td>
                    <td class="text-center">{{ $row['same_rpjmd_renstra'] }}</td>
                    <td class="text-center">{{ $row['diff_rpjmd_renstra'] }}</td>
                    <td class="text-center">{{ $row['same_rpjmd_rkpd'] }}</td>
                    <td class="text-center">{{ $row['diff_rpjmd_rkpd'] }}</td>
                    <td class="text-center">{{ $row['same_renstra_rkpd'] }}</td>
                    <td class="text-center">{{ $row['diff_renstra_rkpd'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
