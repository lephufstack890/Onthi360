{{-- Ô chỉ số — chuyển đúng <Stat> của source RoleWorkspace.jsx. --}}
@props(['label', 'value', 'hint' => null, 'tone' => 'blue', 'icon' => null])
@php
    // Bản cũ (x-stat-tile) dùng tên tông neutral/danger/warning/success; nhận cả 2 bộ tên để
    // các view chưa đổi hết vẫn ra đúng màu chứ không rơi về mặc định.
    $tones = [
        'blue' => 'bg-blue-50 text-blue-600 border-blue-100',
        'neutral' => 'bg-blue-50 text-blue-600 border-blue-100',
        'emerald' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
        'success' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
        'amber' => 'bg-amber-50 text-amber-600 border-amber-100',
        'warning' => 'bg-amber-50 text-amber-600 border-amber-100',
        'violet' => 'bg-violet-50 text-violet-600 border-violet-100',
        'rose' => 'bg-rose-50 text-rose-600 border-rose-100',
        'danger' => 'bg-rose-50 text-rose-600 border-rose-100',
    ];
    $toneClass = $tones[$tone] ?? $tones['blue'];
    $toneIcon = $icon ?? match ($tone) {
        'emerald', 'success' => 'check-circle-2',
        'amber', 'warning' => 'clock-3',
        'rose', 'danger' => 'alert-triangle',
        'violet' => 'wallet-cards',
        default => 'bar-chart-3',
    };
@endphp
<div {{ $attributes->merge(['class' => 'h-full rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_2px_8px_rgba(0,90,180,.04)] transition-all hover:-translate-y-0.5 hover:shadow-md']) }}>
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
            <p class="mt-1 text-xl font-black text-slate-800">{{ $value }}</p>
        </div>
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border {{ $toneClass }}">
            <x-lucide :name="$toneIcon" class="h-4 w-4" />
        </span>
    </div>
    @if ($hint)
        <p class="mt-2 text-[11px] font-medium text-slate-500">{{ $hint }}</p>
    @endif
</div>
