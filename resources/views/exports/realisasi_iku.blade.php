<html>
<head>
    <meta charset="utf-8" />
    <title>Realisasi IKU</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #444; padding: 6px; font-size: 12px; }
        th { background: #e6f4ea; }
    </style>
</head>
<body>
    <h3>{{ $viewTitle ?? 'Realisasi IKU' }}</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Indikator</th>
                <th>Satuan</th>
                <th>Target 2026</th>
                <th>Realisasi Tahun</th>
                <th>Realisasi Fisik</th>
                <th>Realisasi Keuangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['no'] }}</td>
                    <td>{{ $row['indikator'] }}</td>
                    <td>{{ $row['satuan'] }}</td>
                    <td>{{ $row['target_2026'] }}</td>
                    <td>{{ $row['realisasi_tahun'] }}</td>
                    <td>{{ $row['realisasi_fisik'] }}</td>
                    <td>{{ $row['realisasi_keuangan'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>