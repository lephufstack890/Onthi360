{{-- Ô icon vuông bo tròn đứng đầu các thẻ. Nhận cả 'icon' (tên lucide, ưu tiên) lẫn 'emoji'
     (bản cũ) để view nào chưa đổi vẫn hiển thị được. --}}
@props(['icon' => null, 'emoji' => null, 'tone' => 'blue'])
@php
    $tones = [
        'blue' => 'border-blue-100 bg-blue-50 text-blue-600',
        'rose' => 'border-rose-100 bg-rose-50 text-rose-600',
        'sky' => 'border-sky-100 bg-sky-50 text-sky-600',
        'violet' => 'border-violet-100 bg-violet-50 text-violet-600',
        'amber' => 'border-amber-100 bg-amber-50 text-amber-600',
        'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-600',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'grid h-11 w-11 shrink-0 place-items-center rounded-2xl border '.($tones[$tone] ?? $tones['blue'])]) }}>
    @if ($icon)
        <x-lucide :name="$icon" class="h-5 w-5" />
    @else
        <span class="text-lg leading-none">{{ $emoji ?? '✨' }}</span>
    @endif
</div>
