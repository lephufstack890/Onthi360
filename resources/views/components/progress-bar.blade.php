{{-- Thanh tiến độ pastel dùng chung — không chỉ dùng màu để báo trạng thái (13.3), luôn kèm % dạng chữ. --}}
@props(['percent' => 0, 'tone' => 'brand', 'label' => null])
@php
    $bar = [
        'brand' => 'bg-rose-500',
        'success' => 'bg-emerald-500',
        'info' => 'bg-sky-500',
        'warning' => 'bg-amber-500',
    ][$tone] ?? 'bg-rose-500';
@endphp
<div>
    @if ($label)
        <div class="flex justify-between text-xs text-slate-500 mb-1">
            <span>{{ $label }}</span>
            <span>{{ $percent }}%</span>
        </div>
    @endif
    <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
        <div class="h-full {{ $bar }} rounded-full" style="width: {{ max(0, min(100, $percent)) }}%"></div>
    </div>
</div>
