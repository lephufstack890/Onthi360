{{--
  Route: courses.index | Frame: PUB-03
  Spec: 4.1 (Khóa học = khám phá chương trình, khác Lớp học).
  TODO controller: truyền $courses (paginate) + filter môn/khối.
--}}
@extends('layouts.guest')

@section('title', 'Khóa học')

@section('content')
    @php
        $courses = [
            ['title' => 'Luyện thi vào 10 Chuyên Tin', 'meta' => '5 lớp đang triển khai · Tin học · Khối 9', 'average' => 4.8, 'count' => 126],
            ['title' => 'Ôn thi HSG Tin 11', 'meta' => '2 lớp đang triển khai · Tin học · Khối 11', 'average' => 4.6, 'count' => 42],
            ['title' => 'Nền tảng lập trình cho học sinh THCS', 'meta' => 'Sắp mở · Tin học · Khối 6-9', 'average' => null, 'count' => 0],
        ];
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-10">
        <x-page-header title="Khóa học" subtitle="Một khóa học có thể có nhiều lớp; lớp là nơi tổ chức lịch, học viên và tiến độ (8.1)." />

        {{-- TODO: bộ lọc môn/khối/chuyên đề/độ khó (13.2 filter chip) --}}
        <div class="flex gap-2 mb-6 text-sm">
            <button type="button" class="px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-medium">Tất cả</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Tin học</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Toán</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($courses as $c)
                <x-card-item :title="$c['title']" :meta="$c['meta']" :average="$c['average']" :count="$c['count']"
                             href="{{ route('courses.show', 1) }}" badgeLabel="Công khai" badgeTone="info" />
            @empty
                <x-empty-state title="Chưa có khóa học nào" description="Thử bỏ bộ lọc hoặc quay lại sau." />
            @endforelse
        </div>
    </div>
@endsection
