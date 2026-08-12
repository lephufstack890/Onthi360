{{--
  Card dùng chung cho khóa học/tài liệu/lớp ở khu vực công khai.
  Card phải nói rõ Công khai/Có phí/Cần kích hoạt/Sắp mở (12.2) — không
  giấu điều kiện sau CTA.
--}}
@props(['title', 'href' => '#', 'meta' => null, 'badgeLabel' => null, 'badgeTone' => 'info', 'average' => null, 'count' => 0])
<a href="{{ $href }}" class="block rounded-2xl bg-white border border-slate-200 overflow-hidden hover:shadow-md transition">
    <div class="aspect-[4/3] bg-slate-100 flex items-center justify-center text-slate-300 text-sm">
        {{-- TODO: ảnh bìa thật --}}
        Ảnh bìa
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
