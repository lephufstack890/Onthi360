{{--
  Route: home | Frame: PUB-01/02
  Spec: 12.1 (cấu trúc trang chủ: hero → lộ trình → năng lực chấm →
  khóa/tài liệu nổi bật → cuộc thi → giáo viên tiêu biểu → cam kết/FAQ).
  TODO controller: truyền $featuredCourses/$featuredMaterials/$upcomingCompetitions/$featuredTeachers thật.

  Ảnh minh họa trên trang này là ẢNH DEMO TẠM (picsum.photos / ui-avatars.com,
  seed cố định theo tên nên không đổi mỗi lần tải lại) — thay bằng ảnh thật
  khi có nội dung/CDN riêng, xem thêm ghi chú trong x-card-item.
--}}
@extends('layouts.guest')

@section('title', 'Trang chủ')

@section('content')
    @php
        $featuredCourses = [
            ['title' => 'Luyện thi vào 10 Chuyên Tin', 'meta' => '5 lớp đang triển khai', 'average' => 4.8, 'count' => 126],
            ['title' => 'Ôn thi HSG Tin 11', 'meta' => '2 lớp đang triển khai', 'average' => 4.6, 'count' => 42],
            ['title' => 'Luyện thi vào 10 Chuyên Toán', 'meta' => '3 lớp đang triển khai', 'average' => 4.9, 'count' => 91],
            ['title' => 'Nền tảng Python cho học sinh THCS', 'meta' => '1 lớp đang triển khai', 'average' => 4.7, 'count' => 58],
        ];
        $featuredMaterials = [
            ['title' => 'Sách: Ôn thi Tin học 10', 'meta' => 'Sách · Bản mềm + bản in', 'average' => 4.7, 'count' => 88],
            ['title' => 'Chuyên đề: Cấu trúc dữ liệu nâng cao', 'meta' => 'Chuyên đề · Cần kích hoạt', 'average' => null, 'count' => 2],
            ['title' => 'Bộ đề thi thử vào 10 Chuyên Tin', 'meta' => 'Đề thi · 10 đề có đáp án', 'average' => 4.5, 'count' => 34],
            ['title' => 'Chuyên đề: Quy hoạch động nhập môn', 'meta' => 'Chuyên đề · Cần kích hoạt', 'average' => 4.8, 'count' => 19],
        ];
        $competitions = [
            ['title' => 'Cuộc thi Tin học trẻ 2026', 'meta' => '20/08 - 25/08/2026 · Toàn quốc'],
            ['title' => 'Giải Toán tuổi thơ mở rộng', 'meta' => '05/09 - 10/09/2026 · Khối 6-9'],
        ];
        $featuredTeachers = [
            ['name' => 'Nguyễn Văn A', 'subject' => 'Tin học'],
            ['name' => 'Trần Thị B', 'subject' => 'Toán'],
            ['name' => 'Lê Văn C', 'subject' => 'Toán'],
            ['name' => 'Phạm Thị D', 'subject' => 'Tin học'],
        ];
        $stats = [
            ['value' => '12.000+', 'label' => 'Học sinh đang học'],
            ['value' => '350+', 'label' => 'Giáo viên đã duyệt'],
            ['value' => '120+', 'label' => 'Khóa học & lớp'],
            ['value' => '4.8/5', 'label' => 'Đánh giá trung bình'],
        ];
        $faqs = [
            ['q' => 'Bài công khai có cần đăng nhập không?', 'a' => 'Khách xem được; cần đăng nhập để bắt đầu, nộp bài và lưu kết quả.'],
            ['q' => 'Quyền học và quyền dạy khác nhau thế nào?', 'a' => 'Quyền dạy của giáo viên không tự cấp quyền học cho học sinh, và ngược lại — mỗi quyền có phạm vi và thời hạn riêng, luôn hiển thị rõ trên từng học liệu.'],
            ['q' => 'Vì sao một bài học lại bị khóa?', 'a' => 'Hệ thống luôn nêu đúng lý do: thiếu quyền học liệu, giáo viên chưa mở theo tiến độ lớp, hoặc quyền đã hết hạn — không khóa mà không giải thích.'],
            ['q' => 'Chấm bài code (OJ) hoạt động thế nào?', 'a' => 'Bài code được chấm bằng bộ test/luật rõ ràng, kế thừa năng lực từ Quinhdao OJ — không gọi là "AI chấm" khi hệ thống thực chất dùng luật/test case.'],
        ];
    @endphp

    {{-- 1. Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-rose-50 via-rose-50/60 to-white">
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-amber-100/60 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-24 w-96 h-96 rounded-full bg-sky-100/50 blur-3xl" aria-hidden="true"></div>

        <div class="max-w-7xl mx-auto px-4 py-16 lg:py-24 relative">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="text-center lg:text-left">
                    <p class="text-rose-500 font-semibold text-sm tracking-wide mb-2">Học vui hơn · Hiểu sâu hơn · Tiến bộ mỗi ngày!</p>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white border border-rose-100 text-rose-600 text-xs font-medium mb-5">
                        ✦ Học sinh lớp 6–12 · Giáo viên · Phụ huynh
                    </span>
                    <h1 class="text-3xl lg:text-5xl font-semibold text-slate-800 leading-tight">
                        Học có lộ trình, luyện tập và chấm bài — cùng Ôn Thi 360
                    </h1>
                    <p class="text-slate-500 mt-4 max-w-xl mx-auto lg:mx-0">
                        Kế thừa năng lực chấm code của Quinhdao OJ, mở rộng thành một hành trình học hoàn chỉnh
                        cho học sinh lớp 6–12, giáo viên và phụ huynh.
                    </p>
                    <div class="mt-8 flex flex-wrap justify-center lg:justify-start gap-3">
                        <a href="{{ route('courses.index') }}" class="px-6 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition">Khám phá khóa học</a>
                        <a href="{{ route('practice.index') }}" class="px-6 py-3 rounded-lg border border-slate-200 bg-white text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Luyện tập ngay</a>
                    </div>

                    <div class="mt-10 grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-xl mx-auto lg:mx-0">
                        @foreach ($stats as $stat)
                            <div class="text-center lg:text-left">
                                <p class="text-xl lg:text-2xl font-semibold text-slate-800">{{ $stat['value'] }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $stat['label'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="relative hidden lg:block">
                    <div class="rounded-[2rem] overflow-hidden border-4 border-white shadow-xl rotate-1">
                        <img src="https://picsum.photos/seed/onthi360-hero/700/560" alt="Học sinh học tập cùng Ôn Thi 360" class="w-full h-full object-cover">
                    </div>
                    <div class="absolute -bottom-6 -left-8 bg-white rounded-2xl border border-slate-100 shadow-lg px-4 py-3 flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-lg">✅</span>
                        <div>
                            <p class="text-sm font-medium text-slate-700">Bài vừa được chấm</p>
                            <p class="text-xs text-slate-400">Đạt 9.5/10 điểm</p>
                        </div>
                    </div>
                    <div class="absolute -top-6 -right-6 bg-white rounded-2xl border border-slate-100 shadow-lg px-4 py-3 flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center text-lg">🔥</span>
                        <div>
                            <p class="text-sm font-medium text-slate-700">7 ngày học liên tục</p>
                            <p class="text-xs text-slate-400">Giữ vững lộ trình nhé!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 1b. Tính năng nổi bật --}}
    <section class="max-w-7xl mx-auto px-4 py-10">
        <div class="text-center mb-8">
            <h2 class="text-xl lg:text-2xl font-semibold text-slate-800">Vì sao học sinh, giáo viên và phụ huynh chọn Ôn Thi 360</h2>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            @php
                $highlights = [
                    ['emoji' => '🤖', 'tone' => 'rose', 'title' => 'AI luyện tập thông minh', 'body' => 'Gợi ý bài phù hợp năng lực, cá nhân hóa lộ trình học.'],
                    ['emoji' => '🧑‍🤝‍🧑', 'tone' => 'sky', 'title' => 'Lớp học gọn gàng', 'body' => 'Tổ chức lớp, giao bài, theo dõi tiến độ dễ dàng.'],
                    ['emoji' => '📋', 'tone' => 'violet', 'title' => 'Khảo sát an toàn', 'body' => 'Tham gia đúng thời điểm, kiểm soát chặt với late-link.'],
                    ['emoji' => '👛', 'tone' => 'amber', 'title' => 'Ví & Token minh bạch', 'body' => 'Xem số dư, lịch sử sử dụng và quyền lợi rõ ràng.'],
                    ['emoji' => '🌍', 'tone' => 'emerald', 'title' => 'Du học & Trải nghiệm', 'body' => 'Không gian mở rộng cho định hướng và cơ hội phát triển.'],
                ];
            @endphp
            @foreach ($highlights as $h)
                <div class="rounded-2xl bg-white border border-slate-200 p-4 lg:p-5 text-center hover:shadow-md transition">
                    <div class="flex justify-center">
                        <x-icon-tile :emoji="$h['emoji']" :tone="$h['tone']" />
                    </div>
                    <p class="font-medium text-slate-700 text-sm mt-3">{{ $h['title'] }}</p>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">{{ $h['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 1c. Dành cho ai + Vì sao nên chọn --}}
    <section class="max-w-7xl mx-auto px-4 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-3xl bg-white border border-slate-200 p-6 lg:p-8">
                <h3 class="font-semibold text-slate-800 mb-5 flex items-center gap-2">💗 Dành cho</h3>
                @php
                    $audiences = [
                        ['emoji' => '🧑‍🎓', 'tone' => 'rose', 'label' => 'Học sinh', 'body' => 'Luyện tập, làm bài và theo dõi tiến độ của chính mình.'],
                        ['emoji' => '🍎', 'tone' => 'sky', 'label' => 'Giáo viên', 'body' => 'Tổ chức lớp, giao bài, chấm điểm và theo sát học sinh.'],
                        ['emoji' => '👨‍👩‍👧', 'tone' => 'emerald', 'label' => 'Phụ huynh', 'body' => 'Theo dõi lịch học, điểm danh và kết quả của con.'],
                        ['emoji' => '🏫', 'tone' => 'violet', 'label' => 'Trung tâm', 'body' => 'Quản lý nhiều lớp, nhiều giáo viên tập trung một nơi.'],
                    ];
                @endphp
                <div class="grid grid-cols-2 gap-4">
                    @foreach ($audiences as $a)
                        <div class="flex items-start gap-3">
                            <x-icon-tile :emoji="$a['emoji']" :tone="$a['tone']" />
                            <div>
                                <p class="font-medium text-slate-700 text-sm">{{ $a['label'] }}</p>
                                <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">{{ $a['body'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-3xl bg-white border border-slate-200 p-6 lg:p-8">
                <h3 class="font-semibold text-slate-800 mb-5 flex items-center gap-2">⭐ Vì sao nên chọn Ôn Thi 360?</h3>
                @php
                    $reasons = [
                        '⏰ Luyện tập mọi lúc, mọi nơi',
                        '📈 Nâng cao hiệu quả học tập',
                        '📝 Đề thi bám sát chương trình',
                        '🛡️ Học tập an toàn, lành mạnh',
                        '📊 Phân tích điểm chi tiết',
                        '👥 Cộng đồng học tập tích cực',
                    ];
                @endphp
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach ($reasons as $r)
                        <div class="flex items-center gap-2 text-sm text-slate-600 rounded-xl bg-slate-50 px-3 py-2.5">
                            {{ $r }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 2/3. Khối carousel Lộ trình - Tài liệu - Cam kết - Tư tưởng --}}
    <section class="max-w-7xl mx-auto px-4 py-14">
        <x-landing-carousel id="home-landing-carousel" />
    </section>

    {{-- 5. Khóa học nổi bật --}}
    <section class="max-w-7xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Khóa học nổi bật</h2>
                <p class="text-sm text-slate-400 mt-1">Đang có lớp triển khai, giáo viên đồng hành theo tiến độ.</p>
            </div>
            <a href="{{ route('courses.index') }}" class="text-sm text-rose-600 font-medium shrink-0">Xem tất cả ›</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($featuredCourses as $c)
                <x-card-item :title="$c['title']" :meta="$c['meta']" :average="$c['average']" :count="$c['count']" href="{{ route('courses.index') }}" badgeLabel="Đang mở" badgeTone="success" />
            @endforeach
        </div>
    </section>

    {{-- 5. Tài liệu nổi bật --}}
    <section class="max-w-7xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Tài liệu nổi bật</h2>
                <p class="text-sm text-slate-400 mt-1">Sách, chuyên đề, đề thi — quyền học minh bạch, có thời hạn rõ ràng.</p>
            </div>
            <a href="{{ route('materials.index') }}" class="text-sm text-rose-600 font-medium shrink-0">Xem tất cả ›</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($featuredMaterials as $m)
                <x-card-item :title="$m['title']" :meta="$m['meta']" :average="$m['average']" :count="$m['count']" href="{{ route('materials.index') }}" badgeLabel="Cần kích hoạt" badgeTone="warning" />
            @endforeach
        </div>
    </section>

    {{-- 6. Cuộc thi sắp tới --}}
    <section class="max-w-7xl mx-auto px-4 py-10">
        <div class="rounded-3xl bg-gradient-to-br from-slate-900 to-slate-800 p-6 lg:p-10 overflow-hidden relative">
            <span class="absolute -bottom-10 -right-10 text-[10rem] opacity-10 select-none" aria-hidden="true">🏆</span>
            <div class="relative">
                <h2 class="text-xl font-semibold text-white mb-1">Cuộc thi sắp tới</h2>
                <p class="text-sm text-slate-300 mb-6">Thử sức, so tài và ghi tên trên bảng xếp hạng toàn quốc.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($competitions as $comp)
                        <a href="{{ route('competitions.index') }}" class="rounded-2xl bg-white/95 backdrop-blur border border-white/10 p-5 flex items-center justify-between hover:bg-white transition">
                            <div>
                                <p class="font-medium text-slate-700">{{ $comp['title'] }}</p>
                                <p class="text-sm text-slate-400">{{ $comp['meta'] }}</p>
                            </div>
                            <span class="text-rose-600 text-sm font-medium shrink-0 ml-3">Xem ›</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 8. Giáo viên tiêu biểu --}}
    <section class="max-w-7xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Giáo viên tiêu biểu</h2>
                <p class="text-sm text-slate-400 mt-1">Đã được duyệt hồ sơ, đồng hành cùng nhiều lớp học.</p>
            </div>
            <a href="{{ route('teachers.index') }}" class="text-sm text-rose-600 font-medium shrink-0">Xem tất cả ›</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($featuredTeachers as $t)
                <a href="{{ route('teachers.index') }}" class="rounded-2xl bg-white border border-slate-200 p-5 text-center hover:shadow-md hover:-translate-y-0.5 transition-all">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($t['name']) }}&background=ffe4e6&color=be123c&size=128&bold=true"
                         alt="{{ $t['name'] }}" class="w-16 h-16 rounded-full mx-auto mb-3 object-cover">
                    <p class="font-medium text-slate-700">{{ $t['name'] }}</p>
                    <p class="text-sm text-slate-400">{{ $t['subject'] }}</p>
                </a>
            @endforeach
        </div>
    </section>

    {{-- 9. Cam kết / FAQ --}}
    <section class="max-w-7xl mx-auto px-4 py-14">
        <h2 class="text-xl font-semibold text-slate-800 mb-2 text-center">Câu hỏi thường gặp</h2>
        <p class="text-sm text-slate-400 mb-6 text-center">Nêu đúng lý do, không hứa suông — đúng cam kết của Ôn Thi 360.</p>
        <div class="max-w-2xl mx-auto space-y-3">
            @foreach ($faqs as $i => $faq)
                <div x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }" class="rounded-xl bg-white border border-slate-200 overflow-hidden">
                    <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-3 p-4 text-left">
                        <p class="font-medium text-slate-700">{{ $faq['q'] }}</p>
                        <span class="text-slate-400 shrink-0" x-text="open ? '−' : '+'"></span>
                    </button>
                    <div x-show="open" x-cloak class="px-4 pb-4 text-sm text-slate-500">
                        {{ $faq['a'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 10. CTA cuối trang --}}
    <section class="max-w-7xl mx-auto px-4 pb-16">
        <div class="rounded-3xl bg-rose-600 px-6 py-10 lg:py-14 text-center relative overflow-hidden">
            <span class="absolute -top-8 -left-8 text-8xl opacity-10 select-none" aria-hidden="true">🚀</span>
            <h2 class="text-2xl lg:text-3xl font-semibold text-white mb-3">Sẵn sàng bắt đầu?</h2>
            <p class="text-rose-100 mb-6 max-w-xl mx-auto">Tham gia ngay không gian học tập thông minh và đáng yêu trên Ôn Thi 360 — đăng ký miễn phí, chọn khối/mục tiêu và bắt đầu luyện tập ngay hôm nay.</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" class="px-6 py-3 rounded-lg bg-white text-rose-600 text-sm font-medium hover:bg-rose-50 transition">Bắt đầu học ngay →</a>
                <a href="{{ route('info.index') }}" class="px-6 py-3 rounded-lg border border-rose-300 text-white text-sm font-medium hover:bg-rose-700 transition">Tìm hiểu thêm</a>
            </div>
        </div>
    </section>
@endsection
