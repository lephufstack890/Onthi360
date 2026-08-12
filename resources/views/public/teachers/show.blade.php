{{--
  Route: teachers.show
  Spec: 12.2 + 10.2 (hồ sơ, thành tích, khóa/lớp phụ trách được phép công
  bố). Không hiện số điện thoại/địa chỉ cá nhân (12.2).
  TODO controller: truyền $teacher thật + $classesTaught (chỉ lớp cho
  phép công bố) — hiện là dữ liệu minh họa để dựng UI; avatar dùng
  ui-avatars.com tạm.
--}}
@extends('layouts.guest')

@section('title', 'Hồ sơ giáo viên')

@section('content')
    @php
        $teacher = [
            'name' => 'Nguyễn Văn A',
            'subject' => 'Tin học',
            'bio' => 'Tốt nghiệp Sư phạm Tin học, có 5 năm kinh nghiệm giảng dạy ôn thi chuyên và luyện thi học sinh giỏi cấp tỉnh. Tập trung xây nền tảng thuật toán vững chắc trước khi luyện đề.',
            'achievement' => '5 năm giảng dạy · 12 lớp đã phụ trách · HLV đội tuyển HSG Tin 2024',
        ];
        $classes = [
            ['name' => '10CT-2026', 'students' => 32],
            ['name' => '11HSG-2026', 'students' => 18],
        ];
    @endphp

    <div class="max-w-3xl mx-auto px-4 py-10">
        <a href="{{ route('teachers.index') }}" class="text-sm text-slate-500 mb-6 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Giáo viên tiêu biểu</a>

        <div class="rounded-3xl bg-gradient-to-br from-rose-50 via-white to-amber-50 border border-slate-200 p-8 text-center mb-6">
            <img src="https://ui-avatars.com/api/?name={{ urlencode($teacher['name']) }}&background=e11d48&color=ffffff&size=160&bold=true"
                 alt="{{ $teacher['name'] }}" class="w-24 h-24 rounded-full mx-auto mb-3 shadow-md">
            <h1 class="text-xl font-semibold text-slate-800">{{ $teacher['name'] }}</h1>
            <p class="text-rose-600 mt-0.5">{{ $teacher['subject'] }}</p>
            <p class="text-xs text-slate-400 mt-2">{{ $teacher['achievement'] }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-4">
            <h2 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span>📝</span> Giới thiệu</h2>
            <p class="text-sm text-slate-500 leading-relaxed">{{ $teacher['bio'] }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🏫</span> Lớp đang phụ trách (được phép công bố)</h2>
            <div class="space-y-2">
                @forelse ($classes as $c)
                    <div class="flex items-center justify-between px-4 py-3 rounded-lg bg-slate-50 text-sm">
                        <span class="text-slate-700 font-medium">{{ $c['name'] }}</span>
                        <span class="text-slate-400">{{ $c['students'] }} học sinh</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">Chưa có lớp nào được phép công bố.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
