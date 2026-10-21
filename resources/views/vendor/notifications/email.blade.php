{{-- Teks bawaan diterjemahkan ke Bahasa Indonesia (Settings → Integrate). --}}
<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# Mohon perhatian
@else
# Halo!
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Salam,<br>
{{ config('mail.from.name') ?: config('app.name') }} — PT Verra Inter Pangan
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
Jika tombol "{{ $actionText }}" tidak bisa diklik, salin dan buka alamat berikut di browser Anda: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
