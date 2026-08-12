{{--
  Route: courses.show | Frame: PUB-04
  Spec: 8.1, 8.3 (lớp liên quan, rating summary, CTA đăng ký/mua).
  TODO controller: truyền $course thật + $relatedClasses + rating summary.
--}}
@extends('layouts.guest')

@section('title', 'Chi tiết khóa học')

@section('content')
    @php
        $course = ['title' => 'Luyện thi vào 10 Chuyên Tin', 'description' => 'TODO: mô tả chương trình, mục tiêu, đối tượng.'];
        $classes = [
            ['name' => '10CT-2026 (tối T3-T5)', 'teacher' => 'GV Nguyễn Văn A', 'seats' => '32/35'],
            ['name' => '10CT-2026-B (sáng T7)', 'teacher' => 'GV Lê Văn C', 'seats' => '18/35'],
        ];
    @endphp

    <div class="max-w-5xl mx-auto px-4 py-10">
        <a href="{{ route('courses.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Khóa học</a>

        <x-page-header :title="$course['title']">
            <x-slot:actions>
                <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Đăng ký / Mua quyền</a>
            </x-slot:actions>
        </x-page-header>

        <div class="bg-white rounded-2xl border border-slate-200 p-6 mb-6">
            <x-rating-summary :average="4.8" :count="126" />
            <p class="text-sm text-slate-500 mt-4">{{ $course['description'] }}</p>
        </div>

        <h2 class="font-medium text-slate-700 mb-3">Các lớp đang triển khai</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($classes as $cl)
                <div class="rounded-2xl bg-white border border-slate-200 p-4">
                    <p class="font-medium text-slate-700">{{ $cl['name'] }}</p>
                    <p class="text-sm text-slate-400">{{ $cl['teacher'] }} · {{ $cl['seats'] }} học sinh</p>
                </div>
            @endforeach
        </div>
    </div>
@endsection
