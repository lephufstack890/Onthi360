@extends('layouts.teacher')

@section('title', 'Lớp học')
@section('page-title', 'Lớp học')

@section('content')
    @php
        $classes = $classes ?? [];
        $classesCollection = collect($classes);
        $totalStudents = $classesCollection->sum('students');
        $avgCompletion = $classesCollection->count() ? (int) round($classesCollection->avg('completion')) : 0;
        $isTeacherApproved = auth()->user()->isTeacherApproved();
    @endphp

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-rose-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div>
            <p class="text-sm text-sky-600 font-medium">Không gian dạy học của bạn</p>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">Lớp học</h1>
            <p class="text-sm text-slate-500 mt-1">Quản lý lớp được giao — lịch, điểm danh, học liệu và tiến độ (8.3).</p>
        </div>
        @if ($isTeacherApproved)
            <a href="{{ route('teacher.classes.create') }}" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm shrink-0">+ Tạo lớp mới</a>
        @else
            <span class="px-5 py-2.5 rounded-lg bg-slate-100 text-slate-400 text-sm font-medium shrink-0 cursor-not-allowed" title="Hồ sơ giáo viên đang chờ Admin duyệt (3.3)">⏳ Chờ duyệt để tạo lớp</span>
        @endif
    </div>

    @if ($classesCollection->isNotEmpty())
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <x-stat-tile label="Tổng số lớp" :value="$classesCollection->count()" tone="neutral" />
            <x-stat-tile label="Tổng học sinh" :value="$totalStudents" tone="success" />
            <x-stat-tile label="Hoàn thành chung (TB)" value="{{ $avgCompletion }}%" tone="info" />
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        @forelse ($classes as $c)
            <a href="{{ route('teacher.classes.show', $c['id']) }}" class="rounded-2xl bg-white border border-slate-200 p-5 hover:shadow-md hover:border-rose-200 transition block">
                <div class="flex items-start gap-3">
                    <x-icon-tile emoji="🏫" tone="sky" />
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-sky-600 uppercase tracking-wide">{{ $c['course'] }}</p>
                        <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                            <h3 class="font-semibold text-slate-800">{{ $c['name'] }}</h3>
                            @if ($c['code'])
                                <span class="text-[11px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">{{ $c['code'] }}</span>
                            @endif
                        </div>
                        @if ($c['scheduleNote'])
                            <p class="text-xs text-slate-500 mt-1">📅 {{ $c['scheduleNote'] }}</p>
                        @endif
                        <div class="flex items-center gap-3 text-xs text-slate-400 mt-1.5 flex-wrap">
                            <span>👥 {{ $c['students'] }} học sinh</span>
                            @if ($c['nextSession'])
                                <span>· 🗓 Buổi tới: {{ $c['nextSession'] }}</span>
                            @elseif ($c['inProgressSessionLabel'])
                                <span class="text-emerald-600 font-medium">· 🔴 Đang diễn ra ({{ $c['inProgressSessionLabel'] }})@if (! $c['inProgressAttendanceTaken']) — chưa điểm danh @endif</span>
                            @elseif ($c['lastSessionLabel'])
                                @if ($c['lastSessionAttendanceTaken'])
                                    <span>· ✅ Buổi {{ $c['lastSessionLabel'] }} — đã điểm danh</span>
                                @else
                                    <span class="text-amber-600 font-medium">· ⚠️ Buổi {{ $c['lastSessionLabel'] }} đã kết thúc, chưa điểm danh</span>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <x-progress-bar :percent="$c['completion']" label="Hoàn thành chung (theo buổi học)" tone="info" />
                    @if ($c['completionTotalSessions'] > 0)
                        <p class="text-[11px] text-slate-400 mt-1">{{ $c['completionEndedSessions'] }}/{{ $c['completionTotalSessions'] }} buổi đã học</p>
                    @endif
                </div>
            </a>
        @empty
            <div class="col-span-full">
                @if ($isTeacherApproved)
                <x-empty-state title="Chưa có lớp nào" description="Tạo lớp mới để bắt đầu tổ chức dạy học." actionLabel="Tạo lớp mới" :actionHref="route('teacher.classes.create')" />
            @else
                <x-empty-state title="Chưa có lớp nào" description="Hồ sơ giáo viên của bạn đang chờ Admin duyệt (3.3) — được duyệt rồi mới tạo được lớp." />
            @endif
            </div>
        @endforelse
    </div>
@endsection
