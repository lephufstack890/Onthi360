{{--
  Route: student.courses.index | Frame: STU-02
  Spec: 8.1 (khóa vs lớp), 7.3 (3 cửa truy cập).
  TODO controller: truyền $myClasses (ClassEnrollment::with('classRoom.course')).
--}}
@extends('layouts.student')

@section('title', 'Khóa học của tôi')
@section('page-title', 'Khóa học của tôi')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Student\CourseController truyền vào. --}}
    @php
        $classes = $classes ?? [];
    @endphp

    <x-page-header title="📚 Khóa học của tôi" subtitle="Lớp là nơi tổ chức lịch, học viên và tiến độ của bạn (8.1).">
        <x-slot:actions>
            <a href="{{ route('courses.index') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">+ Khám phá khóa học mới</a>
        </x-slot:actions>
    </x-page-header>

    @if (empty($classes))
        <x-empty-state title="Bạn chưa tham gia lớp nào" description="Khám phá khóa học phù hợp hoặc nhập mã lớp giáo viên cung cấp." actionLabel="Khám phá khóa học" :actionHref="route('courses.index')" />
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach ($classes as $c)
                <a href="{{ route('student.classes.show', $c['id']) }}" class="rounded-2xl bg-white border border-slate-200 p-5 hover:shadow-md hover:border-rose-200 transition block">
                    <div class="flex items-start gap-3">
                        <x-icon-tile emoji="🏫" tone="rose" />
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-rose-600 uppercase tracking-wide">{{ $c['course'] }}</p>
                            <h3 class="font-semibold text-slate-800 mt-0.5">{{ $c['class'] }}</h3>
                            <p class="text-xs text-slate-400 mt-1.5">{{ $c['teacher'] }}{{ $c['nextSession'] ? ' · Buổi tới: '.$c['nextSession'] : '' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <x-progress-bar :percent="$c['percent']" label="Tiến độ" tone="brand" />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
