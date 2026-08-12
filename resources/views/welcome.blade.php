{{--
  Route: home | Frame: PUB-01/02
  Spec: 12.1 (cấu trúc trang chủ: hero → lộ trình → năng lực chấm →
  khóa/tài liệu nổi bật → cuộc thi → giáo viên tiêu biểu → cam kết/FAQ).
  TODO controller: truyền $featuredCourses/$featuredMaterials/$upcomingCompetitions/$featuredTeachers thật.
--}}
@extends('layouts.guest')

@section('title', 'Trang chủ')

@section('content')
    @php
        $featuredCourses = [
            ['title' => 'Luyện thi vào 10 Chuyên Tin', 'meta' => '5 lớp đang triển khai', 'average' => 4.8, 'count' => 126],
            ['title' => 'Ôn thi HSG Tin 11', 'meta' => '2 lớp đang triển khai', 'average' => 4.6, 'count' => 42],
        ];
        $featuredMaterials = [
            ['title' => 'Sách: Ôn thi Tin học 10', 'meta' => 'Sách · Bản mềm + bản in', 'average' => 4.7, 'count' => 88],
            ['title' => 'Chuyên đề: Cấu trúc dữ liệu nâng cao', 'meta' => 'Chuyên đề · Cần kích hoạt', 'average' => null, 'count' => 2],
        ];
        $competitions = [
            ['title' => 'Cuộc thi Tin học trẻ 2026', 'meta' => '20/08 - 25/08/2026'],
        ];
        $featuredTeachers = [
            ['name' => 'Nguyễn Văn A', 'subject' => 'Tin học'],
            ['name' => 'Lê Văn C', 'subject' => 'Toán'],
        ];
    @endphp

    {{-- 1. Hero --}}
    <section class="bg-gradient-to-b from-rose-50 to-white">
        <div class="max-w-7xl mx-auto px-4 py-16 lg:py-24 text-center">
            <h1 class="text-3xl lg:text-5xl font-semibold text-slate-800 leading-tight">
                Học có lộ trình, luyện tập và chấm bài — cùng Ôn Thi 360
            </h1>
            <p class="text-slate-500 mt-4 max-w-2xl mx-auto">
                Kế thừa năng lực chấm code của Quinhdao OJ, mở rộng thành một hành trình học hoàn chỉnh
                cho học sinh lớp 6–12, giáo viên và phụ huynh.
            </p>
            <div class="mt-8 flex justify-center gap-3">
                <a href="{{ route('courses.index') }}" class="px-6 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium">Khám phá khóa học</a>
                <a href="{{ route('practice.index') }}" class="px-6 py-3 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Luyện tập ngay</a>
            </div>
        </div>
    </section>

    {{-- 2/3. Lộ trình + năng lực chấm --}}
    <section class="max-w-7xl mx-auto px-4 py-14">
        <h2 class="text-xl font-semibold text-slate-800 mb-6 text-center">Lộ trình học tập</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-center text-sm">
            <div class="rounded-2xl bg-white border border-slate-200 p-5">Chọn khối/mục tiêu</div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5">Vào khóa/lớp</div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5">Luyện tập/kiểm tra</div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5">Theo dõi tiến bộ</div>
        </div>
        <p class="text-center text-sm text-slate-500 mt-6">Chấm được câu lập trình (OJ), trắc nghiệm và điền đáp án — trong cùng một đề.</p>
    </section>

    {{-- 5. Khóa học / Tài liệu nổi bật --}}
    <section class="max-w-7xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Khóa học nổi bật</h2>
            <a href="{{ route('courses.index') }}" class="text-sm text-rose-600 font-medium">Xem tất cả ›</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($featuredCourses as $c)
                <x-card-item :title="$c['title']" :meta="$c['meta']" :average="$c['average']" :count="$c['count']" href="{{ route('courses.index') }}" badgeLabel="Đang mở" badgeTone="success" />
            @endforeach
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Tài liệu nổi bật</h2>
            <a href="{{ route('materials.index') }}" class="text-sm text-rose-600 font-medium">Xem tất cả ›</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($featuredMaterials as $m)
                <x-card-item :title="$m['title']" :meta="$m['meta']" :average="$m['average']" :count="$m['count']" href="{{ route('materials.index') }}" badgeLabel="Cần kích hoạt" badgeTone="warning" />
            @endforeach
        </div>
    </section>

    {{-- 6. Cuộc thi sắp tới --}}
    <section class="max-w-7xl mx-auto px-4 py-10">
        <h2 class="text-xl font-semibold text-slate-800 mb-4">Cuộc thi sắp tới</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($competitions as $comp)
                <a href="{{ route('competitions.index') }}" class="rounded-2xl bg-white border border-slate-200 p-5 flex items-center justify-between">
                    <div>
                        <p class="font-medium text-slate-700">{{ $comp['title'] }}</p>
                        <p class="text-sm text-slate-400">{{ $comp['meta'] }}</p>
                    </div>
                    <span class="text-rose-600 text-sm font-medium">Xem ›</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- 8. Giáo viên tiêu biểu --}}
    <section class="max-w-7xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Giáo viên tiêu biểu</h2>
            <a href="{{ route('teachers.index') }}" class="text-sm text-rose-600 font-medium">Xem tất cả ›</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($featuredTeachers as $t)
                <a href="{{ route('teachers.index') }}" class="rounded-2xl bg-white border border-slate-200 p-5 text-center">
                    <div class="w-16 h-16 rounded-full bg-slate-100 mx-auto mb-3"></div>
                    <p class="font-medium text-slate-700">{{ $t['name'] }}</p>
                    <p class="text-sm text-slate-400">{{ $t['subject'] }}</p>
                </a>
            @endforeach
        </div>
    </section>

    {{-- 9. Cam kết / FAQ --}}
    <section class="max-w-7xl mx-auto px-4 py-14">
        <h2 class="text-xl font-semibold text-slate-800 mb-6 text-center">Câu hỏi thường gặp</h2>
        <div class="max-w-2xl mx-auto space-y-3">
            <div class="rounded-xl bg-white border border-slate-200 p-4">
                <p class="font-medium text-slate-700">Bài công khai có cần đăng nhập không?</p>
                <p class="text-sm text-slate-500 mt-1">Khách xem được; cần đăng nhập để bắt đầu, nộp bài và lưu kết quả.</p>
            </div>
            <div class="rounded-xl bg-white border border-slate-200 p-4">
                <p class="font-medium text-slate-700">Quyền học và quyền dạy khác nhau thế nào?</p>
                <p class="text-sm text-slate-500 mt-1">Quyền dạy của giáo viên không tự cấp quyền học cho học sinh, và ngược lại.</p>
            </div>
        </div>
    </section>
@endsection
