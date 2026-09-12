{{-- Banner đầu trang của khu làm việc — rút gọn từ <Hero> của source
     (education-main/src/components/RoleWorkspace.jsx): nền xanh + ảnh mờ + nhãn nhỏ, tiêu đề,
     mô tả và cụm nút bên phải. Để thấp hơn bản mẫu vì ở đây MỖI MÀN là một trang riêng, dùng
     banner cao 188px như workspace thì lấn hết chỗ của nội dung.

     Ảnh nền và nhãn nhỏ tự đổi theo khu đang xem (source cũng có 4 ảnh riêng cho 4 vai trò),
     suy ra từ tên route hiện tại nên không phải truyền thêm gì ở 100+ chỗ đang gọi.

     Dùng: <x-ws.page-header title="..." subtitle="..." icon="users">
              <x-slot:actions> ... </x-slot:actions>
           </x-ws.page-header> --}}
@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'icon' => null,
    'back' => null,
    'backLabel' => 'Quay lại',
])
@php
    $wsHero = match (true) {
        request()->routeIs('admin.*') => ['img' => 'workspace-admin-hero.jpg', 'eyebrow' => 'Quản trị & kiểm duyệt'],
        request()->routeIs('teacher.*') => ['img' => 'workspace-teacher-hero.jpg', 'eyebrow' => 'Không gian giảng dạy'],
        request()->routeIs('parent.*') => ['img' => 'workspace-parent-hero.jpg', 'eyebrow' => 'Đồng hành cùng con'],
        default => ['img' => 'workspace-student-hero.jpg', 'eyebrow' => 'Hành trình học tập'],
    };
    $wsEyebrow = $eyebrow ?? $wsHero['eyebrow'];
@endphp
<section class="relative overflow-hidden rounded-3xl border border-sky-100 bg-[#0d5faf] shadow-[0_7px_20px_rgba(0,95,180,.09)]">
    <img src="{{ asset('assets/'.$wsHero['img']) }}" alt="" loading="eager" decoding="async"
         class="pointer-events-none absolute inset-0 h-full w-full select-none object-cover">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-[#0759a8]/95 via-[#0976c9]/75 to-[#0976c9]/25"></div>

    <div class="relative flex flex-col gap-3 px-4 py-4 text-white sm:flex-row sm:items-end sm:justify-between sm:gap-4 sm:px-6 sm:py-5">
        <div class="min-w-0">
            @if ($back)
                <a href="{{ $back }}" class="mb-1.5 inline-flex items-center gap-1 text-[11px] font-bold text-sky-100 transition-colors hover:text-white">
                    <x-lucide name="arrow-left" class="h-3.5 w-3.5" />{{ $backLabel }}
                </a>
            @endif

            <p class="text-[10px] font-bold uppercase tracking-[.14em] text-sky-100">{{ $wsEyebrow }}</p>

            <h1 class="mt-1 flex min-w-0 items-center gap-2 break-words text-lg font-bold tracking-tight text-white sm:text-xl">
                @if ($icon)
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border border-white/25 bg-white/15">
                        <x-lucide :name="$icon" class="h-4 w-4" />
                    </span>
                @endif
                <span class="min-w-0">{{ $title }}</span>
            </h1>

            @if ($subtitle)
                <p class="mt-1 max-w-2xl text-xs leading-relaxed text-sky-50">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</section>
