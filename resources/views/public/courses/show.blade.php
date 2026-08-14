{{--
  Route: courses.show | Frame: PUB-04
  Spec: 8.1, 8.3 (lớp liên quan, rating summary, CTA đăng ký/mua).
  Dữ liệu thật do App\Http\Controllers\Public\CourseController truyền vào qua
  App\Services\Public\CourseService::showData() — ảnh bìa dùng picsum.photos tạm
  (chưa có cover_image_path thật được upload).
--}}
@extends('layouts.guest')

@section('title', 'Chi tiết khóa học')

@section('content')
    @php
        $classes = $classes ?? [];
        $ratingAverage = $ratingAverage ?? null;
        $ratingCount = $ratingCount ?? 0;
    @endphp

    <div class="bg-gradient-to-br from-sky-50 via-white to-rose-50">
        <div class="max-w-6xl mx-auto px-4 py-10 lg:py-14">
            <a href="{{ route('courses.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Khóa học</a>

            <div class="flex flex-col lg:flex-row gap-8 items-start">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-3">
                        @if ($course->subject)
                            <x-status-badge tone="info">{{ $course->subject }}</x-status-badge>
                        @endif
                        @if ($course->grade)
                            <x-status-badge tone="neutral">{{ $course->grade }}</x-status-badge>
                        @endif
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800">{{ $course->title }}</h1>
                    <div class="mt-3"><x-rating-summary :average="$ratingAverage" :count="$ratingCount" /></div>
                    <p class="text-slate-500 mt-4 max-w-xl leading-relaxed">{{ $course->description ?: 'Chưa có mô tả chi tiết.' }}</p>
                    <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="inline-block mt-5 px-6 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium">
                        {{ auth()->check() ? 'Xem lớp học của tôi' : 'Đăng nhập để đăng ký / mua quyền' }}
                    </a>
                </div>
                <img src="https://picsum.photos/seed/{{ \Illuminate\Support\Str::slug($course->title) }}/480/360" alt=""
                     class="w-full lg:w-80 rounded-3xl shadow-lg object-cover aspect-[4/3]">
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-10 lg:py-14">
        <div class="bg-white rounded-2xl border border-slate-200 p-6 mb-10">
            <h2 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>📌</span> Thông tin nhanh</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div class="flex items-center justify-between sm:block"><span class="text-slate-500">Số lớp đang mở</span><span class="font-medium text-slate-700 sm:block sm:mt-1">{{ count($classes) }}</span></div>
                <div class="flex items-center justify-between sm:block"><span class="text-slate-500">Môn học</span><span class="font-medium text-slate-700 sm:block sm:mt-1">{{ $course->subject ?: '—' }}</span></div>
                <div class="flex items-center justify-between sm:block"><span class="text-slate-500">Đối tượng</span><span class="font-medium text-slate-700 sm:block sm:mt-1">{{ $course->grade ?: '—' }}</span></div>
            </div>
        </div>

        <h2 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>🏫</span> Các lớp đang triển khai</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @forelse ($classes as $cl)
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-slate-700">{{ $cl['name'] }}</p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($cl['teacher']) }}&background=e11d48&color=ffffff&size=64&bold=true"
                                     alt="{{ $cl['teacher'] }}" class="w-6 h-6 rounded-full">
                                <span class="text-sm text-slate-400">GV {{ $cl['teacher'] }}</span>
                            </div>
                        </div>
                        <x-status-badge tone="success">Đang mở</x-status-badge>
                    </div>
                    <p class="text-sm text-slate-500 mt-4">👥 {{ $cl['studentsCount'] }} học sinh đã tham gia</p>
                </div>
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Chưa có lớp nào đang triển khai" description="Quay lại sau hoặc chọn khóa học khác." />
                </div>
            @endforelse
        </div>
    </div>
@endsection
