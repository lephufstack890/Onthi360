@props(['title', 'href' => '#', 'meta' => null, 'badgeLabel' => null, 'badgeTone' => 'info', 'average' => null, 'count' => 0, 'image' => null, 'owned' => false])
@php
    $coverUrl = $image ?? 'https://picsum.photos/seed/'.urlencode(\Illuminate\Support\Str::slug($title)).'/480/360';
@endphp
<a href="{{ $href }}" class="block rounded-2xl bg-white border border-slate-200 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all">
    <div class="aspect-[4/3] bg-slate-100 overflow-hidden relative">
        <img src="{{ $coverUrl }}" alt="" loading="lazy" class="w-full h-full object-cover">
        @if ($owned)
            <span title="Bạn đã sở hữu" class="absolute top-2 right-2 w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow text-sm font-semibold">✓</span>
        @endif
    </div>
    <div class="p-4 space-y-2">
        @if ($badgeLabel)
            <x-status-badge :tone="$badgeTone">{{ $badgeLabel }}</x-status-badge>
        @endif
        <h3 class="font-medium text-slate-800 line-clamp-2">{{ $title }}</h3>
        @if ($meta)
            <p class="text-xs text-slate-400">{{ $meta }}</p>
        @endif
        <x-rating-summary :average="$average" :count="$count" />
    </div>
</a>
