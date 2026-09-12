{{-- Nút / liên kết dùng chung của trang quản trị. Tự render <a> khi có href, <button> khi không.
     Bản cũ mỗi màn tự viết class riêng (đa số là bg-rose-600) nên mỗi nơi một kiểu — gom về
     đây để cả khu quản trị đồng bộ. --}}
@props([
    'variant' => 'primary',
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'type' => 'submit',
    'size' => 'md',
])
@php
    $variants = [
        'primary' => 'bg-blue-600 text-white shadow-sm shadow-blue-200 hover:bg-blue-700',
        'success' => 'bg-emerald-600 text-white shadow-sm shadow-emerald-100 hover:bg-emerald-700',
        'warning' => 'border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100',
        'danger' => 'border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100',
        'ghost' => 'border border-sky-100 bg-white text-slate-700 hover:border-sky-200 hover:bg-sky-50',
        'soft' => 'border border-blue-100 bg-blue-50 text-blue-700 hover:bg-blue-100',
        // Trên nền banner xanh của x-admin.page-header
        'onhero' => 'bg-white text-blue-700 shadow-sm hover:bg-sky-50',
        'onhero-ghost' => 'border border-white/35 bg-white/10 text-white backdrop-blur-sm hover:bg-white/20',
    ];
    $sizes = [
        'sm' => 'min-h-9 px-2.5 py-1.5 text-[11px]',
        'md' => 'min-h-10 px-3.5 py-2 text-xs',
        'lg' => 'min-h-11 px-4 py-2.5 text-[13px]',
    ];
    $classes = 'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-50 '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-lucide :name="$icon" class="h-3.5 w-3.5 shrink-0" />@endif
        <span class="min-w-0 truncate">{{ $slot }}</span>
        @if ($iconRight)<x-lucide :name="$iconRight" class="h-3.5 w-3.5 shrink-0" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-lucide :name="$icon" class="h-3.5 w-3.5 shrink-0" />@endif
        <span class="min-w-0 truncate">{{ $slot }}</span>
        @if ($iconRight)<x-lucide :name="$iconRight" class="h-3.5 w-3.5 shrink-0" />@endif
    </button>
@endif
