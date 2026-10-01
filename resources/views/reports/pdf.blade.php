<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan PM {{ $year }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f0f0f0; }
        h1 { font-size: 18px; }
        .meta { color: #666; margin-bottom: 16px; }
    </style>
</head>
<body>
    <h1>Laporan Preventive Maintenance</h1>
    <div class="meta">Tahun: {{ $year }} @if($period) · Periode: {{ $period }} @endif</div>

    <h3>Kepatuhan per Periode</h3>
    <table>
        <thead><tr><th>Periode</th><th>Total</th><th>Selesai</th><th>%</th></tr></thead>
        <tbody>
            @foreach($compliance as $c)
            <tr>
                <td>{{ $c['label'] }}</td>
                <td>{{ $c['total'] }}</td>
                <td>{{ $c['completed'] }}</td>
                <td>{{ $c['percentage'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Ringkasan Tindakan</h3>
    <table>
        <tbody>
            <tr><td>Bersihkan</td><td>{{ $actions['clean'] }}</td></tr>
            <tr><td>Perbaiki</td><td>{{ $actions['repair'] }}</td></tr>
            <tr><td>Lumasi</td><td>{{ $actions['lubricate'] }}</td></tr>
            <tr><td>Ganti</td><td>{{ $actions['replace'] }}</td></tr>
        </tbody>
    </table>

    <h3>Komponen Paling Sering Diganti</h3>
    <table>
        <thead><tr><th>Komponen</th><th>Jumlah</th></tr></thead>
        <tbody>
            @foreach($topParts as $p)
            <tr><td>{{ $p['item_name'] }}</td><td>{{ $p['total'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
