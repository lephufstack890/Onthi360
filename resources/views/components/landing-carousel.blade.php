@props(['id' => 'landing-carousel'])

@php
    $slides = [
        [
            'tag' => 'Lộ trình',
            'title' => 'Học có lộ trình rõ ràng',
            'body' => 'Chọn khối/mục tiêu → vào khóa/lớp phù hợp → luyện tập và kiểm tra → theo dõi tiến bộ theo thời gian thực. Không học lan man, luôn biết bước tiếp theo là gì.',
            'emoji' => '🧭',
            'from' => 'from-sky-100',
            'to' => 'to-blue-50',
            'accent' => 'text-sky-600',
        ],
        [
            'tag' => 'Tài liệu',
            'title' => 'Học liệu có bản quyền, minh bạch',
            'body' => 'Sách, Chuyên đề, Đề thi có mục lục rõ ràng, quyền học cá nhân có thời hạn cụ thể — mua/kích hoạt minh bạch, không mập mờ điều kiện sau nút bấm.',
            'emoji' => '📚',
            'from' => 'from-violet-100',
            'to' => 'to-purple-50',
            'accent' => 'text-violet-600',
        ],
        [
            'tag' => 'Cam kết',
            'title' => 'Luôn nêu đúng lý do, không hứa suông',
            'body' => 'Bài bị khóa luôn nói rõ vì sao — thiếu quyền, chưa mở theo lớp hay đã quá hạn. Không gọi là "AI chấm bài" khi hệ thống dùng luật/OJ. Dữ liệu trẻ em được bảo vệ mặc định.',
            'emoji' => '🤝',
            'from' => 'from-rose-100',
            'to' => 'to-pink-50',
            'accent' => 'text-rose-600',
        ],
        [
            'tag' => 'Tư tưởng',
            'title' => 'Học rõ hơn — Tiến bộ nhanh hơn',
            'body' => 'Học sinh biết học gì tiếp theo và làm được đến đâu; giáo viên tổ chức lớp mà không phải giao từng bài; phụ huynh nhìn thấy tiến độ thực của con.',
            'emoji' => '💡',
            'from' => 'from-amber-100',
            'to' => 'to-yellow-50',
            'accent' => 'text-amber-600',
        ],
    ];
@endphp

<div class="relative">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Ý tưởng Ôn Thi 360, nhìn là hiểu</h2>
        <div class="hidden sm:flex gap-2">
            <button type="button" onclick="document.getElementById('{{ $id }}').scrollBy({left: -340, behavior: 'smooth'})"
                    class="w-9 h-9 rounded-full border border-slate-200 bg-white text-slate-500 hover:text-rose-600 hover:border-rose-200" aria-label="Slide trước">‹</button>
            <button type="button" onclick="document.getElementById('{{ $id }}').scrollBy({left: 340, behavior: 'smooth'})"
                    class="w-9 h-9 rounded-full border border-slate-200 bg-white text-slate-500 hover:text-rose-600 hover:border-rose-200" aria-label="Slide sau">›</button>
        </div>
    </div>

    <div id="{{ $id }}" class="flex gap-4 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-2 -mx-4 px-4 lg:mx-0 lg:px-0 no-scrollbar"
         style="scrollbar-width: none;">
        @foreach ($slides as $s)
            <div class="snap-start shrink-0 w-[85%] sm:w-[60%] lg:w-[calc(30%-12px)]">
                <div class="h-full rounded-3xl bg-gradient-to-br {{ $s['from'] }} {{ $s['to'] }} border border-white p-6 relative overflow-hidden">
                    <span class="absolute -top-3 -right-3 text-3xl opacity-40 select-none" aria-hidden="true">✦</span>
                    <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-2xl shadow-sm mb-4">
                        {{ $s['emoji'] }}
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wide {{ $s['accent'] }} mb-1">{{ $s['tag'] }}</p>
                    <h3 class="font-semibold text-slate-800 mb-2 leading-snug">{{ $s['title'] }}</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">{{ $s['body'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex justify-center gap-1.5 mt-4">
        @foreach ($slides as $s)
            <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
        @endforeach
    </div>
</div>

@once
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
@endonce
