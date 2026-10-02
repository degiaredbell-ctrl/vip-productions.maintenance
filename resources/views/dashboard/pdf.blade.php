<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Mesin & Status PM {{ $year }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { color: #666; font-size: 11px; margin-bottom: 10px; }
        .summary { margin-bottom: 10px; }
        .summary span { display: inline-block; margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #c8c8c8; padding: 4px 6px; text-align: left; }
        th { background: #f0f0f0; }
        .muted { color: #888; text-align: center; }
        .footer { color: #888; margin-top: 10px; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Daftar Mesin &amp; Status Preventive Maintenance</h1>
    <div class="meta">
        PT Verra Inter Pangan · Tahun {{ $year }} · Periode {{ $period->label() }}
        @if($status !== 'all') · Status: {{ \App\Enums\PmDisplayStatus::tryFrom($status)?->label() ?? $status }} @endif
        @if($subCategory !== 'all') · Area: {{ $subCategory }} @endif
        @if($search !== '') · Pencarian: "{{ $search }}" @endif
    </div>

    <div class="summary">
        <span><b>Total:</b> {{ $stats['total'] }}</span>
        <span><b>Selesai:</b> {{ $stats['done'] }}</span>
        <span><b>Sedang Approval:</b> {{ $stats['progress'] }}</span>
        <span><b>Belum:</b> {{ $stats['todo'] }}</span>
        <span><b>Perlu Revisi:</b> {{ $stats['issue'] }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode</th>
                <th>Nama Mesin</th>
                <th>Lokasi</th>
                <th>Area</th>
                <th>Tipe</th>
                <th>Minggu</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($machines as $index => $machine)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $machine['code'] }}</td>
                    <td>{{ $machine['name'] }}</td>
                    <td>{{ $machine['location'] }}</td>
                    <td>{{ $machine['sub_category'] }}</td>
                    <td>{{ $machine['type_label'] }}</td>
                    <td>{{ $machine['week_group'] }}</td>
                    <td>{{ $machine['status_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">Tidak ada mesin yang cocok.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Dicetak: {{ $generatedAt }}</p>
</body>
</html>
