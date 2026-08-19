@extends('layouts.guest')

@section('title', 'Hồ sơ giáo viên')

@section('content')
    @php
        $teacher = [
            'name' => 'Nguyễn Văn A',
            'subjects' => ['Tin học', 'Thuật toán'],
            'bio' => 'Tốt nghiệp Sư phạm Tin học, có 5 năm kinh nghiệm giảng dạy ôn thi chuyên và luyện thi học sinh giỏi cấp tỉnh. Tập trung xây nền tảng thuật toán vững chắc trước khi luyện đề.',
            'yearsTeaching' => 5,
            'achievements' => [
                '5 năm kinh nghiệm giảng dạy ôn thi chuyên',
                'Đã phụ trách 12 lớp học',
                'HLV đội tuyển học sinh giỏi Tin học 2024',
            ],
            'average' => 4.9,
            'reviewCount' => 36,
        ];
        $classes = [
            ['name' => '10CT-2026', 'students' => 32],
            ['name' => '11HSG-2026', 'students' => 18],
        ];
        $totalStudents = collect($classes)->sum('students');
        $testimonials = [
            ['quote' => 'Thầy dạy dễ hiểu, luôn giải thích rõ vì sao một bài giải đúng hoặc sai — không chỉ cho đáp án.', 'meta' => 'Học sinh đã xác thực · Lớp 10CT-2026'],
            ['quote' => 'Lộ trình luyện đề rất bài bản, mình tự tin hơn hẳn trước kỳ thi học sinh giỏi.', 'meta' => 'Học sinh đã xác thực · Lớp 11HSG-2026'],
        ];
    @endphp

    <div class="max-w-5xl mx-auto px-4 py-10">
        <a href="{{ route('teachers.index') }}" class="text-sm text-slate-500 mb-6 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Giáo viên tiêu biểu</a>

        {{-- Hero: ảnh bìa + avatar nổi --}}
        <div class="rounded-3xl overflow-hidden border border-slate-200 mb-6">
            <div class="relative h-32 lg:h-40 bg-gradient-to-br from-rose-200 via-rose-100 to-amber-100">
                <img src="https://picsum.photos/seed/{{ \Illuminate\Support\Str::slug($teacher['name']) }}-cover/1200/300" alt=""
                     class="w-full h-full object-cover mix-blend-multiply opacity-40">
            </div>
            <div class="bg-white px-6 lg:px-8 pb-6">
                <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($teacher['name']) }}&background=e11d48&color=ffffff&size=160&bold=true"
                         alt="{{ $teacher['name'] }}" class="w-24 h-24 rounded-full border-4 border-white shadow-md shrink-0 -mt-12 relative z-10 bg-white">
                    <div class="flex-1 pb-1">
                        <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">{{ $teacher['name'] }}</h1>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            @foreach ($teacher['subjects'] as $subj)
                                <x-status-badge tone="info">{{ $subj }}</x-status-badge>
                            @endforeach
                        </div>
                    </div>
                    <div class="pb-1"><x-rating-summary :average="$teacher['average']" :count="$teacher['reviewCount']" /></div>
                </div>

                <div class="grid grid-cols-3 gap-4 mt-6 pt-6 border-t border-slate-100 text-center max-w-sm">
                    <div><p class="text-lg font-semibold text-rose-600">{{ $teacher['yearsTeaching'] }}</p><p class="text-xs text-slate-400 mt-0.5">năm giảng dạy</p></div>
                    <div><p class="text-lg font-semibold text-rose-600">{{ count($classes) }}</p><p class="text-xs text-slate-400 mt-0.5">lớp đang phụ trách</p></div>
                    <div><p class="text-lg font-semibold text-rose-600">{{ $totalStudents }}</p><p class="text-xs text-slate-400 mt-0.5">học sinh</p></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-5">
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span>📝</span> Giới thiệu</h2>
                    <p class="text-sm text-slate-500 leading-relaxed">{{ $teacher['bio'] }}</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🏆</span> Thành tích nổi bật</h2>
                    <ul class="space-y-2">
                        @foreach ($teacher['achievements'] as $a)
                            <li class="flex items-start gap-2 text-sm text-slate-600"><span class="text-emerald-500 shrink-0">✓</span>{{ $a }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🏫</span> Lớp đang phụ trách (được phép công bố)</h2>
                    <div class="space-y-2">
                        @forelse ($classes as $c)
                            <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50">
                                <x-icon-tile emoji="🏫" tone="sky" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-700">{{ $c['name'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $c['students'] }} học sinh</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">Chưa có lớp nào được phép công bố.</p>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>💬</span> Học sinh nói gì</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($testimonials as $t)
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p class="text-sm text-slate-600 leading-relaxed">“{{ $t['quote'] }}”</p>
                                <p class="text-xs text-slate-400 mt-2">🔒 {{ $t['meta'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-slate-200 p-5 sticky top-24">
                    <h2 class="font-medium text-slate-700 mb-3">Muốn học cùng {{ $teacher['name'] }}?</h2>
                    <p class="text-sm text-slate-500 leading-relaxed mb-4">
                        Đây là trang vinh danh, không phải danh bạ cá nhân — không có số điện thoại hay liên hệ riêng (12.2).
                        Đăng ký tài khoản và vào khóa học phù hợp để có thể được xếp vào lớp thầy/cô phụ trách.
                    </p>
                    <div class="space-y-2">
                        <a href="{{ route('courses.index') }}" class="block w-full text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition">Xem khóa học đang mở</a>
                        <a href="{{ route('register') }}" class="block w-full text-center px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Đăng ký tài khoản</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
