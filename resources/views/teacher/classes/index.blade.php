{{--
  Route: teacher.classes.index | Frame: TEA-02
  Spec: 8.1 (lớp mà giáo viên phụ trách hoặc đồng phụ trách).
  TODO controller: truyền $classes = auth()->user()->classRoomsTeaching().
--}}
@extends('layouts.teacher')

@section('title', 'Lớp học')
@section('page-title', 'Lớp học')

@section('content')
    @php
        $classes = [
            ['id' => 10, 'course' => 'Luyện thi vào 10 Chuyên Tin', 'name' => '10CT-2026', 'students' => 32, 'nextSession' => 'Hôm nay 19:00', 'completion' => 62],
            ['id' => 11, 'course' => 'Ôn thi HSG Tin 11', 'name' => '11HSG-2026', 'students' => 18, 'nextSession' => 'Thứ Sáu 19:30', 'completion' => 45],
        ];
    @endphp

    <x-page-header title="Lớp học" subtitle="Quản lý lớp được giao — lịch, điểm danh, học liệu và tiến độ (8.3).">
        <x-slot:actions>
            <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo lớp mới</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @forelse ($classes as $c)
            <a href="{{ route('teacher.classes.show', $c['id']) }}" class="rounded-2xl bg-white border border-slate-200 p-5 hover:shadow-md transition block">
                <p class="text-xs font-medium text-sky-600 uppercase tracking-wide">{{ $c['course'] }}</p>
                <h3 class="font-semibold text-slate-800 mt-1">{{ $c['name'] }}</h3>
                <p class="text-sm text-slate-400 mt-1">{{ $c['students'] }} học sinh · Buổi tới: {{ $c['nextSession'] }}</p>
                <div class="mt-4">
                    <x-progress-bar :percent="$c['completion']" label="Hoàn thành chung" tone="info" />
                </div>
            </a>
        @empty
            <div class="col-span-full">
                <x-empty-state title="Chưa có lớp nào" description="Tạo lớp mới để bắt đầu tổ chức dạy học." actionLabel="Tạo lớp mới" actionHref="#" />
            </div>
        @endforelse
    </div>
@endsection
