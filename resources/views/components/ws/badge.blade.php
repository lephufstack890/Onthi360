{{-- Nhãn trạng thái — viên bo tròn, chữ nhỏ đậm, đúng kiểu badge trong source. --}}
@props(['tone' => 'neutral'])
@php
    $colors = [
        'neutral' => 'border-slate-200 bg-slate-50 text-slate-600',
        'info' => 'border-sky-200 bg-sky-50 text-sky-700',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-700',
        'brand' => 'border-blue-200 bg-blue-50 text-blue-700',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold leading-tight '.($colors[$tone] ?? $colors['neutral'])]) }}>
    {{ $slot }}
</span>
