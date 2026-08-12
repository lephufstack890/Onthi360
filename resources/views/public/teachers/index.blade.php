{{--
  Route: teachers.index | Frame: PUB-10
  Spec: 12.2 (trang vinh danh, không phải danh bạ cá nhân — không hiện số
  điện thoại/địa chỉ cá nhân).
  TODO controller: truyền $teachers được chọn công bố — hiện là dữ liệu
  minh họa để dựng UI; avatar dùng ui-avatars.com tạm.
--}}
@extends('layouts.guest')

@section('title', 'Giáo viên tiêu biểu')

@section('content')
    @php
        $teachers = [
            ['id' => 1, 'name' => 'Nguyễn Văn A', 'subject' => 'Tin học', 'achievement' => '5 năm giảng dạy · 12 lớp đã phụ trách'],
            ['id' => 2, 'name' => 'Lê Văn C', 'subject' => 'Toán', 'achievement' => '3 năm giảng dạy · HSG cấp tỉnh'],
            ['id' => 3, 'name' => 'Trần Thị B', 'subject' => 'Vật lý', 'achievement' => '7 năm giảng dạy · Ôn thi chuyên'],
            ['id' => 4, 'name' => 'Phạm Minh D', 'subject' => 'Tin học', 'achievement' => '4 năm giảng dạy · HLV đội tuyển tin'],
        ];
    @endphp

    <div class="bg-gradient-to-br from-rose-50 via-white to-amber-50">
        <div class="max-w-7xl mx-auto px-4 py-12 lg:py-16 text-center">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white text-rose-600 text-xs font-medium mb-4 shadow-sm">🏆 Vinh danh</span>
            <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800">Giáo viên tiêu biểu</h1>
            <p class="text-slate-500 mt-3 max-w-xl mx-auto">Vinh danh và tạo niềm tin — đây không phải danh bạ cá nhân, không hiển thị số điện thoại hay thông tin liên hệ riêng (12.2).</p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-10">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @forelse ($teachers as $t)
                <a href="{{ route('teachers.show', $t['id']) }}" class="rounded-2xl bg-white border border-slate-200 p-6 text-center hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($t['name']) }}&background=e11d48&color=ffffff&size=128&bold=true"
                         alt="{{ $t['name'] }}" class="w-20 h-20 rounded-full mx-auto mb-3 shadow-sm">
                    <p class="font-medium text-slate-700">{{ $t['name'] }}</p>
                    <p class="text-sm text-rose-600 mt-0.5">{{ $t['subject'] }}</p>
                    <p class="text-xs text-slate-400 mt-2 leading-relaxed">{{ $t['achievement'] }}</p>
                    <span class="inline-block mt-3 text-xs font-medium text-slate-500">Xem hồ sơ ›</span>
                </a>
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Chưa có giáo viên được vinh danh" />
                </div>
            @endforelse
        </div>
    </div>
@endsection
