@php
    $logo = is_file(public_path('img/logo.png')) ? 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('img/logo.png'))) : null;
    $s = $report['summary'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 17px; margin: 0; }
        h3 { font-size: 13px; margin: 18px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
        th { background: #f0f0f0; }
        .num { text-align: center; }
        .meta { color: #666; }
        .doc-header { display: table; width: 100%; margin-bottom: 10px; }
        .doc-header .logo-box { display: table-cell; width: 70px; vertical-align: middle; }
        .doc-header .logo-box img { width: 60px; }
        .doc-header .title-box { display: table-cell; vertical-align: middle; padding-left: 10px; }
        .kpi td { text-align: center; padding: 8px 4px; }
        .kpi b { display: block; font-size: 16px; }
        .bad { color: #B93A2E; }
        .good { color: #2E7D4F; }
    </style>
</head>
<body>
    <div class="doc-header">
        @if ($logo)<div class="logo-box"><img src="{{ $logo }}" alt="Logo"></div>@endif
        <div class="title-box">
            <h1>{{ $report['title'] }}</h1>
            <div class="meta">Rentang: {{ $report['rangeLabel'] }} · Periode PM berjalan: {{ $report['periodLabel'] }}@if (! empty($report['currentWeek'])) (Minggu ke-{{ $report['currentWeek'] }})@endif</div>
            <div class="meta">Dibuat: {{ $report['generatedAt'] }} WIB</div>
        </div>
    </div>

    <h3>Ringkasan Pencapaian</h3>
    <table class="kpi">
        <tr>
            <td><b>{{ $s['total'] }}</b>Terjadwal</td>
            <td><b class="good">{{ $s['done'] }}</b>Selesai</td>
            <td><b>{{ $s['progress'] }}</b>Dalam Approval</td>
            <td><b>{{ $s['issue'] }}</b>Revisi</td>
            <td><b>{{ $s['todo'] }}</b>Belum</td>
            <td><b class="bad">{{ $s['overdue'] }}</b>Terlambat</td>
            <td><b>{{ $s['percent'] }}%</b>Pencapaian</td>
        </tr>
    </table>

    <h3>Per Jenis Perangkat</h3>
    <table>
        <thead><tr><th></th><th class="num">Terjadwal</th><th class="num">Selesai</th><th class="num">Approval</th><th class="num">Revisi</th><th class="num">Belum</th><th class="num">Terlambat</th><th class="num">Pencapaian</th></tr></thead>
        <tbody>
        @foreach (['machine' => 'Mesin', 'utility' => 'Utility'] as $key => $label)
            @php $r = $report['status'][$key]; @endphp
            <tr>
                <td><b>{{ $label }}</b></td>
                <td class="num">{{ $r['total'] }}</td><td class="num">{{ $r['done'] }}</td><td class="num">{{ $r['progress'] }}</td>
                <td class="num">{{ $r['issue'] }}</td><td class="num">{{ $r['todo'] }}</td><td class="num">{{ $r['overdue'] }}</td><td class="num">{{ $r['percent'] }}%</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h3>Aktivitas &amp; Antrean</h3>
    <table>
        <tr><td>Checklist disubmit teknisi (rentang ini)</td><td class="num">{{ $report['activity']['submitted'] }}</td><td>Menunggu User PIC</td><td class="num">{{ $report['pending']['pic'] }}</td></tr>
        <tr><td>PM selesai disetujui Atasan (rentang ini)</td><td class="num">{{ $report['activity']['approved'] }}</td><td>Menunggu Atasan</td><td class="num">{{ $report['pending']['supervisor'] }}</td></tr>
        <tr><td>PM dikembalikan untuk revisi (rentang ini)</td><td class="num">{{ $report['activity']['rejected'] }}</td><td>Perlu revisi teknisi</td><td class="num">{{ $report['pending']['revision'] }}</td></tr>
    </table>

    <h3>Daftar Perangkat Terlambat ({{ count($report['overdueList']) }})</h3>
    @if (count($report['overdueList']) > 0)
        <table>
            <thead><tr><th class="num">No</th><th>Kode</th><th>Perangkat</th><th>Jenis</th><th>Lokasi</th><th class="num">Minggu</th><th>Batas</th><th class="num">Terlambat</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($report['overdueList'] as $i => $item)
                <tr>
                    <td class="num">{{ $i + 1 }}</td><td>{{ $item['code'] }}</td><td>{{ $item['name'] }}</td><td>{{ $item['type_label'] }}</td>
                    <td>{{ $item['location'] ?: '-' }}</td><td class="num">W{{ $item['week'] }}</td><td>{{ $item['deadline'] }}</td>
                    <td class="num bad">{{ $item['days_late'] }} hari</td><td>{{ $item['status'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p>Tidak ada perangkat terlambat.</p>
    @endif
</body>
</html>
