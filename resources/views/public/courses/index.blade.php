{{--
  Route: courses.index | Frame: PUB-03
  Spec: 4.1 (Khóa học = khám phá chương trình, khác Lớp học).
  Dữ liệu thật do App\Http\Controllers\Public\CourseController truyền vào qua
  App\Services\Public\CourseService::indexData().
--}}
@extends('layouts.guest')

@section('title', 'Khóa học')

@section('content')
    @php
        $courses = $courses ?? [];
        $subjects = $subjects ?? [];
        $activeSubject = $activeSubject ?? null;
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-10">
        <x-page-header title="Khóa học" subtitle="Một khóa học có thể có nhiều lớp; lớp là nơi tổ chức lịch, học viên và tiến độ (8.1)." />

        <div class="flex flex-wrap gap-2 mb-6 text-sm">
            <a href="{{ route('courses.index') }}" class="px-3 py-1.5 rounded-full {{ $activeSubject === null ? 'bg-rose-50 text-rose-600 font-medium' : 'border border-slate-200 text-slate-500' }}">Tất cả</a>
            @foreach ($subjects as $subject)
                <a href="{{ route('courses.index', ['subject' => $subject]) }}" class="px-3 py-1.5 rounded-full {{ $activeSubject === $subject ? 'bg-rose-50 text-rose-600 font-medium' : 'border border-slate-200 text-slate-500' }}">{{ $subject }}</a>
            @endforeach
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($courses as $c)
                <x-card-item :title="$c['title']" :meta="$c['meta']" :average="$c['average']" :count="$c['count']"
                             href="{{ route('courses.show', $c['id']) }}" badgeLabel="Công khai" badgeTone="info" />
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Chưa có khóa học nào" description="Thử bỏ bộ lọc hoặc quay lại sau." />
                </div>
            @endforelse
        </div>
    </div>
@endsection
