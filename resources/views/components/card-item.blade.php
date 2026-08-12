{{--
  Card dùng chung cho khóa học/tài liệu/lớp ở khu vực công khai.
  Card phải nói rõ Công khai/Có phí/Cần kích hoạt/Sắp mở (12.2) — không
  giấu điều kiện sau CTA.

  Ảnh bìa: dùng ảnh demo tạm (picsum.photos, seed theo $title nên mỗi thẻ
  luôn ra đúng 1 ảnh cố định, không đổi mỗi lần tải lại) — thay bằng ảnh bìa
  thật (upload/CDN riêng) khi có. Truyền $image để override URL thật.
--}}
@props(['title', 'href' => '#', 'meta' => null, 'badgeLabel' => null, 'badgeTone' => 'info', 'average' => null, 'count' => 0, 'image' => null])
@php
    $coverUrl = $image ?? 'https://picsum.photos/seed/'.urlencode(\Illuminate\Support\Str::slug($title)).'/480/360';
@endphp
<a href="{{ $href }}" class="block rounded-2xl bg-white border border-slate-200 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all">
    <div class="aspect-[4/3] bg-slate-100 overflow-hidden">
        <img src="{{ $coverUrl }}" alt="" loading="lazy" class="w-full h-full object-cover">
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
