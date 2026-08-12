{{--
  Route: dashboard (khi user chỉ có role student) | Frame: STU-01
  Spec: 10.1 — trong 5 giây phải thấy: việc cần làm hôm nay, bài đang
  dở/mới mở, lịch sắp tới, tiến độ lớp/khóa, kết quả gần đây, thông báo
  quan trọng. Empty state dẫn tới bài công khai/hướng dẫn vào lớp, không
  để dashboard trống.
  TODO controller: truyền $todayTasks, $upcoming, $classProgress,
  $recentResults, $notifications thật; nếu user chưa vào lớp nào, render
  nhánh $hasAnyClass = false ở dưới.
--}}
@extends('layouts.student')

@section('title', 'Tổng quan')
@section('page-title', 'Tổng quan')

@section('content')
    @php
        $name = auth()->user()->name ?? 'bạn';
        $hasAnyClass = true;

        $todayTasks = [
            ['title' => 'Nộp bài: Đề ôn chương 3 - Cấu trúc dữ liệu', 'meta' => 'Lớp 10CT-2026 · Hạn 22:00 hôm nay', 'emoji' => '⏰', 'tone' => 'rose', 'cta' => 'Làm ngay'],
            ['title' => 'Mã bài mới mở: Bài 12 - Đệ quy cơ bản', 'meta' => 'Giáo viên vừa mở tiến độ · Lớp 10CT-2026', 'emoji' => '🔓', 'tone' => 'emerald', 'cta' => 'Xem bài'],
            ['title' => 'Bài đang làm dở: Trắc nghiệm chương 2', 'meta' => 'Đã lưu nháp 6/10 câu', 'emoji' => '📝', 'tone' => 'amber', 'cta' => 'Tiếp tục'],
        ];

        $upcoming = [
            ['time' => 'Hôm nay · 19:00', 'title' => 'Buổi học: Cấu trúc dữ liệu nâng cao', 'meta' => 'Lớp 10CT-2026'],
            ['time' => 'Thứ Năm · 19:00', 'title' => 'Buổi học: Ôn tập chương 3', 'meta' => 'Lớp 10CT-2026'],
        ];

        $classProgress = [
            ['name' => '10CT-2026 · Luyện thi vào 10 Chuyên Tin', 'percent' => 62],
            ['name' => '11HSG-2026 · Ôn thi HSG Tin 11', 'percent' => 28],
        ];

        $recentResults = [
            ['title' => 'Trắc nghiệm chương 1', 'score' => '9/10', 'time' => '2 ngày trước', 'tone' => 'success'],
            ['title' => 'Bài code: Sắp xếp nổi bọt', 'score' => 'Accepted', 'time' => '4 ngày trước', 'tone' => 'success'],
            ['title' => 'Đề ôn chương 2', 'score' => '6/10', 'time' => '1 tuần trước', 'tone' => 'warning'],
        ];

        $notifications = [
            ['text' => 'Giáo viên đã mở Bài 12 - Đệ quy cơ bản', 'time' => '1 giờ trước'],
            ['text' => 'Quyền học "Chuyên đề CTDL nâng cao" sắp hết hạn (còn 5 ngày)', 'time' => '3 giờ trước'],
        ];
    @endphp

    @if (!$hasAnyClass)
        {{-- Empty state: chưa vào lớp nào — dẫn tới bài công khai / hướng dẫn vào lớp (10.1) --}}
        <div class="rounded-3xl bg-gradient-to-br from-rose-50 to-amber-50 p-10 text-center">
            <div class="text-5xl mb-3">🎈</div>
            <h2 class="text-lg font-semibold text-slate-800">Chào {{ $name }}, bắt đầu hành trình học của bạn nhé!</h2>
            <p class="text-sm text-slate-500 mt-2 max-w-md mx-auto">Bạn chưa tham gia lớp nào. Thử luyện tập bài công khai ngay, hoặc nhập mã lớp nếu giáo viên đã cung cấp cho bạn.</p>
            <div class="flex justify-center gap-3 mt-5">
                <a href="{{ route('practice.index') }}" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">Luyện tập ngay</a>
                <a href="{{ route('student.courses.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Tìm lớp/khóa học</a>
            </div>
        </div>
    @else
        {{-- Hero chào mừng --}}
        <div class="rounded-3xl bg-gradient-to-br from-rose-100 via-pink-50 to-amber-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
            <div>
                <p class="text-sm text-rose-600 font-medium">Chào mừng trở lại 👋</p>
                <h2 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">{{ $name }}, hôm nay có {{ count($todayTasks) }} việc đang chờ bạn!</h2>
                <p class="text-sm text-slate-500 mt-1">Cứ từng bước một — bạn đang làm rất tốt rồi đó.</p>
            </div>
            <div class="text-5xl">🚀</div>
        </div>

        {{-- Việc cần làm hôm nay --}}
        <h3 class="font-medium text-slate-700 mb-3">Việc cần làm hôm nay</h3>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
            @foreach ($todayTasks as $t)
                <div class="rounded-2xl bg-white border border-slate-200 p-4 flex items-start gap-3">
                    <x-icon-tile :emoji="$t['emoji']" :tone="$t['tone']" />
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-700 text-sm leading-snug">{{ $t['title'] }}</p>
                        <p class="text-xs text-slate-400 mt-1">{{ $t['meta'] }}</p>
                        <a href="{{ route('student.practice.index') }}" class="inline-block mt-2 text-xs font-medium text-rose-600">{{ $t['cta'] }} ›</a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                {{-- Tiến độ lớp/khóa --}}
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h3 class="font-medium text-slate-700 mb-4">Tiến độ lớp/khóa</h3>
                    <div class="space-y-4">
                        @foreach ($classProgress as $cp)
                            <x-progress-bar :percent="$cp['percent']" :label="$cp['name']" tone="brand" />
                        @endforeach
                    </div>
                </div>

                {{-- Lịch sắp tới --}}
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h3 class="font-medium text-slate-700 mb-4">Lịch sắp tới</h3>
                    <ul class="space-y-3">
                        @foreach ($upcoming as $u)
                            <li class="flex items-center gap-3 text-sm">
                                <div class="w-16 shrink-0 text-xs font-medium text-rose-600">{{ $u['time'] }}</div>
                                <div class="flex-1">
                                    <p class="text-slate-700">{{ $u['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $u['meta'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="space-y-6">
                {{-- Kết quả gần đây --}}
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h3 class="font-medium text-slate-700 mb-4">Kết quả gần đây</h3>
                    <ul class="space-y-3">
                        @foreach ($recentResults as $r)
                            <li class="flex items-center justify-between text-sm">
                                <div>
                                    <p class="text-slate-700">{{ $r['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $r['time'] }}</p>
                                </div>
                                <x-status-badge :tone="$r['tone']">{{ $r['score'] }}</x-status-badge>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('student.practice.index') }}" class="inline-block mt-4 text-sm text-rose-600 font-medium">Xem toàn bộ lịch sử ›</a>
                </div>

                {{-- Thông báo --}}
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h3 class="font-medium text-slate-700 mb-4">Thông báo</h3>
                    <ul class="space-y-3">
                        @foreach ($notifications as $n)
                            <li class="text-sm">
                                <p class="text-slate-700">{{ $n['text'] }}</p>
                                <p class="text-xs text-slate-400">{{ $n['time'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif
@endsection
