@extends('layouts.guest')

@section('title', $course->title)
@section('meta-description', \Illuminate\Support\Str::limit(strip_tags((string) $course->description), 155) ?: 'Khoá học '.$course->title.' trên Ôn Thi 360 — nội dung, lớp đang mở, học phí và đánh giá của học viên.')

@section('content')
{{-- ═══════════════ [COURSE-SHOW] CHI TIẾT KHOÁ HỌC ═══════════════
     SỬA 14/9 — CHỈ ĐỔI GIAO DIỆN: dựng lại theo ngôn ngữ thị giác của trang Khoá học và các
     trang công khai đã làm. Ba thứ được sửa kèm vì cùng là lỗi hiển thị:
       · ẢNH: bỏ picsum.photos (ảnh ngẫu nhiên từ máy chủ ngoài) — dùng ảnh bìa thật của khoá
         học, thiếu thì lấy bộ ảnh sẵn có như ở trang danh sách.
       · AVATAR GIÁO VIÊN: bỏ ui-avatars.com — dịch vụ này nhận HỌ TÊN THẬT của giáo viên qua
         URL ở mỗi lần tải trang. Thay bằng x-ws.avatar vẽ chữ cái đầu ngay tại chỗ.
     Logic, route và mọi điều kiện hiển thị giữ nguyên. --}}
    @php
        $classes = $classes ?? [];
        $ratingAverage = $ratingAverage ?? null;
        $ratingCount = $ratingCount ?? 0;
        $isStudent = $isStudent ?? false;
        $myClassRoomIdsInThisCourse = $myClassRoomIdsInThisCourse ?? [];
        $isEnrolledInThisCourse = count($myClassRoomIdsInThisCourse) > 0;

        // Cùng bộ ảnh dự phòng với trang danh sách khoá học để hai màn nhìn liền mạch.
        $fallbackCovers = ['course-img-1.png', 'course-img-2.png', 'course-img-3.png', 'course-img-4.png', 'course-img-5.png'];
        $coverUrl = $course->cover_image_path
            ? asset('storage/'.$course->cover_image_path)
            : asset('assets/'.$fallbackCovers[$course->id % count($fallbackCovers)]);

        $totalStudents = 0;
        foreach ($classes as $cl) { $totalStudents += (int) ($cl['studentsCount'] ?? 0); }
    @endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div class="flex flex-col gap-4 sm:gap-5">

    {{-- ══════ 1. ĐƯỜNG DẪN QUAY LẠI ══════ --}}
    <a href="{{ route('courses.index') }}"
       class="inline-flex w-fit items-center gap-1.5 rounded-xl border border-sky-100 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-600 shadow-2xs transition-colors hover:border-sky-200 hover:text-blue-700">
        <x-lucide name="arrow-left" class="h-3.5 w-3.5" />Quay lại Lớp học
    </a>

    {{-- ══════ 2. HERO KHOÁ HỌC ══════ --}}
    <section class="relative overflow-hidden rounded-3xl border border-sky-200/90 bg-gradient-to-r from-[#0B3C78] via-[#0284C7] to-[#38BDF8] shadow-[0_10px_35px_rgba(0,100,220,0.08)]">
        <img src="{{ $coverUrl }}" alt="" loading="eager" decoding="async"
             class="pointer-events-none absolute inset-0 h-full w-full select-none object-cover opacity-35">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-[#07326B]/95 via-[#0759a8]/80 to-[#0976c9]/35"></div>

        <div class="relative flex flex-col gap-5 p-5 text-white sm:p-7 lg:flex-row lg:items-center">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5">
                    @if ($course->subject)
                        <span class="inline-flex items-center gap-1 rounded-full border border-white/25 bg-white/15 px-2.5 py-0.5 text-[11px] font-bold text-white backdrop-blur-sm">
                            <x-lucide name="book-open" class="h-3 w-3" />{{ $course->subject }}
                        </span>
                    @endif
                    @if ($course->grade)
                        <span class="inline-flex items-center gap-1 rounded-full border border-white/25 bg-white/15 px-2.5 py-0.5 text-[11px] font-bold text-white backdrop-blur-sm">
                            <x-lucide name="graduation-cap" class="h-3 w-3" />{{ $course->grade }}
                        </span>
                    @endif
                </div>

                <h1 class="type-hero-title mt-2.5 text-[24px] leading-tight text-white sm:text-[30px]">{{ $course->title }}</h1>

                <div class="mt-2 [&_span]:text-sky-100">
                    <x-rating-summary :average="$ratingAverage" :count="$ratingCount" />
                </div>

                <div class="rich-content mt-3 max-w-2xl text-[13px] leading-relaxed text-sky-50">{!! $course->description ?: 'Chưa có mô tả chi tiết.' !!}</div>

                <div class="mt-5">
                    @if (! auth()->check())
                        <a href="{{ route('login') }}"
                           class="inline-flex min-h-11 items-center gap-1.5 rounded-2xl bg-white px-5 text-[13px] font-bold text-[#0B3C78] shadow-sm transition-colors hover:bg-sky-50">
                            <x-lucide name="log-in" class="h-4 w-4" />Đăng nhập để đăng ký / mua quyền
                        </a>
                    @elseif ($isStudent && $isEnrolledInThisCourse)
                        <a href="{{ count($myClassRoomIdsInThisCourse) === 1 ? route('student.classes.show', $myClassRoomIdsInThisCourse[0]) : route('student.courses.index') }}"
                           class="inline-flex min-h-11 items-center gap-1.5 rounded-2xl bg-white px-5 text-[13px] font-bold text-[#0B3C78] shadow-sm transition-colors hover:bg-sky-50">
                            <x-lucide name="school" class="h-4 w-4" />Xem lớp học của tôi
                            <x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                        </a>
                    @elseif ($isStudent)
                        <a href="#tham-gia-lop"
                           class="inline-flex min-h-11 items-center gap-1.5 rounded-2xl bg-white px-5 text-[13px] font-bold text-[#0B3C78] shadow-sm transition-colors hover:bg-sky-50">
                            <x-lucide name="key-round" class="h-4 w-4" />Nhập mã lớp để tham gia
                        </a>
                    @else
                        {{-- Vai trò khác (giáo viên/phụ huynh/admin) — giữ nguyên hành vi cũ. --}}
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex min-h-11 items-center gap-1.5 rounded-2xl bg-white px-5 text-[13px] font-bold text-[#0B3C78] shadow-sm transition-colors hover:bg-sky-50">
                            <x-lucide name="school" class="h-4 w-4" />Xem lớp học của tôi
                        </a>
                    @endif
                </div>
            </div>

            <img src="{{ $coverUrl }}" alt="Ảnh bìa khoá học {{ $course->title }}" loading="lazy" decoding="async"
                 class="relative w-full shrink-0 rounded-2xl border border-white/25 object-cover shadow-[0_10px_28px_rgba(4,40,80,0.28)] lg:w-72 xl:w-80"
                 style="aspect-ratio: 4 / 3;">
        </div>
    </section>

    {{-- ══════ 3. THÔNG TIN NHANH ══════ --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Lớp đang mở', 'value' => count($classes), 'icon' => 'school', 'tone' => 'bg-sky-50 text-[#2D7FA3] border-sky-100'],
            ['label' => 'Học viên đang học', 'value' => $totalStudents, 'icon' => 'users', 'tone' => 'bg-emerald-50 text-[#3B9374] border-emerald-100'],
            ['label' => 'Môn học', 'value' => $course->subject ?: '—', 'icon' => 'book-open', 'tone' => 'bg-violet-50 text-[#786BB1] border-violet-100'],
            ['label' => 'Đối tượng', 'value' => $course->grade ?: '—', 'icon' => 'graduation-cap', 'tone' => 'bg-amber-50 text-[#AF7C32] border-amber-100'],
        ] as $stat)
            <div class="flex items-center gap-3 rounded-3xl border border-sky-100 bg-white p-3.5 shadow-[0_2px_10px_rgba(0,100,220,0.04)]">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl border {{ $stat['tone'] }}">
                    <x-lucide :name="$stat['icon']" class="h-4.5 w-4.5" />
                </span>
                <span class="min-w-0">
                    <span class="type-meta block text-slate-400">{{ $stat['label'] }}</span>
                    <span class="mt-0.5 block truncate text-[15px] font-black text-[#0B3C78]">{{ $stat['value'] }}</span>
                </span>
            </div>
        @endforeach
    </div>

    {{-- ══════ 4. CÁC LỚP ĐANG TRIỂN KHAI ══════ --}}
    <section class="rounded-3xl border border-sky-100 bg-white p-4 shadow-[0_2px_10px_rgba(0,100,220,0.04)] sm:p-5">
        <div class="mb-3 flex items-center gap-2.5">
            <span class="grid h-8.5 w-8.5 place-items-center rounded-xl bg-blue-600 text-white shadow-2xs">
                <x-lucide name="school" class="h-4.5 w-4.5" />
            </span>
            <div>
                <h2 class="type-section-title">Các lớp đang triển khai</h2>
                <p class="type-body mt-0.5 text-slate-500">Chọn lớp phù hợp với lịch học của bạn</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($classes as $cl)
                <div class="flex flex-col justify-between rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3.5 transition-all hover:border-sky-200 hover:shadow-md">
                    <div class="flex items-start justify-between gap-2.5">
                        <p class="type-card-title min-w-0 flex-1">{{ $cl['name'] }}</p>
                        @if ($cl['isMember'] ?? false)
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                <x-lucide name="check-circle-2" class="h-3 w-3" />Đã tham gia
                            </span>
                        @else
                            <span class="inline-flex shrink-0 items-center rounded-full border border-sky-200 bg-white px-2 py-0.5 text-[10px] font-bold text-sky-700">Đang mở</span>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center gap-2 border-t border-sky-100 pt-2.5">
                        <x-ws.avatar :name="$cl['teacher']" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="type-meta block text-slate-400">Giáo viên phụ trách</span>
                            <span class="block truncate text-[12px] font-bold text-slate-700">{{ $cl['teacher'] }}</span>
                        </span>
                    </div>

                    <p class="type-meta mt-2 flex items-center gap-1 text-slate-500">
                        <x-lucide name="users" class="h-3 w-3 shrink-0" />{{ $cl['studentsCount'] }} học sinh đã tham gia
                    </p>
                </div>
            @empty
                <div class="sm:col-span-2 xl:col-span-3">
                    <div class="rounded-2xl border border-dashed border-sky-200 bg-[#F8FBFE] p-8 text-center">
                        <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-white text-sky-600 shadow-2xs">
                            <x-lucide name="school" class="h-6 w-6" />
                        </span>
                        <p class="type-card-title mt-3">Chưa có lớp nào đang triển khai</p>
                        <p class="type-body mt-1 text-slate-500">Quay lại sau hoặc chọn lớp học khác.</p>
                        <a href="{{ route('courses.index') }}" class="type-action mt-3 inline-block text-blue-600 hover:underline">Xem các lớp khác →</a>
                    </div>
                </div>
            @endforelse
        </div>
    </section>

    {{-- ══════ 5. THAM GIA BẰNG MÃ LỚP ══════ --}}
    @if ($isStudent)
        <section id="tham-gia-lop" class="scroll-mt-20 rounded-3xl border border-sky-100 bg-white p-4 shadow-[0_2px_10px_rgba(0,100,220,0.04)] sm:p-5">
            <div class="mb-3 flex items-center gap-2.5">
                <span class="grid h-8.5 w-8.5 place-items-center rounded-xl bg-amber-50 text-amber-700">
                    <x-lucide name="key-round" class="h-4.5 w-4.5" />
                </span>
                <div>
                    <h2 class="type-section-title">Có mã lớp?</h2>
                    <p class="type-body mt-0.5 text-slate-500">Giáo viên cấp mã riêng cho từng lớp — nhập đúng mã để tham gia ngay</p>
                </div>
            </div>

            <form method="POST" action="{{ route('student.classes.join') }}" class="flex max-w-md flex-col gap-2.5 sm:flex-row">
                @csrf
                <input type="text" name="code" placeholder="Ví dụ: 10CT-2026" aria-label="Mã lớp"
                       class="h-11 flex-1 rounded-xl border bg-[#F0F6FC] px-3.5 text-xs font-medium text-slate-700 outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100 {{ $errors->has('code') ? 'border-rose-300' : 'border-sky-200' }}">
                <button type="submit"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-[#126F91] px-5 text-[12px] font-bold text-white shadow-[0_5px_12px_rgba(18,111,145,0.18)] transition-colors hover:bg-[#0F5F7A]">
                    <x-lucide name="plus" class="h-4 w-4" />Tham gia lớp
                </button>
            </form>
            @error('code')
                <p class="mt-2 flex items-center gap-1 text-[11px] font-medium text-rose-600">
                    <x-lucide name="info" class="h-3 w-3 shrink-0" />{{ $message }}
                </p>
            @enderror
        </section>
    @endif
</div>
</div>
@endsection

@push('scripts')
    <style>
        .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .rich-content p { margin-bottom: 0.5rem; }
        .rich-content a { color: #126F91; text-decoration: underline; }
    </style>
@endpush
