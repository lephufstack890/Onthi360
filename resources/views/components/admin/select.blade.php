{{-- Ô chọn — bo 2xl, viền sky, quầng focus xanh dương cho khớp ô nhập của các màn đã thiết kế. --}}
@props(['icon' => null])
<div class="relative">
    @if ($icon)
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
            <x-lucide :name="$icon" class="h-4 w-4" />
        </span>
    @endif
    <select {{ $attributes->merge(['class' => 'min-h-11 w-full cursor-pointer appearance-none rounded-2xl border border-sky-100 bg-white py-2.5 '.($icon ? 'pl-9' : 'pl-3.5').' pr-9 text-xs font-medium text-slate-700 outline-none transition hover:border-sky-200 focus:border-blue-300 focus:ring-2 focus:ring-blue-200']) }}>
        {{ $slot }}
    </select>
    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
        <x-lucide name="chevron-down" class="h-4 w-4" />
    </span>
</div>
