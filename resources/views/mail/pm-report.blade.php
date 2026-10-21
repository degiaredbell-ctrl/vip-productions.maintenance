<x-mail::message>
# {{ $report['title'] }}

**Rentang:** {{ $report['rangeLabel'] }}<br>
**Periode PM berjalan:** {{ $report['periodLabel'] }}

## Status PM periode berjalan

<x-mail::table>
| | Total | Selesai | Approval | Belum | Revisi | Progres |
|:--|--:|--:|--:|--:|--:|--:|
@foreach (['machine' => 'Mesin', 'utility' => 'Utility'] as $key => $label)
| **{{ $label }}** | {{ $report['status'][$key]['total'] }} | {{ $report['status'][$key]['done'] }} | {{ $report['status'][$key]['progress'] }} | {{ $report['status'][$key]['todo'] }} | {{ $report['status'][$key]['issue'] }} | {{ $report['status'][$key]['percent'] }}% |
@endforeach
</x-mail::table>

## Aktivitas selama rentang ini

- Checklist disubmit teknisi: **{{ $report['activity']['submitted'] }}**
- PM selesai disetujui Atasan: **{{ $report['activity']['approved'] }}**
- PM ditolak (perlu revisi): **{{ $report['activity']['rejected'] }}**

## Menunggu tindakan saat ini

- Menunggu User PIC: **{{ $report['pending']['pic'] }}**
- Menunggu Atasan: **{{ $report['pending']['supervisor'] }}**
- Perlu revisi teknisi: **{{ $report['pending']['revision'] }}**

## Stok suku cadang

Habis: **{{ $report['stock']['out'] }}** item · Menipis: **{{ $report['stock']['low'] }}** item

@if (count($report['stock']['items']) > 0)
<x-mail::table>
| Kode | Suku Cadang | Stok | Minimum |
|:--|:--|--:|--:|
@foreach ($report['stock']['items'] as $part)
| {{ $part->code }} | {{ $part->name }} | {{ $part->stock }} {{ $part->unit }} | {{ $part->min_stock }} |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="$url">
Buka Dashboard PM
</x-mail::button>

Rekap lengkap terlampir dalam file Excel.

<small>Dibuat otomatis {{ $report['generatedAt'] }} WIB oleh PM Preventive — PT Verra Inter Pangan.</small>
</x-mail::message>
