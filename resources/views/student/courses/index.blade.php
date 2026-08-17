{{--
  Route: student.courses.index | Frame: STU-02
  Spec: 8.1 (khóa vs lớp), 7.3 (3 cửa truy cập).
  Dữ liệu thật do App\Http\Controllers\Student\CourseController truyền vào.
  Form "Nhập mã lớp để tham gia" gửi thật tới student.classes.join (App\Http\Controllers\
  Student\ClassRoomController::join(), qua App\Services\Student\ClassRoomService::
  joinByCode()) — trước đây empty-state đã HỨA SẴN "nhập mã lớp giáo viên cung cấp" nhưng
  chưa có luồng thật nào thực hiện việc này.
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

    @if (session('status') === 'joined-class')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tham gia lớp thành công!'])
    @endif

    <div class="rounded-2xl bg-white border border-slate-200 p-5 mb-6">
        <h2 class="font-medium text-slate-700 mb-1 flex items-center gap-2"><span>🔑</span> Có mã lớp?</h2>
        <p class="text-sm text-slate-500 mb-3">Giáo viên cung cấp mã lớp riêng cho từng lớp — nhập đúng mã để tham gia ngay.</p>
        <form method="POST" action="{{ route('student.classes.join') }}" class="flex flex-col sm:flex-row gap-3 max-w-md">
            @csrf
            <input type="text" name="code" placeholder="Ví dụ: 10CT-2026"
                   class="flex-1 rounded-lg border {{ $errors->has('code') ? 'border-rose-300' : 'border-slate-200' }} text-sm p-2.5">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition-colors shrink-0">Tham gia lớp</button>
        </form>
        @error('code')
            <p class="text-xs text-rose-500 mt-2">{{ $message }}</p>
        @enderror
    </div>

    @if (empty($classes))
        <x-empty-state title="Bạn chưa tham gia lớp nào" description="Khám phá khóa học phù hợp hoặc nhập mã lớp giáo viên cung cấp ở trên." actionLabel="Khám phá khóa học" :actionHref="route('courses.index')" />
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
