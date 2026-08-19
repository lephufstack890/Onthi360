@props(['emoji' => '✨', 'tone' => 'rose'])
@php
    $bg = [
        'rose' => 'bg-rose-100',
        'sky' => 'bg-sky-100',
        'violet' => 'bg-violet-100',
        'amber' => 'bg-amber-100',
        'emerald' => 'bg-emerald-100',
    ][$tone] ?? 'bg-rose-100';
@endphp
<div class="w-11 h-11 rounded-2xl {{ $bg }} flex items-center justify-center text-xl shrink-0">
    {{ $emoji }}
</div>
