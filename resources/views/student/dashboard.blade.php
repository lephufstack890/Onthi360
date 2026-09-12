@extends('layouts.student')

@section('title', 'Tổng quan')
@section('page-title', 'Tổng quan')

@section('content')
    @php
        $name = $name ?? (auth()->user()->name ?? 'bạn');
        $hasAnyClass = $hasAnyClass ?? false;
        $todayTasks = $todayTasks ?? [];
        $upcoming = $upcoming ?? [];
        $classProgress = $classProgress ?? [];
        $recentResults = $recentResults ?? [];
        $notifications = $notifications ?? [];
    @endphp

    @if (!$hasAnyClass)
        <div class="rounded-3xl bg-gradient-to-br from-sky-50 to-amber-50 p-10 text-center">
            <div class="text-5xl mb-3">🎈</div>
            <h2 class="text-lg font-semibold text-slate-800">Chào {{ $name }}, bắt đầu hành trình học của bạn nhé!</h2>
            <p class="text-[13px] text-slate-500 mt-2 max-w-md mx-auto">Bạn chưa tham gia lớp nào. Thử luyện tập bài công khai ngay, hoặc nhập mã lớp nếu giáo viên đã cung cấp cho bạn.</p>
            <div class="flex justify-center gap-3 mt-5">
                <a href="{{ route('practice.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Luyện tập ngay</a>
                <a href="{{ route('student.courses.index') }}" class="px-5 py-2.5 rounded-xl border border-sky-100 text-slate-600 text-[13px] font-medium">Tìm lớp/khóa học</a>
            </div>
        </div>
    @else
        <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-blue-50 shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 lg:p-6 mb-4 flex items-center justify-between flex-wrap gap-4">
            <div>
                <p class="text-[13px] text-blue-600 font-medium">Chào mừng trở lại 👋</p>
                @if (count($todayTasks) > 0)
                    <h2 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">{{ $name }}, hôm nay có {{ count($todayTasks) }} việc đang chờ bạn!</h2>
                @else
                    <h2 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">{{ $name }}, chào mừng bạn trở lại!</h2>
                @endif
                <p class="text-[13px] text-slate-500 mt-1">Cứ từng bước một — bạn đang làm rất tốt rồi đó.</p>
            </div>
            <div class="text-5xl">🚀</div>
        </div>

        <h3 class="font-medium text-slate-700 mb-3">Việc cần làm hôm nay</h3>
        @if (count($todayTasks) > 0)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
                @foreach ($todayTasks as $t)
                    <div class="rounded-3xl bg-white border border-sky-100 p-4 flex items-start gap-3">
                        <x-ws.icon-tile :emoji="$t['emoji']" :tone="$t['tone']" />
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-slate-700 text-[13px] leading-snug">{{ $t['title'] }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ $t['meta'] }}</p>
                            <a href="{{ route('student.practice.index') }}" class="inline-block mt-2 text-xs font-medium text-blue-600">{{ $t['cta'] }} ›</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-3xl bg-white border border-sky-100 p-5 mb-8">
                <x-ws.empty-state title="Không có việc nào cần làm hôm nay" description="Cứ thư giãn, hoặc luyện tập thêm nếu bạn muốn." actionLabel="Luyện tập thêm" :actionHref="route('student.practice.index')" />
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <h3 class="font-medium text-slate-700 mb-4">Tiến độ lớp/khóa</h3>
                    <div class="space-y-4">
                        @foreach ($classProgress as $cp)
                            <x-ws.progress-bar :percent="$cp['percent']" :label="$cp['name']" tone="brand" />
                        @endforeach
                    </div>
                </div>

                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <h3 class="font-medium text-slate-700 mb-4">Lịch sắp tới</h3>
                    <ul class="space-y-3">
                        @foreach ($upcoming as $u)
                            <li class="flex items-center gap-3 text-[13px]">
                                <div class="w-16 shrink-0 text-xs font-medium text-blue-600">{{ $u['time'] }}</div>
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
                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <h3 class="font-medium text-slate-700 mb-4">Kết quả gần đây</h3>
                    <ul class="space-y-3">
                        @foreach ($recentResults as $r)
                            <li class="flex items-center justify-between text-[13px]">
                                <div>
                                    <p class="text-slate-700">{{ $r['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $r['time'] }}</p>
                                </div>
                                <x-ws.badge :tone="$r['tone']">{{ $r['score'] }}</x-ws.badge>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('student.practice.index') }}" class="inline-block mt-4 text-[13px] text-blue-600 font-medium">Xem toàn bộ lịch sử ›</a>
                </div>

                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <h3 class="font-medium text-slate-700 mb-4">Thông báo</h3>
                    <ul class="space-y-3">
                        @foreach ($notifications as $n)
                            <li class="text-[13px]">
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
