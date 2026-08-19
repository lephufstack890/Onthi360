@props(['tone' => 'neutral'])
@php
    $colors = [
        'neutral' => 'bg-slate-100 text-slate-600',
        'info' => 'bg-sky-100 text-sky-700',
        'success' => 'bg-emerald-100 text-emerald-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'danger' => 'bg-rose-100 text-rose-700',
    ];
@endphp
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $colors[$tone] ?? $colors['neutral'] }}">
    {{ $slot }}
</span>
