{{-- Thanh tiến độ. GIỮ nguyên nguyên tắc cũ (13.3): không chỉ dùng màu để báo trạng thái,
     luôn kèm số phần trăm dạng chữ. --}}
@props(['percent' => 0, 'tone' => 'brand', 'label' => null])
@php
    $bar = [
        'brand' => 'bg-blue-600',
        'success' => 'bg-emerald-500',
        'info' => 'bg-sky-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
    ][$tone] ?? 'bg-blue-600';
@endphp
<div>
    @if ($label)
        <div class="mb-1 flex items-center justify-between gap-2 text-[11px] font-bold">
            <span class="text-slate-500">{{ $label }}</span>
            <span class="text-slate-700">{{ $percent }}%</span>
        </div>
    @endif
    <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full {{ $bar }} transition-[width] duration-500" style="width: {{ max(0, min(100, $percent)) }}%"></div>
    </div>
</div>
