@extends('layouts.student')

@section('title', 'Tổng quan')
@section('page-title', 'Tổng quan')

@section('content')
    {{--
      SỬA 24/9 (khách: "xem source mới nhất trong học sinh, xây lại trang tổng quan cho giống
      UI source mới, logic vẫn giữ nguyên").

      DỰNG LẠI THEO education-main/src/components/RoleWorkspace.jsx — hàm StudentOverview() và
      Hero()/Stat()/CardTitle()/Progress() dùng kèm. Bám đúng thứ tự khối của bản mẫu:

        1. Hero ảnh nền + lớp phủ chuyển sắc xanh
        2. Thẻ "Tiến độ cá nhân" nền #f7fbfd, phần trăm to bên phải, thanh tiến độ
        3. Dải 3 thẻ số liệu (Stat): nhãn IN HOA nhỏ, số to, ô icon viền màu ở góc phải
        4. Lưới [1.55fr .95fr]: trái "Tiếp tục học" + "Kết quả gần đây", phải "Sắp tới"

      LOGIC KHÔNG ĐỔI: vẫn đúng các khoá của Student\DashboardService::buildDashboardData()
      (name, hasAnyClass, todayTasks, upcoming, classProgress, recentResults, notifications,
      classCount, averageProgress, submittedCount, todaySessionCount) và đúng các route cũ.
      Không thêm truy vấn nào ở view.
    --}}
    @php
        $name = $name ?? (auth()->user()->name ?? 'bạn');
        $hasAnyClass = $hasAnyClass ?? false;
        $todayTasks = $todayTasks ?? [];
        $upcoming = $upcoming ?? [];
        $classProgress = $classProgress ?? [];
        $recentResults = $recentResults ?? [];
        $notifications = $notifications ?? [];
        $classCount = $classCount ?? count($classProgress);
        $averageProgress = $averageProgress ?? 0;
        $submittedCount = $submittedCount ?? count($recentResults);
        $todaySessionCount = $todaySessionCount ?? 0;

        // Lớp đang có tiến độ cao nhất — làm "việc tiếp theo" của thẻ lớn bên trái, đúng vai
        // trò khối "Bài tiếp theo" của bản mẫu. Không có lớp nào thì thẻ đổi thành lối vào
        // Luyện tập, chứ không để một ô trống.
        $focusClass = null;
        foreach ($classProgress as $cp) {
            if ($focusClass === null || ($cp['percent'] ?? 0) > ($focusClass['percent'] ?? 0)) {
                $focusClass = $cp;
            }
        }

        // Stat() của bản mẫu: 4 bộ màu blue/emerald/amber/violet.
        $statTones = [
            'blue' => 'bg-blue-50 text-blue-600 border-blue-100',
            'emerald' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
            'amber' => 'bg-amber-50 text-amber-600 border-amber-100',
            'violet' => 'bg-violet-50 text-violet-600 border-violet-100',
        ];

        $stats = [
            ['Đã nộp', $submittedCount, 'Bài đã nộp và ghi nhận', 'file-check-2', 'blue'],
            ['Lớp đang học', $classCount, $classCount > 0 ? 'Lớp bạn đang theo' : 'Chưa tham gia lớp nào', 'graduation-cap', 'emerald'],
            ['Buổi hôm nay', $todaySessionCount, $todaySessionCount > 0 ? 'Nhớ vào lớp đúng giờ' : 'Hôm nay không có buổi nào', 'calendar-days', 'amber'],
        ];
    @endphp

    <div class="student-dashboard space-y-4">

        {{-- ══════ 1. HERO ══════ --}}
        <section class="student-dashboard-hero relative min-w-0 overflow-hidden rounded-3xl border border-sky-100 bg-[#0d5faf] shadow-[0_7px_20px_rgba(0,95,180,.09)]">
            <img src="{{ asset('assets/workspace-student-hero.jpg') }}" alt="" decoding="async"
                 class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-r from-[#0759a8] via-[#0976c9] to-transparent opacity-90"></div>

            <div class="student-dashboard-hero-inner relative flex flex-col gap-4 px-5 py-5 text-white sm:flex-row sm:items-end sm:justify-between sm:px-7">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[.14em] text-sky-100">Hành trình học tập của bạn</p>
                    <h1 class="mt-1 break-words text-xl font-bold tracking-tight text-white sm:text-2xl">Chào {{ $name }}, sẵn sàng bứt phá?</h1>
                    <p class="mt-1 max-w-xl text-xs text-sky-50">Từng bước rõ ràng, mọi kết quả đều được lưu lại để bạn tiếp tục đúng lúc.</p>
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                    <a href="{{ route('student.practice.index') }}"
                       class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-white px-3.5 py-2 text-[12px] font-bold text-blue-700 shadow-sm transition hover:bg-sky-50">
                        <x-lucide name="pencil" class="h-3.5 w-3.5" />Luyện tập ngay
                    </a>
                    <a href="{{ route('student.courses.index') }}"
                       class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-white/25 bg-white/10 px-3.5 py-2 text-[12px] font-bold text-white transition hover:bg-white/20">
                        <x-lucide name="book-open" class="h-3.5 w-3.5" />Khóa học của tôi
                    </a>
                </div>
            </div>
        </section>

        {{-- ══════ 2. TIẾN ĐỘ CÁ NHÂN ══════ --}}
        <section class="student-dashboard-summary rounded-3xl border border-sky-100 p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[.12em] text-blue-600">Tiến độ cá nhân</p>
                    <h2 class="mt-1 text-base font-bold text-slate-800">
                        @if ($hasAnyClass)
                            {{ $submittedCount }} bài đã nộp · {{ $classCount }} lớp đang theo
                        @else
                            Bạn chưa tham gia lớp nào
                        @endif
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        @if ($hasAnyClass)
                            Tiến độ tính theo số buổi đã học của các lớp bạn đang theo.
                        @else
                            Luyện tập bài công khai ngay, hoặc mở trang Lớp học rồi bấm "Đăng ký học".
                        @endif
                    </p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-[11px] font-bold text-slate-400">Tiến độ trung bình</p>
                    <strong class="mt-1 block text-2xl font-black text-blue-700">{{ $averageProgress }}%</strong>
                </div>
            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-white">
                <div class="h-full rounded-full bg-blue-600" style="width: {{ max(0, min(100, $averageProgress)) }}%"></div>
            </div>

            <div class="mt-2 flex flex-wrap justify-between gap-2 text-[11px] font-medium text-slate-500">
                <span>{{ $classCount }} lớp đang theo</span>
                <span>{{ $todaySessionCount > 0 ? $todaySessionCount.' buổi học hôm nay' : 'Hôm nay không có buổi nào' }}</span>
            </div>
        </section>

        {{-- ══════ 3. DẢI THẺ SỐ LIỆU ══════ --}}
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ($stats as [$label, $value, $note, $icon, $tone])
                <div class="rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_2px_8px_rgba(0,90,180,.04)]">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                            <p class="mt-1 text-xl font-black text-slate-800">{{ $value }}</p>
                        </div>
                        <span class="rounded-xl border p-2 {{ $statTones[$tone] }}"><x-lucide :name="$icon" class="h-4 w-4" /></span>
                    </div>
                    <p class="mt-2 text-[11px] font-medium text-slate-500">{{ $note }}</p>
                </div>
            @endforeach
        </div>

        {{-- ══════ 4. HAI CỘT ══════ --}}
        <div class="student-dashboard-grid">
            <div class="min-w-0 space-y-4">

                {{-- Tiếp tục học — khuôn "Bài tiếp theo" của bản mẫu --}}
                <section class="rounded-3xl border border-sky-100 bg-white p-4 sm:p-5">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="rounded-lg bg-blue-50 p-1.5 text-blue-600"><x-lucide name="clipboard-list" class="h-3.5 w-3.5" /></span>
                            <h2 class="text-sm font-bold text-slate-800">Tiếp tục học</h2>
                        </div>
                        <a href="{{ route('student.courses.index') }}" class="text-xs font-bold text-blue-600 hover:underline">Xem khóa học <x-lucide name="chevron-right" class="inline h-3 w-3" /></a>
                    </div>

                    <div class="student-dashboard-next flex flex-col gap-4 rounded-2xl border border-sky-100 p-4 sm:flex-row sm:items-center">
                        <img src="{{ asset('assets/course-img-1.png') }}" alt="" decoding="async"
                             class="h-16 w-16 shrink-0 rounded-xl object-cover sm:h-20 sm:w-20">
                        <div class="min-w-0 flex-1">
                            @if ($focusClass !== null)
                                <p class="text-sm font-bold text-slate-800">{{ $focusClass['name'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">Lớp bạn đang theo sát nhất</p>
                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-blue-500" style="width: {{ max(0, min(100, $focusClass['percent'])) }}%"></div>
                                </div>
                                <div class="mt-2 flex flex-wrap justify-between gap-2 text-[11px] font-medium text-slate-500">
                                    <span>{{ $focusClass['percent'] }}% số buổi đã học</span>
                                    <span>{{ $classCount }} lớp đang theo</span>
                                </div>
                                <a href="{{ route('student.courses.index') }}"
                                   class="mt-3 inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">
                                    <x-lucide name="chevron-right" class="h-3.5 w-3.5" />Vào lớp học
                                </a>
                            @else
                                <p class="text-sm font-bold text-slate-800">Bắt đầu bằng một bài luyện tập</p>
                                <p class="mt-1 text-xs text-slate-500">Chưa có lớp nào — kho bài công khai luôn mở, làm được ngay không cần đăng ký.</p>
                                <a href="{{ route('student.practice.index') }}"
                                   class="mt-3 inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">
                                    <x-lucide name="pencil" class="h-3.5 w-3.5" />Luyện tập ngay
                                </a>
                            @endif
                        </div>
                    </div>

                    @if (count($todayTasks) > 0)
                        <div class="mt-3 divide-y divide-slate-100">
                            @foreach ($todayTasks as $t)
                                <div class="flex items-center gap-3 py-2.5">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-600"><x-lucide name="clock" class="h-4 w-4" /></span>
                                    <p class="min-w-0 flex-1 truncate text-xs font-semibold text-slate-700">{{ $t['title'] ?? '' }}</p>
                                    <a href="{{ route('student.practice.index') }}" class="shrink-0 text-[11px] font-bold text-blue-600 hover:underline">Làm ngay</a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                {{-- Kết quả gần đây --}}
                <section class="rounded-3xl border border-sky-100 bg-white p-4 sm:p-5">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="rounded-lg bg-blue-50 p-1.5 text-blue-600"><x-lucide name="trophy" class="h-3.5 w-3.5" /></span>
                            <h2 class="text-sm font-bold text-slate-800">Kết quả gần đây</h2>
                        </div>
                        <a href="{{ route('student.practice.index', ['tab' => 'history']) }}" class="text-xs font-bold text-blue-600 hover:underline">Xem tất cả <x-lucide name="chevron-right" class="inline h-3 w-3" /></a>
                    </div>

                    @if (count($recentResults) > 0)
                        <div class="space-y-2">
                            @foreach ($recentResults as $r)
                                <div class="flex flex-wrap items-center gap-3 rounded-xl bg-slate-50 p-3">
                                    <span @class([
                                        'h-2 w-2 shrink-0 rounded-full',
                                        'bg-emerald-500' => ($r['tone'] ?? '') === 'success',
                                        'bg-amber-500' => ($r['tone'] ?? '') !== 'success',
                                    ])></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-bold text-slate-800">{{ $r['title'] }}</p>
                                        <p class="mt-0.5 text-[10px] text-slate-500">{{ $r['time'] ?? '' }}</p>
                                    </div>
                                    <span class="text-xs font-black text-blue-700">{{ $r['score'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="rounded-xl bg-slate-50 p-4 text-center text-[12px] text-slate-500">Chưa có bài nào được nộp. Làm xong một bài là kết quả hiện ở đây.</p>
                    @endif
                </section>
            </div>

            {{-- Sắp tới --}}
            <aside class="min-w-0 space-y-4">
                <section class="rounded-3xl border border-sky-100 bg-white p-4 sm:p-5">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="rounded-lg bg-blue-50 p-1.5 text-blue-600"><x-lucide name="calendar-days" class="h-3.5 w-3.5" /></span>
                            <h2 class="text-sm font-bold text-slate-800">Sắp tới</h2>
                        </div>
                        <a href="{{ route('student.schedule.index') }}" class="text-xs font-bold text-blue-600 hover:underline">Lịch học <x-lucide name="chevron-right" class="inline h-3 w-3" /></a>
                    </div>

                    @if (count($upcoming) > 0)
                        <div class="student-dashboard-list divide-y divide-slate-100">
                            @foreach ($upcoming as $u)
                                @php($parts = explode(' ', (string) ($u['time'] ?? ''), 2))
                                <div class="flex items-center gap-3 py-3">
                                    <div class="w-12 shrink-0 rounded-xl border border-sky-100 bg-sky-50 px-1.5 py-1.5 text-center text-blue-700">
                                        <p class="text-[10px] font-bold">{{ ($u['isToday'] ?? false) ? 'Hôm nay' : ($parts[0] ?? '—') }}</p>
                                        <p class="mt-0.5 text-xs font-black">{{ $parts[1] ?? '—' }}</p>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold leading-snug text-slate-700">{{ $u['title'] }}</p>
                                        <p class="mt-0.5 truncate text-[10px] text-slate-400">{{ $u['meta'] ?? '' }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="rounded-xl bg-slate-50 p-4 text-center text-[12px] text-slate-500">Chưa có buổi học nào được xếp lịch.</p>
                    @endif
                </section>

                @if (count($notifications) > 0)
                    <section class="rounded-3xl border border-sky-100 bg-white p-4 sm:p-5">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="rounded-lg bg-blue-50 p-1.5 text-blue-600"><x-lucide name="bell" class="h-3.5 w-3.5" /></span>
                            <h2 class="text-sm font-bold text-slate-800">Thông báo</h2>
                        </div>
                        <div class="student-dashboard-list divide-y divide-slate-100">
                            @foreach ($notifications as $n)
                                <div class="py-3">
                                    <p class="text-xs font-bold text-slate-800">{{ $n['title'] ?? '' }}</p>
                                    <p class="mt-0.5 text-[10px] text-slate-500">{{ $n['time'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('student.notifications') }}" class="mt-3 inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:underline">
                            Xem tất cả <x-lucide name="chevron-right" class="h-3 w-3" />
                        </a>
                    </section>
                @endif
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- Nền riêng của thẻ "Tiến độ cá nhân" và hiệu ứng rê chuột của thẻ "Tiếp tục học" — chép
         từ education-main/src/index.css (.student-dashboard-summary / .student-dashboard-next).
         Viết CSS thường vì bản CSS trên máy chủ là bản build sẵn, class mới sẽ không có. --}}
    <style>
        /* Chép từ education-main/src/index.css */
        .student-dashboard-summary { background-color: #F7FBFD; }
        .student-dashboard-next {
            background-color: #F6FAFC;
            transition: border-color 160ms ease, background-color 160ms ease;
        }
        .student-dashboard-next:hover { border-color: #B9DBE3; background-color: #F2F9FB; }

        /* Mấy tiện ích Tailwind bản mẫu dùng nhưng CHƯA CÓ trong bản CSS build sẵn trên máy
           chủ (VPS không chạy được vite). Viết tay ở đây để giữ đúng bố cục bản mẫu, thay vì
           đổi sang class khác rồi lệch thiết kế. */
        .student-dashboard-hero { min-height: 188px; }

        .student-dashboard-grid { display: grid; gap: 1rem; }
        @media (min-width: 1280px) {
            .student-dashboard-grid { grid-template-columns: 1.55fr .95fr; }
        }

        @media (min-width: 640px) {
            .student-dashboard-hero-inner { padding-top: 1.5rem; padding-bottom: 1.5rem; }
        }

        .student-dashboard-list > :first-child { padding-top: .25rem; }
        .student-dashboard-list > :last-child { padding-bottom: .25rem; }
    </style>
@endpush
