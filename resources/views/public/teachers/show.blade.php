{{--
  Route: teachers.show
  Spec: 12.2 + 10.2 (hồ sơ, thành tích, khóa/lớp phụ trách được phép công bố).
  TODO controller: truyền $teacher thật + $classesTaught (chỉ lớp cho phép công bố).
--}}
@extends('layouts.guest')

@section('title', 'Hồ sơ giáo viên')

@section('content')
    @php
        $teacher = ['name' => 'Nguyễn Văn A', 'subject' => 'Tin học', 'bio' => 'TODO: giới thiệu, kinh nghiệm, thành tích được phép công bố.'];
        $classes = ['10CT-2026', '11HSG-2026'];
    @endphp

    <div class="max-w-3xl mx-auto px-4 py-10">
        <a href="{{ route('teachers.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Giáo viên tiêu biểu</a>

        <div class="text-center mb-6">
            <div class="w-24 h-24 rounded-full bg-slate-100 mx-auto mb-3"></div>
            <h1 class="text-xl font-semibold text-slate-800">{{ $teacher['name'] }}</h1>
            <p class="text-slate-400">{{ $teacher['subject'] }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-4">
            <p class="text-sm text-slate-500">{{ $teacher['bio'] }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-2">Lớp đang phụ trách (được phép công bố)</h2>
            <ul class="text-sm text-slate-500 space-y-1">
                @foreach ($classes as $c)
                    <li>{{ $c }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endsection
