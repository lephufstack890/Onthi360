@props(['icon' => null])
<div class="relative">
    @if ($icon)
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm">{{ $icon }}</span>
    @endif
    <select {{ $attributes->merge(['class' => 'w-full appearance-none rounded-lg border border-slate-200 bg-white text-sm text-slate-700 py-2.5 '.($icon ? 'pl-9' : 'pl-3').' pr-9 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition cursor-pointer']) }}>
        {{ $slot }}
    </select>
    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M5.5 7.5L10 12l4.5-4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</div>
