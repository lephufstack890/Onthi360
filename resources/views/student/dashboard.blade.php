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
        <div class="rounded-3xl bg-gradient-to-br from-rose-100 via-pink-50 to-amber-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
            <div>
                <p class="text-sm text-rose-600 font-medium">Chào mừng trở lại 👋</p>
                @if (count($todayTasks) > 0)
                    <h2 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">{{ $name }}, hôm nay có {{ count($todayTasks) }} việc đang chờ bạn!</h2>
                @else
                    <h2 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">{{ $name }}, chào mừng bạn trở lại!</h2>
                @endif
                <p class="text-sm text-slate-500 mt-1">Cứ từng bước một — bạn đang làm rất tốt rồi đó.</p>
            </div>
            <div class="text-5xl">🚀</div>
        </div>

        <h3 class="font-medium text-slate-700 mb-3">Việc cần làm hôm nay</h3>
        @if (count($todayTasks) > 0)
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
        @else
            <div class="rounded-2xl bg-white border border-slate-200 p-5 mb-8">
                <x-empty-state title="Không có việc nào cần làm hôm nay" description="Cứ thư giãn, hoặc luyện tập thêm nếu bạn muốn." actionLabel="Luyện tập thêm" :actionHref="route('student.practice.index')" />
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h3 class="font-medium text-slate-700 mb-4">Tiến độ lớp/khóa</h3>
                    <div class="space-y-4">
                        @foreach ($classProgress as $cp)
                            <x-progress-bar :percent="$cp['percent']" :label="$cp['name']" tone="brand" />
                        @endforeach
                    </div>
                </div>

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
