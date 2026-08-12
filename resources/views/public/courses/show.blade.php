{{--
  Route: courses.show | Frame: PUB-04
  Spec: 8.1, 8.3 (lớp liên quan, rating summary, CTA đăng ký/mua).
  TODO controller: truyền $course thật + $relatedClasses + rating summary
  — hiện là dữ liệu minh họa để dựng UI; ảnh bìa dùng picsum.photos,
  avatar giáo viên dùng ui-avatars.com tạm.
--}}
@extends('layouts.guest')

@section('title', 'Chi tiết khóa học')

@section('content')
    @php
        $course = [
            'title' => 'Luyện thi vào 10 Chuyên Tin',
            'subject' => 'Tin học',
            'grade' => 'Khối 9',
            'description' => 'Chương trình bám sát cấu trúc đề thi chuyên các năm gần đây, kết hợp lý thuyết trọng tâm với luyện đề thật và chấm bài lập trình tự động.',
            'outcomes' => ['Nắm chắc cấu trúc dữ liệu và thuật toán cơ bản', 'Luyện phản xạ với đề thi mô phỏng hàng tuần', 'Chấm bài lập trình tự động, xem lại lời giải chi tiết'],
        ];
        $classes = [
            ['name' => '10CT-2026 (tối T3-T5)', 'teacher' => 'Nguyễn Văn A', 'seats' => 32, 'capacity' => 35],
            ['name' => '10CT-2026-B (sáng T7)', 'teacher' => 'Lê Văn C', 'seats' => 18, 'capacity' => 35],
        ];
    @endphp

    <div class="bg-gradient-to-br from-sky-50 via-white to-rose-50">
        <div class="max-w-6xl mx-auto px-4 py-10 lg:py-14">
            <a href="{{ route('courses.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Khóa học</a>

            <div class="flex flex-col lg:flex-row gap-8 items-start">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-3">
                        <x-status-badge tone="info">{{ $course['subject'] }}</x-status-badge>
                        <x-status-badge tone="neutral">{{ $course['grade'] }}</x-status-badge>
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800">{{ $course['title'] }}</h1>
                    <div class="mt-3"><x-rating-summary :average="4.8" :count="126" /></div>
                    <p class="text-slate-500 mt-4 max-w-xl leading-relaxed">{{ $course['description'] }}</p>
                    <a href="{{ route('login') }}" class="inline-block mt-5 px-6 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium">
                        Đăng ký / Mua quyền
                    </a>
                </div>
                <img src="https://picsum.photos/seed/{{ \Illuminate\Support\Str::slug($course['title']) }}/480/360" alt=""
                     class="w-full lg:w-80 rounded-3xl shadow-lg object-cover aspect-[4/3]">
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-10 lg:py-14">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
                <h2 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>🎯</span> Bạn sẽ đạt được gì</h2>
                <ul class="space-y-2.5">
                    @foreach ($course['outcomes'] as $o)
                        <li class="flex items-start gap-2.5 text-sm text-slate-600"><span class="text-emerald-500 mt-0.5">✓</span>{{ $o }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h2 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>📌</span> Thông tin nhanh</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between"><span class="text-slate-500">Số lớp đang mở</span><span class="font-medium text-slate-700">{{ count($classes) }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Hình thức</span><span class="font-medium text-slate-700">Trực tuyến</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Đối tượng</span><span class="font-medium text-slate-700">{{ $course['grade'] }}</span></div>
                </div>
            </div>
        </div>

        <h2 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>🏫</span> Các lớp đang triển khai</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($classes as $cl)
                @php $percentFull = $cl['capacity'] > 0 ? (int) round($cl['seats'] / $cl['capacity'] * 100) : 0; @endphp
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
                        @if ($percentFull >= 90)
                            <x-status-badge tone="warning">Gần đầy</x-status-badge>
                        @else
                            <x-status-badge tone="success">Còn chỗ</x-status-badge>
                        @endif
                    </div>
                    <div class="mt-4">
                        <x-progress-bar :percent="$percentFull" label="{{ $cl['seats'] }}/{{ $cl['capacity'] }} học sinh" tone="brand" />
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
