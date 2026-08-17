{{--
  Route: courses.show | Frame: PUB-04
  Spec: 8.1, 8.3 (lớp liên quan, rating summary, CTA đăng ký/mua).
  Dữ liệu thật do App\Http\Controllers\Public\CourseController truyền vào qua
  App\Services\Public\CourseService::showData() — ảnh bìa dùng picsum.photos tạm
  (chưa có cover_image_path thật được upload).

  CTA trước đây chỉ kiểm tra auth()->check() — bất kỳ ai ĐÃ đăng nhập, kể cả học sinh chưa
  từng tham gia lớp nào của khóa này, đều thấy "Xem lớp học của tôi". Giờ học sinh chỉ thấy
  nút đó khi thật sự đã tham gia (ClassEnrollment active) ≥ 1 lớp thuộc khóa này; ngược lại
  thấy CTA "Nhập mã lớp để tham gia" — join-by-code thật (App\Http\Controllers\Student\
  ClassRoomController::join(), route student.classes.join).
--}}
@extends('layouts.guest')

@section('title', 'Chi tiết khóa học')

@section('content')
    @php
        $classes = $classes ?? [];
        $ratingAverage = $ratingAverage ?? null;
        $ratingCount = $ratingCount ?? 0;
        $isStudent = $isStudent ?? false;
        $myClassRoomIdsInThisCourse = $myClassRoomIdsInThisCourse ?? [];
        $isEnrolledInThisCourse = count($myClassRoomIdsInThisCourse) > 0;
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

                    @if (! auth()->check())
                        <a href="{{ route('login') }}" class="inline-block mt-5 px-6 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium">Đăng nhập để đăng ký / mua quyền</a>
                    @elseif ($isStudent && $isEnrolledInThisCourse)
                        <a href="{{ count($myClassRoomIdsInThisCourse) === 1 ? route('student.classes.show', $myClassRoomIdsInThisCourse[0]) : route('student.courses.index') }}"
                           class="inline-block mt-5 px-6 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium">Xem lớp học của tôi ›</a>
                    @elseif ($isStudent)
                        <a href="#tham-gia-lop" class="inline-block mt-5 px-6 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium">Nhập mã lớp để tham gia ↓</a>
                    @else
                        {{-- Vai trò khác (giáo viên/phụ huynh/admin) — giữ nguyên hành vi cũ, ngoài phạm vi bug được báo cáo. --}}
                        <a href="{{ route('dashboard') }}" class="inline-block mt-5 px-6 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium">Xem lớp học của tôi</a>
                    @endif
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
                        @if ($cl['isMember'] ?? false)
                            <x-status-badge tone="success">Đã tham gia ✓</x-status-badge>
                        @else
                            <x-status-badge tone="success">Đang mở</x-status-badge>
                        @endif
                    </div>
                    <p class="text-sm text-slate-500 mt-4">👥 {{ $cl['studentsCount'] }} học sinh đã tham gia</p>
                </div>
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Chưa có lớp nào đang triển khai" description="Quay lại sau hoặc chọn khóa học khác." />
                </div>
            @endforelse
        </div>

        @if ($isStudent)
            <div id="tham-gia-lop" class="scroll-mt-20 mt-10 rounded-2xl bg-white border border-slate-200 p-6">
                <h2 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span>🔑</span> Có mã lớp?</h2>
                <p class="text-sm text-slate-500 mb-4">Giáo viên cung cấp mã lớp riêng cho từng lớp — nhập đúng mã để tham gia ngay.</p>
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
        @endif
    </div>
@endsection
