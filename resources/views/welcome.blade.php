@extends('layouts.guest')

@section('title', 'Trang chủ')

@section('content')
    @php
        $featuredTeachers = $featuredTeachers ?? [];

        // Trả về URL ảnh nếu file có thật trong public/images/home/, ngược lại null.
        $homeImg = fn (string $file) => file_exists(public_path('images/home/'.$file))
            ? asset('images/home/'.$file)
            : null;

        $sidebarItems = [
            ['label' => 'Trang chủ', 'route' => 'home', 'icon' => 'home'],
            ['label' => 'Lớp học', 'route' => 'courses.index', 'icon' => 'class'],
            ['label' => 'Luyện tập', 'route' => 'practice.index', 'icon' => 'code'],
            ['label' => 'Tài liệu', 'route' => 'materials.index', 'icon' => 'doc'],
            ['label' => 'Cuộc thi', 'route' => 'competitions.index', 'icon' => 'trophy'],
            ['label' => 'Bảng xếp hạng', 'route' => 'leaderboard.index', 'icon' => 'chart'],
            ['label' => 'Giáo viên và chuyên gia', 'route' => 'teachers.index', 'icon' => 'users'],
            ['label' => 'Thông tin', 'route' => 'info.index', 'icon' => 'info'],
        ];
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 xl:px-6 py-5">
        <div class="flex gap-5 items-start">

            {{-- ═══════════ CỘT TRÁI: menu + 2 thẻ giới thiệu ═══════════ --}}
            <aside class="hidden lg:block w-[228px] shrink-0 space-y-4 sticky top-[84px]">
                <nav class="bg-white rounded-2xl border border-slate-200/80 p-2 space-y-0.5">
                    @foreach ($sidebarItems as $item)
                        @php $isActive = request()->routeIs($item['route']); @endphp
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition {{ $isActive ? 'bg-sky-50 text-sky-600' : 'text-slate-500 hover:bg-slate-50 hover:text-sky-600' }}">
                            <x-nav-icon :name="$item['icon']" class="w-[18px] h-[18px] shrink-0" />
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                {{-- Thẻ "Cùng nhau kiến tạo thành công" --}}
                <a href="{{ route('info.index') }}" class="block rounded-2xl overflow-hidden bg-gradient-to-b from-sky-50 to-blue-100 border border-sky-100 p-4 text-center hover:shadow-md transition">
                    <p class="text-[15px] font-bold text-sky-700 leading-snug italic">Cùng nhau<br>kiến tạo<br>thành công</p>
                    @if ($src = $homeImg('sidebar-city.png'))
                        <img src="{{ $src }}" alt="" class="w-full mt-3">
                    @else
                        <div class="mt-3 h-24 rounded-xl bg-gradient-to-t from-sky-200/80 to-transparent flex items-end justify-center gap-1 px-2 pb-1" aria-hidden="true">
                            <span class="w-4 h-10 rounded-t bg-white/70"></span>
                            <span class="w-5 h-16 rounded-t bg-white/80"></span>
                            <span class="w-4 h-12 rounded-t bg-white/70"></span>
                            <span class="w-6 h-20 rounded-t bg-white/90"></span>
                            <span class="w-4 h-14 rounded-t bg-white/70"></span>
                            <span class="text-lg -mb-1">✈️</span>
                        </div>
                    @endif
                </a>

                {{-- Thẻ "Giáo viên và chuyên gia" — dùng giáo viên đầu tiên từ dữ liệu có sẵn nếu có. --}}
                @php $spotlight = $featuredTeachers[0] ?? null; @endphp
                <div class="rounded-2xl bg-white border border-slate-200/80 p-4">
                    <a href="{{ route('teachers.index') }}" class="flex items-center gap-2 mb-3">
                        <span class="w-7 h-7 rounded-lg bg-sky-100 text-sky-600 flex items-center justify-center">
                            <x-nav-icon name="users" class="w-4 h-4" />
                        </span>
                        <span class="text-[13px] font-bold text-slate-700">Giáo viên và chuyên gia</span>
                        <span class="ml-auto text-slate-300">›</span>
                    </a>
                    <div class="text-center">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($spotlight['name'] ?? 'Nguyễn Tiến Thành') }}&background=e0f2fe&color=0369a1&size=160&bold=true"
                             alt="{{ $spotlight['name'] ?? 'Giáo viên và chuyên gia' }}"
                             class="w-24 h-24 rounded-full mx-auto object-cover ring-4 ring-sky-50">
                        <p class="mt-3 text-sm font-bold text-slate-700">{{ $spotlight['name'] ?? 'Thầy Nguyễn Tiến Thành' }}</p>
                        <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">
                            {{ $spotlight['subject'] ?? 'Giáo viên Tin học' }} - Trường<br>THPT Chuyên Thái Bình
                        </p>
                    </div>
                    <div class="flex items-center justify-between mt-4">
                        <button type="button" class="w-8 h-8 rounded-full border border-slate-200 text-slate-400 hover:text-sky-600 hover:border-sky-200 transition">‹</button>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] text-slate-400 mr-1">1/3</span>
                            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-200"></span>
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-200"></span>
                        </div>
                        <button type="button" class="w-8 h-8 rounded-full border border-slate-200 text-slate-400 hover:text-sky-600 hover:border-sky-200 transition">›</button>
                    </div>
                </div>
            </aside>

            {{-- ═══════════ CỘT GIỮA ═══════════ --}}
            <div class="flex-1 min-w-0 space-y-5">

                {{-- Thanh thông báo hệ thống. DỮ LIỆU MẪU: nối vào bảng thông báo khi cần. --}}
                <div class="rounded-2xl bg-amber-50 border border-amber-200/70 px-4 py-3 flex items-center gap-3">
                    <span class="text-lg shrink-0">📢</span>
                    <span class="text-[13px] font-bold text-amber-700 shrink-0">Thông báo hệ thống</span>
                    <span class="w-px h-5 bg-amber-200 shrink-0"></span>
                    <p class="text-[13px] text-slate-600 truncate">Kỳ thi HSG Tin học cấp tỉnh năm học 2025–2026 sắp diễn ra. Hãy chuẩn bị thật tốt!</p>
                    <div class="ml-auto flex items-center gap-1 shrink-0">
                        <button type="button" class="w-7 h-7 rounded-lg bg-white/70 border border-amber-200 text-amber-500 text-sm">‹</button>
                        <button type="button" class="w-7 h-7 rounded-lg bg-white/70 border border-amber-200 text-amber-500 text-sm">›</button>
                    </div>
                </div>

                {{-- ── Banner chính ── --}}
                <section class="relative rounded-3xl overflow-hidden border border-sky-100 bg-gradient-to-br from-sky-100 via-sky-50 to-blue-50">
                    <div class="absolute inset-y-0 right-0 w-1/2 pointer-events-none" aria-hidden="true">
                        @if ($src = $homeImg('hero.png'))
                            <img src="{{ $src }}" alt="" class="h-full w-full object-cover object-left">
                        @else
                            <div class="h-full w-full bg-gradient-to-l from-sky-200/70 to-transparent flex items-center justify-center text-[7rem] opacity-70 select-none">🎓</div>
                        @endif
                    </div>

                    <div class="relative p-6 lg:p-8">
                        <h1 class="text-4xl lg:text-[42px] font-extrabold text-slate-800 tracking-tight">
                            Ôn Thi <span class="text-amber-400">360</span>
                        </h1>
                        <p class="mt-2 text-lg lg:text-xl font-bold text-sky-700 leading-snug max-w-xl">
                            Luyện thi Tin học – Đồng hành cùng bạn<br>chinh phục mọi mục tiêu
                        </p>

                        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 max-w-xl">
                            @foreach (['HSG lớp 9', 'HSG lớp 12', 'Vào lớp 10 chuyên Tin', 'Tốt nghiệp THPT môn Tin học'] as $tag)
                                <span class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-slate-600">
                                    <span class="w-4 h-4 rounded-full bg-emerald-500 text-white text-[9px] flex items-center justify-center shrink-0">✓</span>
                                    {{ $tag }}
                                </span>
                            @endforeach
                        </div>

                        {{-- Khối chọn mục tiêu. Form GET sang trang Luyện tập, không thêm luồng xử lý mới. --}}
                        <form method="GET" action="{{ route('practice.index') }}"
                              class="mt-6 max-w-3xl rounded-2xl bg-white/95 backdrop-blur border border-white shadow-lg shadow-sky-100/60 p-4">
                            <p class="flex items-center gap-2 text-[13px] font-bold text-slate-700 mb-3">
                                <span class="w-6 h-6 rounded-lg bg-sky-100 text-sky-600 flex items-center justify-center text-[11px]">🎯</span>
                                Chọn mục tiêu học hoặc lộ trình của bạn
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-[1fr_1.3fr_auto] gap-3">
                                <label class="relative block">
                                    <span class="absolute left-3 top-2 text-[10px] text-slate-400">Lớp hiện tại</span>
                                    <select name="grade" class="w-full h-[52px] pt-5 pb-1 pl-9 pr-8 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 appearance-none focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-300">
                                        @foreach ([6, 7, 8, 9, 10, 11, 12] as $g)
                                            <option value="{{ $g }}" @selected($g === 10)>Lớp {{ $g }}</option>
                                        @endforeach
                                    </select>
                                    <span class="absolute left-3 bottom-3 text-sky-500 text-sm pointer-events-none">🎓</span>
                                    <span class="absolute right-3 bottom-3 text-slate-400 pointer-events-none">▾</span>
                                </label>
                                <label class="relative block">
                                    <span class="absolute left-3 top-2 text-[10px] text-slate-400">Mục tiêu học</span>
                                    <select name="goal" class="w-full h-[52px] pt-5 pb-1 pl-9 pr-8 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 appearance-none focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-300">
                                        <option>Luyện thi HSG Tin học lớp 12</option>
                                        <option>Luyện thi HSG Tin học lớp 9</option>
                                        <option>Luyện thi vào lớp 10 chuyên Tin</option>
                                        <option>Ôn thi tốt nghiệp môn Tin học</option>
                                        <option>Học trước chương trình Tin học đủ học</option>
                                    </select>
                                    <span class="absolute left-3 bottom-3 text-sky-500 text-sm pointer-events-none">🎯</span>
                                    <span class="absolute right-3 bottom-3 text-slate-400 pointer-events-none">▾</span>
                                </label>
                                <button type="submit" class="h-[52px] px-7 rounded-xl bg-gradient-to-r from-amber-300 to-amber-400 text-slate-800 text-sm font-extrabold shadow-sm shadow-amber-200 hover:from-amber-400 hover:to-amber-500 transition whitespace-nowrap">
                                    Bắt đầu học ngay →
                                </button>
                            </div>
                        </form>

                        <div class="mt-3 flex flex-wrap gap-1.5 max-w-4xl">
                            @foreach ([
                                'Học trước chương trình Tin học để đủ học',
                                'Luyện thi HSG Tin học lớp 9',
                                'Luyện thi HSG môn Tin học lớp 12',
                                'Luyện thi vào lớp 10 chuyên Tin',
                                'Ôn thi tốt nghiệp môn Tin học',
                            ] as $chip)
                                <a href="{{ route('practice.index') }}" class="px-2.5 py-1.5 rounded-lg bg-white/80 border border-slate-200/80 text-[10.5px] font-medium whitespace-nowrap text-slate-500 hover:text-sky-600 hover:border-sky-200 transition">{{ $chip }}</a>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- ── Chương trình học nổi bật ── --}}
                @php
                    $programs = [
                        ['title' => 'Luyện tập theo chuyên đề', 'body' => 'Bài tập tự luyện bám sát từng chuyên đề', 'cta' => 'Luyện tập ngay', 'href' => route('practice.index'), 'bg' => 'from-sky-50 to-blue-50', 'ring' => 'border-sky-100', 'btn' => 'bg-sky-500 hover:bg-sky-600', 'img' => 'program-1.png', 'emoji' => '💻'],
                        ['title' => 'Sách – Chuyên đề – Bộ đề', 'body' => 'Giáo trình và tài liệu học tập', 'cta' => 'Khám phá', 'href' => route('materials.index'), 'bg' => 'from-emerald-50 to-teal-50', 'ring' => 'border-emerald-100', 'btn' => 'bg-emerald-500 hover:bg-emerald-600', 'img' => 'program-2.png', 'emoji' => '📚'],
                        ['title' => 'Lớp học chuyên nghiệp', 'body' => 'Quản lý lớp, nhận xét học sinh, theo dõi tiến độ', 'cta' => 'Vào lớp học', 'href' => route('courses.index'), 'bg' => 'from-violet-50 to-purple-50', 'ring' => 'border-violet-100', 'btn' => 'bg-violet-500 hover:bg-violet-600', 'img' => 'program-3.png', 'emoji' => '🧑‍🏫'],
                        ['title' => 'Đội ngũ giáo viên', 'body' => 'Giáo viên chất lượng, đồng hành tận tâm', 'cta' => 'Vào lớp tiết', 'href' => route('teachers.index'), 'bg' => 'from-amber-50 to-orange-50', 'ring' => 'border-amber-100', 'btn' => 'bg-amber-400 hover:bg-amber-500', 'img' => 'program-4.png', 'emoji' => '👩‍🏫'],
                        ['title' => 'Chuyên gia & cuộc thi', 'body' => 'Chuyên gia hỗ trợ, cuộc thi uy tín thường xuyên', 'cta' => 'Tìm hiểu', 'href' => route('competitions.index'), 'bg' => 'from-sky-50 to-cyan-50', 'ring' => 'border-cyan-100', 'btn' => 'bg-cyan-500 hover:bg-cyan-600', 'img' => 'program-5.png', 'emoji' => '🏆'],
                    ];
                @endphp
                <section class="rounded-3xl bg-white border border-slate-200/80 p-5 lg:p-6">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center text-lg shrink-0">⚙️</span>
                            <div>
                                <h2 class="text-lg font-extrabold text-slate-800">Chương trình học nổi bật</h2>
                                <p class="text-[13px] text-slate-400 mt-0.5">Kết hợp giữa luyện tập, giáo trình, lớp học, phụ huynh, giáo viên và chuyên gia cao cấp</p>
                            </div>
                        </div>
                        <a href="{{ route('courses.index') }}" class="shrink-0 px-4 py-2 rounded-xl bg-sky-50 text-sky-600 text-[13px] font-bold hover:bg-sky-100 transition">Xem tất cả →</a>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                        @foreach ($programs as $p)
                            <div class="rounded-2xl bg-gradient-to-b {{ $p['bg'] }} border {{ $p['ring'] }} p-4 flex flex-col">
                                <div class="h-24 rounded-xl bg-white/70 flex items-center justify-center mb-3 overflow-hidden">
                                    @if ($src = $homeImg($p['img']))
                                        <img src="{{ $src }}" alt="" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-4xl select-none" aria-hidden="true">{{ $p['emoji'] }}</span>
                                    @endif
                                </div>
                                <p class="text-[13.5px] font-extrabold text-slate-800 leading-snug">{{ $p['title'] }}</p>
                                <p class="text-[11.5px] text-slate-500 mt-1.5 leading-relaxed flex-1">{{ $p['body'] }}</p>
                                <a href="{{ $p['href'] }}" class="mt-3 inline-flex items-center justify-center gap-1 px-3 py-2 rounded-xl {{ $p['btn'] }} text-white text-[12px] font-bold transition">
                                    {{ $p['cta'] }} <span class="text-[11px]">→</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- ── Lộ trình học chuyên nghiệp ── --}}
                @php
                    $steps = [
                        ['no' => 1, 'title' => 'Xác định mục tiêu', 'body' => 'Chọn khối, mục tiêu, lộ trình phù hợp', 'emoji' => '🎯', 'tone' => 'bg-sky-100 text-sky-600'],
                        ['no' => 2, 'title' => 'Lộ trình cá nhân hoá', 'body' => 'Học theo năng lực & mục tiêu', 'emoji' => '🗺️', 'tone' => 'bg-emerald-100 text-emerald-600'],
                        ['no' => 3, 'title' => 'Luyện tập & Học liệu', 'body' => 'Bài tập, giáo trình, chuyên đề', 'emoji' => '💻', 'tone' => 'bg-violet-100 text-violet-600'],
                        ['no' => 4, 'title' => 'Lớp học & Giáo viên', 'body' => 'Học cùng giáo viên, nhận xét', 'emoji' => '👥', 'tone' => 'bg-amber-100 text-amber-600'],
                        ['no' => 5, 'title' => 'Thi & Đánh giá', 'body' => 'Cuộc thi, mô phỏng, báo cáo tiến độ', 'emoji' => '🏆', 'tone' => 'bg-rose-100 text-rose-600'],
                    ];
                @endphp
                <section class="rounded-3xl bg-gradient-to-br from-sky-50 via-white to-blue-50 border border-sky-100 p-5 lg:p-6">
                    <div class="flex items-start gap-3 mb-5">
                        <span class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg shrink-0">⏱️</span>
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-800">Lộ trình học chuyên nghiệp</h2>
                            <p class="text-[13px] text-slate-400 mt-0.5">Định hướng rõ ràng – Tiết kiệm thời gian – Bám sát chương trình thi</p>
                        </div>
                    </div>

                    <div class="flex flex-col lg:flex-row items-stretch gap-2">
                        @foreach ($steps as $i => $step)
                            <div class="flex-1 text-center px-2">
                                <div class="w-14 h-14 mx-auto rounded-2xl {{ $step['tone'] }} flex items-center justify-center text-2xl">{{ $step['emoji'] }}</div>
                                <p class="mt-3 text-[13px] font-extrabold text-slate-700">{{ $step['no'] }}. {{ $step['title'] }}</p>
                                <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">{{ $step['body'] }}</p>
                            </div>
                            @if ($i < count($steps) - 1)
                                <div class="hidden lg:flex items-start pt-5 text-slate-300 text-xl shrink-0" aria-hidden="true">›</div>
                            @endif
                        @endforeach
                    </div>
                </section>

                {{-- ── 5 nhóm đối tượng ── --}}
                @php
                    $audiences = [
                        ['emoji' => '🧑‍🎓', 'title' => 'Học sinh tự luyện', 'body' => 'Luyện tập theo chuyên đề', 'href' => route('practice.index'), 'cls' => 'bg-sky-50 border-sky-100 text-sky-700'],
                        ['emoji' => '👨‍👩‍👧', 'title' => 'Phụ huynh đồng hành', 'body' => 'Theo dõi tiến độ và kết quả', 'href' => route('info.index'), 'cls' => 'bg-violet-50 border-violet-100 text-violet-700'],
                        ['emoji' => '🏫', 'title' => 'Lớp học chuyên nghiệp', 'body' => 'Quản lý lớp, nhận xét học sinh', 'href' => route('courses.index'), 'cls' => 'bg-emerald-50 border-emerald-100 text-emerald-700'],
                        ['emoji' => '🧑‍🏫', 'title' => 'Giáo viên & chuyên gia', 'body' => 'Đồng hành, hỗ trợ cao cấp', 'href' => route('teachers.index'), 'cls' => 'bg-blue-50 border-blue-100 text-blue-700'],
                        ['emoji' => '🏆', 'title' => 'Hệ thống & cuộc thi', 'body' => 'Học liệu chuẩn, đấu trường uy tín', 'href' => route('competitions.index'), 'cls' => 'bg-amber-50 border-amber-100 text-amber-700'],
                    ];
                @endphp
                <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    @foreach ($audiences as $a)
                        <a href="{{ $a['href'] }}" class="rounded-2xl border {{ $a['cls'] }} px-4 py-3.5 flex items-center gap-3 hover:shadow-md transition">
                            <span class="text-2xl shrink-0">{{ $a['emoji'] }}</span>
                            <span class="min-w-0">
                                <span class="block text-[12px] font-extrabold leading-tight">{{ $a['title'] }}</span>
                                <span class="block text-[10.5px] text-slate-500 mt-0.5 leading-tight">{{ $a['body'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </section>
            </div>

            {{-- ═══════════ CỘT PHẢI ═══════════ --}}
            <aside class="hidden xl:block w-[330px] shrink-0 space-y-4">

                {{-- Hành trình học. DỮ LIỆU MẪU đúng theo design. --}}
                <div class="rounded-2xl bg-white border border-slate-200/80 overflow-hidden">
                    <div class="bg-gradient-to-r from-sky-50 to-blue-50 px-4 py-3.5 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-sky-500 text-white flex items-center justify-center text-sm shrink-0">📘</span>
                        <span class="text-[13.5px] font-extrabold text-slate-800 whitespace-nowrap">Hành trình học của bạn</span>
                        <span class="ml-auto px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-[11px] font-bold text-slate-500">Lớp 10 ▾</span>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 h-2.5 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r from-sky-400 to-blue-500" style="width: 65%"></div>
                            </div>
                            <span class="text-[13px] font-extrabold text-sky-600 shrink-0">65%</span>
                        </div>

                        <a href="{{ route('practice.index') }}" class="mt-4 flex items-center gap-3 rounded-xl bg-sky-50/70 border border-sky-100 p-3 hover:bg-sky-50 transition">
                            <span class="w-9 h-9 rounded-lg bg-white text-sky-600 flex items-center justify-center shrink-0">👨‍💻</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13px] font-bold text-slate-700">Tiếp tục học</span>
                                <span class="block text-[11px] text-slate-400 truncate">Bài 12: Cấu trúc dữ liệu và giải thuật</span>
                            </span>
                            <span class="text-slate-300 shrink-0">›</span>
                        </a>

                        <div class="grid grid-cols-3 gap-2.5 mt-3">
                            @foreach ([
                                ['n' => 12, 'label' => 'Bài đã hoàn thành', 'emoji' => '📦', 'cls' => 'bg-rose-50 border-rose-100 text-rose-600'],
                                ['n' => 8, 'label' => 'Đang học', 'emoji' => '📗', 'cls' => 'bg-emerald-50 border-emerald-100 text-emerald-600'],
                                ['n' => 3, 'label' => 'Chưa hoàn thành', 'emoji' => '🏅', 'cls' => 'bg-amber-50 border-amber-100 text-amber-600'],
                            ] as $tile)
                                <div class="rounded-xl border {{ $tile['cls'] }} p-2.5 text-center">
                                    <span class="text-base">{{ $tile['emoji'] }}</span>
                                    <p class="text-[15px] font-extrabold leading-none mt-1">{{ $tile['n'] }}</p>
                                    <p class="text-[9px] text-slate-400 mt-1 leading-tight whitespace-nowrap">{{ $tile['label'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Thông báo quan trọng. DỮ LIỆU MẪU. --}}
                <div class="rounded-2xl bg-white border border-slate-200/80 p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-xs">📣</span>
                        <span class="text-[13.5px] font-extrabold text-slate-800 whitespace-nowrap">Thông báo quan trọng</span>
                        <a href="{{ route('info.index') }}" class="ml-auto shrink-0 text-[10px] font-bold text-sky-600 whitespace-nowrap">Xem tất cả →</a>
                    </div>
                    <div class="space-y-3">
                        @foreach ([
                            ['emoji' => '⭐', 'text' => 'Kỳ thi HSG Tin học cấp tỉnh sắp diễn ra', 'time' => '3 ngày trước'],
                            ['emoji' => '📗', 'text' => 'Lịch học lớp Toán Tin 10A1 tuần này', 'time' => '5 giờ trước'],
                            ['emoji' => '📕', 'text' => 'Bài tập mới: Cấu trúc dữ liệu cơ bản', 'time' => '1 ngày trước'],
                            ['emoji' => '🏅', 'text' => 'Bạn đã nhận được một huy hiệu mới!', 'time' => '2 ngày trước'],
                        ] as $n)
                            <div class="flex items-start gap-2.5">
                                <span class="text-sm shrink-0 mt-0.5">{{ $n['emoji'] }}</span>
                                <p class="text-[12px] text-slate-600 leading-snug flex-1">{{ $n['text'] }}</p>
                                <span class="text-[10px] text-slate-300 shrink-0 whitespace-nowrap">{{ $n['time'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Top học sinh xuất sắc. DỮ LIỆU MẪU — trang Bảng xếp hạng có số liệu thật. --}}
                <div class="rounded-2xl bg-white border border-slate-200/80 p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-xs">🏆</span>
                        <span class="text-[13.5px] font-extrabold text-slate-800 whitespace-nowrap">Top học sinh xuất sắc</span>
                        <a href="{{ route('leaderboard.index') }}" class="ml-auto shrink-0 text-[10px] font-bold text-sky-600 whitespace-nowrap">Xem bảng xếp hạng →</a>
                    </div>
                    <div class="space-y-2.5">
                        @foreach ([
                            ['no' => 1, 'name' => 'Nguyễn Minh Anh', 'class' => '10A1', 'score' => '9.8', 'medal' => 'bg-amber-400'],
                            ['no' => 2, 'name' => 'Trần Đức Duy', 'class' => '10A2', 'score' => '9.6', 'medal' => 'bg-slate-300'],
                            ['no' => 3, 'name' => 'Lê Phương Thảo', 'class' => '10A3', 'score' => '9.5', 'medal' => 'bg-orange-300'],
                            ['no' => 4, 'name' => 'Phạm Hoàng Nam', 'class' => '10A2', 'score' => '9.3', 'medal' => 'bg-slate-200'],
                            ['no' => 5, 'name' => 'Vũ Thị Mai', 'class' => '10A2', 'score' => '9.2', 'medal' => 'bg-slate-200'],
                        ] as $row)
                            <div class="flex items-center gap-2.5">
                                <span class="w-5 h-5 rounded-full {{ $row['medal'] }} text-white text-[10px] font-bold flex items-center justify-center shrink-0">{{ $row['no'] }}</span>
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($row['name']) }}&background=e0f2fe&color=0369a1&size=64&bold=true"
                                     alt="{{ $row['name'] }}" class="w-7 h-7 rounded-full object-cover shrink-0">
                                <span class="text-[12px] font-semibold text-slate-700 truncate flex-1">{{ $row['name'] }}</span>
                                <span class="text-[11px] text-slate-400 shrink-0">{{ $row['class'] }}</span>
                                <span class="text-[12px] font-extrabold text-sky-600 shrink-0 w-8 text-right">{{ $row['score'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Thẻ cổ vũ --}}
                <a href="{{ route('competitions.index') }}" class="block rounded-2xl bg-gradient-to-br from-amber-50 to-yellow-100 border border-amber-200/70 p-5 text-center hover:shadow-md transition">
                    <span class="text-4xl">🏆</span>
                    <p class="mt-2 text-[15px] font-extrabold text-amber-700 italic leading-snug">Cùng chinh phục<br>thành tích cao hơn!</p>
                </a>
            </aside>
        </div>
    </div>
@endsection
