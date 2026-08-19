@extends('layouts.student')

@section('title', 'Thời khoá biểu')
@section('page-title', 'Thời khoá biểu')

@section('content')
    @php
        $days = $days ?? [];
    @endphp

    <x-page-header title="Thời khoá biểu" subtitle="Lịch học gộp từ tất cả lớp bạn đang tham gia, xem theo tuần." />

    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
        <div class="flex items-center gap-2">
            <a href="{{ route('student.schedule.index', ['week' => $weekOffset - 1]) }}"
               class="w-9 h-9 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:bg-slate-50" aria-label="Tuần trước">‹</a>
            <p class="text-sm font-medium text-slate-700 min-w-[160px] text-center">
                {{ $weekStart->format('d/m') }} – {{ $weekEnd->format('d/m/Y') }}
            </p>
            <a href="{{ route('student.schedule.index', ['week' => $weekOffset + 1]) }}"
               class="w-9 h-9 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:bg-slate-50" aria-label="Tuần sau">›</a>
        </div>
        @if ($weekOffset !== 0)
            <a href="{{ route('student.schedule.index') }}" class="text-sm text-rose-600 font-medium">Về tuần này</a>
        @endif
    </div>

    @if (! $hasAnyClass)
        <x-empty-state title="Bạn chưa tham gia lớp học nào"
                        description="Thời khoá biểu sẽ hiện ra khi bạn được ghi danh vào một lớp học."
                        actionLabel="Xem khóa học của tôi" :actionHref="route('student.courses.index')" />
    @else
        <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
            <table class="w-full border-collapse min-w-[980px] table-fixed">
                <thead>
                    <tr>
                        @foreach ($days as $day)
                            <th class="w-[14.2857%] align-top border-b border-slate-200 {{ ! $loop->last ? 'border-r' : '' }} p-3 text-left {{ $day['isToday'] ? 'bg-rose-50' : 'bg-slate-50' }}">
                                <p class="text-xs font-semibold uppercase tracking-wide {{ $day['isToday'] ? 'text-rose-600' : 'text-slate-500' }}">{{ $day['label'] }}</p>
                                <p class="text-sm font-medium text-slate-700">{{ $day['date']->format('d/m') }}</p>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach ($days as $day)
                            <td class="align-top border-slate-200 {{ ! $loop->last ? 'border-r' : '' }} p-2 {{ $day['isToday'] ? 'bg-rose-50/30' : '' }}">
                                <div class="space-y-2">
                                    @forelse ($day['sessions'] as $s)
                                        <div class="rounded-xl bg-slate-50 border border-slate-100 p-2.5">
                                            <p class="text-xs font-semibold text-slate-700 truncate" title="{{ $s['className'] }}">{{ $s['className'] }}</p>
                                            <p class="text-xs text-slate-500 mt-0.5 truncate" title="{{ $s['topic'] }}">{{ $s['topic'] ?? 'Buổi học' }}</p>
                                            <p class="text-xs text-slate-400 mt-1">🕐 {{ $s['timeRangeLabel'] }}</p>
                                            @if (! empty($s['location']))
                                                <p class="text-xs text-slate-400 truncate" title="{{ $s['location'] }}">📍 {{ $s['location'] }}</p>
                                            @endif
                                            <div class="flex flex-wrap items-center gap-1 mt-1.5">
                                                <x-status-badge :tone="$s['timeStatusTone']">{{ $s['timeStatusLabel'] }}</x-status-badge>
                                                <x-status-badge :tone="$s['attendanceTone']">{{ $s['attendanceLabel'] }}</x-status-badge>
                                            </div>
                                            <a href="{{ route('student.classes.show', ['class' => $s['classRoomId'], 'tab' => 'schedule']) }}"
                                               class="block text-xs text-rose-600 font-medium mt-1.5">Xem lớp ›</a>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-300 italic px-1">—</p>
                                    @endforelse
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
@endsection
