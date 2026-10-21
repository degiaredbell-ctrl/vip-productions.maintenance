<x-mail::message>
# {{ $report['title'] }}

**Rentang:** {{ $report['rangeLabel'] }}<br>
**Periode PM berjalan:** {{ $report['periodLabel'] }}@if (! empty($report['currentWeek'])) · Minggu ke-{{ $report['currentWeek'] }}@endif

## Ringkasan pencapaian PM

<x-mail::table>
| Terjadwal | Selesai | Dalam Approval | Revisi | Belum | Terlambat | Pencapaian |
|--:|--:|--:|--:|--:|--:|--:|
| **{{ $report['summary']['total'] }}** | **{{ $report['summary']['done'] }}** | {{ $report['summary']['progress'] }} | {{ $report['summary']['issue'] }} | {{ $report['summary']['todo'] }} | **{{ $report['summary']['overdue'] }}** | **{{ $report['summary']['percent'] }}%** |
</x-mail::table>

<x-mail::table>
| | Terjadwal | Selesai | Approval | Revisi | Belum | Terlambat | Pencapaian |
|:--|--:|--:|--:|--:|--:|--:|--:|
@foreach (['machine' => 'Mesin', 'utility' => 'Utility'] as $key => $label)
| **{{ $label }}** | {{ $report['status'][$key]['total'] }} | {{ $report['status'][$key]['done'] }} | {{ $report['status'][$key]['progress'] }} | {{ $report['status'][$key]['issue'] }} | {{ $report['status'][$key]['todo'] }} | {{ $report['status'][$key]['overdue'] }} | {{ $report['status'][$key]['percent'] }}% |
@endforeach
</x-mail::table>

## Perangkat terlambat ({{ count($report['overdueList']) }})

@if (count($report['overdueList']) > 0)
<x-mail::table>
| No | Perangkat | Lokasi | Minggu | Batas | Terlambat |
|:--|:--|:--|:--:|:--|--:|
@foreach (array_slice($report['overdueList'], 0, 50) as $i => $item)
| {{ $i + 1 }} | [{{ $item['code'] }} — {{ str_replace(['[', ']', '|'], ['(', ')', '/'], $item['name']) }}]({{ $base.$item['path'] }}) | {{ str_replace('|', '/', $item['location'] ?: '-') }} | W{{ $item['week'] }} | {{ $item['deadline'] }} | {{ $item['days_late'] }} hari |
@endforeach
</x-mail::table>
@if (count($report['overdueList']) > 50)
<small>…dan {{ count($report['overdueList']) - 50 }} perangkat lainnya (lihat lampiran PDF).</small>
@endif
@else
Tidak ada perangkat terlambat. 👍
@endif

## Aktivitas selama rentang ini

- Checklist disubmit teknisi: **{{ $report['activity']['submitted'] }}**
- PM selesai disetujui Atasan: **{{ $report['activity']['approved'] }}**
- PM dikembalikan untuk revisi: **{{ $report['activity']['rejected'] }}**

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

@if ($attachPdf || $attachExcel)
Terlampir: {{ implode(' dan ', array_filter([$attachPdf ? 'ringkasan PDF' : null, $attachExcel ? 'rekap lengkap Excel' : null])) }}.
@endif

<small>Dibuat otomatis {{ $report['generatedAt'] }} WIB oleh PM Preventive — PT Verra Inter Pangan.</small>
</x-mail::message>
