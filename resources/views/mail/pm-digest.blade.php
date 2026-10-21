<x-mail::message>
# {{ $title }}

Halo, {{ $name }}!

{{ $body }}

@if (count($roles) > 0)
<small>Anda menerima notifikasi ini sebagai: {{ implode(', ', $roles) }}.</small>
@endif

<x-mail::table>
@if ($overdue)
| No | Perangkat | Lokasi | Minggu | Batas | Terlambat |
|:--|:--|:--|:--:|:--|--:|
@foreach ($items as $i => $item)
| {{ $i + 1 }} | [{{ $item['code'] }} — {{ str_replace(['[', ']', '|'], ['(', ')', '/'], $item['name']) }}]({{ $base.$item['path'] }}) | {{ str_replace('|', '/', $item['location'] ?: '-') }} | W{{ $item['week'] }} | {{ $item['deadline'] }} | **{{ $item['days_late'] }} hari** |
@endforeach
@else
| No | Perangkat | Lokasi | Minggu | Batas | Status |
|:--|:--|:--|:--:|:--|:--|
@foreach ($items as $i => $item)
| {{ $i + 1 }} | [{{ $item['code'] }} — {{ str_replace(['[', ']', '|'], ['(', ')', '/'], $item['name']) }}]({{ $base.$item['path'] }}) | {{ str_replace('|', '/', $item['location'] ?: '-') }} | W{{ $item['week'] }} | {{ $item['deadline'] }} | {{ $item['status'] }} |
@endforeach
@endif
</x-mail::table>

<x-mail::button :url="$url" :color="$overdue ? 'error' : 'primary'">
{{ $overdue ? 'Lihat Perangkat Terlambat' : 'Buka Daftar PM' }}
</x-mail::button>

@if ($overdue)
Pengingat ini dikirim ulang setiap hari selama PM belum selesai dan berhenti otomatis untuk perangkat yang PM-nya sudah dikerjakan.
@endif

<small>Dikirim otomatis {{ $generatedAt }} WIB oleh PM Preventive — PT Verra Inter Pangan.</small>
</x-mail::message>
