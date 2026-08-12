{{--
  Route: teachers.index | Frame: PUB-10
  Spec: 12.2 (trang vinh danh, không phải danh bạ cá nhân).
  TODO controller: truyền $teachers được chọn công bố.
--}}
@extends('layouts.guest')

@section('title', 'Giáo viên tiêu biểu')

@section('content')
    @php
        $teachers = [
            ['id' => 1, 'name' => 'Nguyễn Văn A', 'subject' => 'Tin học', 'achievement' => '5 năm giảng dạy · 12 lớp đã phụ trách'],
            ['id' => 2, 'name' => 'Lê Văn C', 'subject' => 'Toán', 'achievement' => '3 năm giảng dạy · HSG cấp tỉnh'],
        ];
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-10">
        <x-page-header title="Giáo viên tiêu biểu" subtitle="Vinh danh và tạo niềm tin — không phải danh bạ có số điện thoại cá nhân (12.2)." />

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($teachers as $t)
                <a href="{{ route('teachers.show', $t['id']) }}" class="rounded-2xl bg-white border border-slate-200 p-5 text-center">
                    <div class="w-16 h-16 rounded-full bg-slate-100 mx-auto mb-3"></div>
                    <p class="font-medium text-slate-700">{{ $t['name'] }}</p>
                    <p class="text-sm text-slate-400">{{ $t['subject'] }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ $t['achievement'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
@endsection
