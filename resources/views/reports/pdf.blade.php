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
        h1 { font-size: 18px; margin: 0; }
        .meta { color: #666; margin-bottom: 16px; }
        .num { text-align: center; }
        /* Header dokumen: logo di kiri, judul di kanan. DomPDF hanya andal
           untuk layout berbasis tabel, jadi tidak memakai flexbox. */
        .doc-header { display: table; width: 100%; margin-bottom: 4px; }
        .doc-header .logo-box { display: table-cell; width: 76px; vertical-align: middle; }
        .doc-header .logo-box img { width: 64px; }
        .doc-header .title-box { display: table-cell; vertical-align: middle; padding-left: 12px; }
        .printed-at { color: #666; font-size: 11px; margin-bottom: 14px; }
        .muted { color: #888; }
    </style>
</head>
<body>
    <div class="doc-header">
        @if($logoDataUri)
            <div class="logo-box"><img src="{{ $logoDataUri }}" alt="Logo"></div>
        @endif
        <div class="title-box">
            <h1>Laporan Preventive Maintenance</h1>
            <div class="meta">Tahun: {{ $year }} @if($period) · Periode: {{ $period }} @endif</div>
        </div>
    </div>
    <div class="printed-at">Dicetak: {{ $generatedAt->format('d/m/Y H:i:s') }}</div>

    <h3>Kepatuhan per Periode</h3>
    <table>
        <thead><tr><th>Periode</th><th class="num">Total</th><th class="num">Selesai</th><th class="num">Sedang Approval</th><th class="num">%</th></tr></thead>
        <tbody>
            @foreach($compliance as $c)
            <tr>
                <td>{{ $c['label'] }}</td>
                <td class="num">{{ $c['total'] }}</td>
                <td class="num">{{ $c['completed'] }}</td>
                <td class="num">{{ $c['approving'] ?? 0 }}</td>
                <td class="num">{{ $c['percentage'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Ringkasan Tindakan</h3>
    <table>
        <tbody>
            <tr><td>Bersihkan</td><td class="num">{{ $actions['clean'] }}</td></tr>
            <tr><td>Perbaiki</td><td class="num">{{ $actions['repair'] }}</td></tr>
            <tr><td>Lumasi</td><td class="num">{{ $actions['lubricate'] }}</td></tr>
            <tr><td>Perlu Pergantian</td><td class="num">{{ $actions['replace'] }}</td></tr>
        </tbody>
    </table>

    <h3>Komponen Paling Sering Diganti</h3>
    <table>
        <thead><tr><th>Komponen</th><th class="num">Jumlah</th></tr></thead>
        <tbody>
            @foreach($topParts as $p)
            <tr><td>{{ $p['item_name'] }}</td><td class="num">{{ $p['total'] }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h3>Detail Komponen Diganti</h3>
    @if(count($replacedParts) > 0)
    <table>
        <thead>
            <tr>
                <th class="num">No</th>
                <th>Mesin</th>
                <th>Lokasi</th>
                <th>Area</th>
                <th>Komponen</th>
                <th>Spesifikasi</th>
                <th class="num">Jumlah</th>
                <th>Periode</th>
            </tr>
        </thead>
        <tbody>
            @foreach($replacedParts as $i => $part)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td>{{ $part['machine_code'] }} — {{ $part['machine_name'] }}</td>
                <td>{{ $part['machine_location'] ?: '-' }}</td>
                <td>{{ $part['machine_area'] ?: '-' }}</td>
                <td>{{ $part['item_name'] }}</td>
                <td>{{ $part['spec'] ?: '-' }}</td>
                <td class="num">{{ $part['total'] }}</td>
                <td>{{ $part['period_label'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="muted">Tidak ada komponen yang diganti pada periode ini.</p>
    @endif
</body>
</html>
