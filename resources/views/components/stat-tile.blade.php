@props(['label', 'value', 'hint' => null, 'tone' => 'neutral'])
@php
    $tones = [
        'neutral' => 'border-slate-200',
        'danger' => 'border-rose-200',
        'warning' => 'border-amber-200',
        'success' => 'border-emerald-200',
    ];
@endphp
<div class="rounded-2xl bg-white border {{ $tones[$tone] ?? $tones['neutral'] }} p-5">
    <p class="text-sm text-slate-500">{{ $label }}</p>
    <p class="text-2xl font-semibold text-slate-800 mt-1">{{ $value }}</p>
    @if($hint)
        <p class="text-xs text-slate-400 mt-1">{{ $hint }}</p>
    @endif
</div>
