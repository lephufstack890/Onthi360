@extends('layouts.student')

@section('title', 'Tổng quan')
@section('page-title', 'Tổng quan')

@section('content')
    {{--
      SỬA 23/9 (khách: "giao diện bảng điều khiển cá nhân còn xấu, biểu tượng dùng emoji cẩu
      thả — dùng SVG; thống nhất cách trình bày với trang lớp học và cuộc thi").

      DỰNG LẠI UI, KHÔNG đổi logic: vẫn đúng các khoá dữ liệu của
      Student\DashboardService::buildDashboardData(), không thêm truy vấn ở view.

      Ngôn ngữ thiết kế bê nguyên từ student/competitions/room.blade.php và
      student/classes/show.blade.php: thẻ đầu trang nền chuyển sắc xanh, dải 4 thẻ số liệu bo
      2xl có ô icon vuông, các khối nội dung nền trắng viền sky-100, nhãn nhỏ IN HOA giãn chữ,
      chữ chính #123B68 / phụ #61798B. Mọi biểu tượng là <x-lucide> (SVG), không còn emoji.
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

        // Mỗi thẻ số liệu: [nhãn, số, chú thích, icon lucide, bộ màu] — cùng khuôn với 4 thẻ
        // đầu màn "Không gian thi" để hai trang nhìn là một nhà.
        $stats = [
            ['Lớp đang học', $classCount, $classCount > 0 ? 'lớp bạn đang theo' : 'chưa tham gia lớp nào', 'graduation-cap', 'sky'],
            ['Tiến độ trung bình', $averageProgress.'%', 'số buổi đã học của các lớp', 'target', 'emerald'],
            ['Bài đã nộp', $submittedCount, 'tính từ lúc bắt đầu học', 'file-check-2', 'violet'],
            ['Buổi học hôm nay', $todaySessionCount, $todaySessionCount > 0 ? 'nhớ vào lớp đúng giờ' : 'hôm nay không có buổi nào', 'clock', 'amber'],
        ];

        $statTones = [
            'sky' => ['border-sky-100', 'to-[#EAF5F8]', 'text-[#61798B]', 'bg-[#DDF2F5]', 'text-[#126F91]'],
            'emerald' => ['border-emerald-100', 'to-[#EDF8F3]', 'text-[#5E7B6E]', 'bg-[#DFF2E9]', 'text-[#2F8F6F]'],
            'violet' => ['border-violet-100', 'to-[#F4F0FA]', 'text-[#7657A5]', 'bg-[#EEE8F7]', 'text-[#7657A5]'],
            'amber' => ['border-amber-100', 'to-[#FFF8E7]', 'text-[#8F7A58]', 'bg-[#FFF0C7]', 'text-[#B57A2B]'],
        ];
    @endphp

    {{-- ══════ THẺ ĐẦU TRANG ══════ --}}
    <header class="relative overflow-hidden rounded-2xl border border-[#0B4E6B] bg-gradient-to-r from-[#064C99] via-[#0066CC] to-[#0891B2] px-4 py-4 shadow-[0_2px_10px_rgba(18,59,104,0.08)] sm:px-5 sm:py-5">
        <span class="pointer-events-none absolute -right-7 -top-7 h-40 w-40 rounded-full bg-white/10"></span>
        <span class="pointer-events-none absolute -bottom-16 right-16 h-36 w-32 rounded-full bg-white/5"></span>

        <div class="relative flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.08em] text-sky-50">
                    <x-lucide name="sparkles" class="h-3 w-3" />Không gian học tập
                </span>
                <h2 class="mt-2 text-lg font-black tracking-tight text-white sm:text-xl">
                    @if ($hasAnyClass)
                        Chào {{ $name }}, chúc bạn một ngày học hiệu quả
                    @else
                        Chào {{ $name }}, bắt đầu hành trình học của bạn nhé
                    @endif
                </h2>
                <p class="mt-1 max-w-xl text-[12px] leading-relaxed text-sky-50">
                    @if ($hasAnyClass)
                        Cứ từng bước một — theo dõi tiến độ lớp, lịch buổi tới và kết quả gần đây ngay bên dưới.
                    @else
                        Bạn chưa tham gia lớp nào. Luyện tập bài công khai ngay, hoặc mở trang Lớp học rồi bấm "Đăng ký học" — giáo viên duyệt là vào học được.
                    @endif
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('student.practice.index') }}"
                   class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-white px-3.5 py-2 text-[12px] font-bold text-[#0B4E6B] shadow-sm transition hover:bg-sky-50">
                    <x-lucide name="pencil" class="h-3.5 w-3.5" />Luyện tập ngay
                </a>
                <a href="{{ route('student.courses.index') }}"
                   class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-white/25 bg-white/10 px-3.5 py-2 text-[12px] font-bold text-white transition hover:bg-white/20">
                    <x-lucide name="graduation-cap" class="h-3.5 w-3.5" />{{ $hasAnyClass ? 'Lớp của tôi' : 'Tìm lớp/khoá học' }}
                </a>
            </div>
        </div>
    </header>

    {{-- ══════ DẢI THẺ SỐ LIỆU ══════ --}}
    <section class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($stats as [$label, $value, $note, $icon, $tone])
            @php([$border, $bgTo, $labelColor, $tileBg, $tileText] = $statTones[$tone])
            <article class="group relative overflow-hidden rounded-2xl border {{ $border }} bg-gradient-to-br from-white via-white {{ $bgTo }} p-3.5 shadow-[0_4px_14px_rgba(28,91,121,0.06)]">
                <span class="pointer-events-none absolute -right-7 -top-7 h-20 w-20 rounded-full bg-white/60 transition duration-200 group-hover:scale-110"></span>
                <div class="relative flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-[.08em] {{ $labelColor }}">{{ $label }}</p>
                        <p class="mt-1.5 truncate text-2xl font-semibold leading-none tracking-tight text-[#123B68]">{{ $value }}</p>
                        <p class="mt-1.5 truncate text-[11px] {{ $labelColor }}">{{ $note }}</p>
                    </div>
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $tileBg }} {{ $tileText }} shadow-sm">
                        <x-lucide :name="$icon" class="h-4 w-4" />
                    </span>
                </div>
            </article>
        @endforeach
    </section>

    {{-- ══════ VIỆC CẦN LÀM HÔM NAY ══════ --}}
    <section class="mt-3 rounded-2xl border border-sky-100 bg-white p-3 shadow-[0_3px_12px_rgba(28,91,121,0.05)] sm:p-4">
        <div class="flex items-center justify-between gap-2">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[.12em] text-[#2D7FA3]">Hôm nay</p>
                <h2 class="mt-1 text-sm font-black text-[#123B68]">Việc cần làm</h2>
            </div>
            @if (count($todayTasks) > 0)
                <span class="rounded-full bg-sky-50 px-2.5 py-1 text-[10px] font-bold text-[#126F91]">{{ count($todayTasks) }} việc</span>
            @endif
        </div>

        @if (count($todayTasks) > 0)
            <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
                @foreach ($todayTasks as $t)
                    <div class="flex items-start gap-3 rounded-xl border border-sky-100 bg-[#F8FBFC] p-3">
                        <x-ws.icon-tile :icon="$t['icon'] ?? 'check-circle'" :emoji="$t['emoji'] ?? null" :tone="$t['tone'] ?? 'blue'" />
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] font-bold leading-snug text-[#123B68]">{{ $t['title'] }}</p>
                            <p class="mt-1 text-[11px] text-[#61798B]">{{ $t['meta'] }}</p>
                            <a href="{{ route('student.practice.index') }}" class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-[#126F91] hover:underline">
                                {{ $t['cta'] }}<x-lucide name="chevron-right" class="h-3 w-3" />
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="mt-3 flex flex-col items-center gap-2 rounded-xl border border-dashed border-sky-200 bg-[#F8FBFC] px-4 py-6 text-center">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#DFF2E9] text-[#2F8F6F]"><x-lucide name="check" class="h-5 w-5" /></span>
                <p class="text-[13px] font-bold text-[#123B68]">Không có việc nào cần làm hôm nay</p>
                <p class="text-[11px] text-[#61798B]">Cứ thư giãn, hoặc luyện thêm vài bài cho chắc tay.</p>
                <a href="{{ route('student.practice.index') }}"
                   class="mt-1 inline-flex min-h-9 items-center gap-1.5 rounded-xl bg-[#126F91] px-3.5 py-2 text-[12px] font-bold text-white transition hover:bg-[#0F607E]">
                    <x-lucide name="pencil" class="h-3.5 w-3.5" />Luyện tập thêm
                </a>
            </div>
        @endif
    </section>

    {{-- ══════ 2 CỘT NỘI DUNG ══════ --}}
    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">

            {{-- Tiến độ lớp/khoá --}}
            <section class="rounded-2xl border border-sky-100 bg-white p-3 shadow-[0_3px_12px_rgba(28,91,121,0.05)] sm:p-4">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-[#DDF2F5] text-[#126F91]"><x-lucide name="trending-up" class="h-4 w-4" /></span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[.12em] text-[#2D7FA3]">Học tập</p>
                        <h2 class="text-sm font-black text-[#123B68]">Tiến độ lớp/khoá</h2>
                    </div>
                </div>

                @if (count($classProgress) > 0)
                    <div class="mt-3 space-y-3">
                        @foreach ($classProgress as $cp)
                            <x-ws.progress-bar :percent="$cp['percent']" :label="$cp['name']" tone="brand" />
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 rounded-xl border border-dashed border-sky-200 bg-[#F8FBFC] px-3 py-4 text-center text-[12px] text-[#61798B]">
                        Chưa có lớp nào để theo dõi tiến độ.
                    </p>
                @endif
            </section>

            {{-- Lịch sắp tới --}}
            <section class="rounded-2xl border border-sky-100 bg-white p-3 shadow-[0_3px_12px_rgba(28,91,121,0.05)] sm:p-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-[#FFF0C7] text-[#B57A2B]"><x-lucide name="calendar-days" class="h-4 w-4" /></span>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[.12em] text-[#2D7FA3]">Lịch học</p>
                            <h2 class="text-sm font-black text-[#123B68]">Buổi sắp tới</h2>
                        </div>
                    </div>
                    <a href="{{ route('student.schedule.index') }}" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#126F91] hover:underline">
                        Xem lịch đầy đủ<x-lucide name="chevron-right" class="h-3 w-3" />
                    </a>
                </div>

                @if (count($upcoming) > 0)
                    <ul class="mt-3 divide-y divide-[#EEF4F7]">
                        @foreach ($upcoming as $u)
                            <li class="flex items-center gap-3 py-2.5">
                                <span @class([
                                    'shrink-0 rounded-lg px-2 py-1 text-[11px] font-black',
                                    'bg-[#FFF0C7] text-[#B57A2B]' => $u['isToday'] ?? false,
                                    'bg-[#EAF5F8] text-[#126F91]' => ! ($u['isToday'] ?? false),
                                ])>{{ $u['time'] }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[13px] font-bold text-[#123B68]">{{ $u['title'] }}</p>
                                    <p class="truncate text-[11px] text-[#61798B]">{{ $u['meta'] }}</p>
                                </div>
                                @if ($u['isToday'] ?? false)
                                    <span class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700">Hôm nay</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 rounded-xl border border-dashed border-sky-200 bg-[#F8FBFC] px-3 py-4 text-center text-[12px] text-[#61798B]">
                        Chưa có buổi học nào sắp tới.
                    </p>
                @endif
            </section>
        </div>

        <div class="space-y-3">

            {{-- Kết quả gần đây --}}
            <section class="rounded-2xl border border-sky-100 bg-white p-3 shadow-[0_3px_12px_rgba(28,91,121,0.05)] sm:p-4">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-[#DFF2E9] text-[#2F8F6F]"><x-lucide name="medal" class="h-4 w-4" /></span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[.12em] text-[#2D7FA3]">Thành tích</p>
                        <h2 class="text-sm font-black text-[#123B68]">Kết quả gần đây</h2>
                    </div>
                </div>

                @if (count($recentResults) > 0)
                    <ul class="mt-3 divide-y divide-[#EEF4F7]">
                        @foreach ($recentResults as $r)
                            <li class="flex items-center justify-between gap-2 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-[13px] font-bold text-[#123B68]">{{ $r['title'] }}</p>
                                    <p class="text-[11px] text-[#61798B]">{{ $r['time'] }}</p>
                                </div>
                                <x-ws.badge :tone="$r['tone']">{{ $r['score'] }}</x-ws.badge>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('student.practice.index', ['tab' => 'history']) }}" class="mt-3 inline-flex items-center gap-1 text-[11px] font-bold text-[#126F91] hover:underline">
                        Xem toàn bộ lịch sử<x-lucide name="chevron-right" class="h-3 w-3" />
                    </a>
                @else
                    <p class="mt-3 rounded-xl border border-dashed border-sky-200 bg-[#F8FBFC] px-3 py-4 text-center text-[12px] text-[#61798B]">
                        Chưa có bài nào được chấm.
                    </p>
                @endif
            </section>

            {{-- Thông báo --}}
            <section class="rounded-2xl border border-sky-100 bg-white p-3 shadow-[0_3px_12px_rgba(28,91,121,0.05)] sm:p-4">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-[#EEE8F7] text-[#7657A5]"><x-lucide name="bell" class="h-4 w-4" /></span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[.12em] text-[#2D7FA3]">Cập nhật</p>
                        <h2 class="text-sm font-black text-[#123B68]">Thông báo</h2>
                    </div>
                </div>

                @if (count($notifications) > 0)
                    <ul class="mt-3 space-y-2.5">
                        @foreach ($notifications as $n)
                            <li class="rounded-xl bg-[#F8FBFC] px-3 py-2">
                                <p class="text-[12px] leading-snug text-[#123B68]">{{ $n['text'] }}</p>
                                <p class="mt-0.5 text-[11px] text-[#61798B]">{{ $n['time'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 rounded-xl border border-dashed border-sky-200 bg-[#F8FBFC] px-3 py-4 text-center text-[12px] text-[#61798B]">
                        Chưa có thông báo mới.
                    </p>
                @endif
                <a href="{{ route('student.notifications') }}" class="mt-3 inline-flex items-center gap-1 text-[11px] font-bold text-[#126F91] hover:underline">
                    Mở trang thông báo<x-lucide name="chevron-right" class="h-3 w-3" />
                </a>
            </section>
        </div>
    </div>
@endsection
