{{--
  Route: admin.dashboard | Frame: ADM-01
  Spec: 2.1 (mục tiêu vận hành) + 16 mục 9 (quan sát vận hành: hàng đợi
  chấm, lỗi runner/trích xuất, đơn/mã bất thường, quyền dạy sắp/hết hạn,
  review pending/reported).

  TODO controller: truyền các số liệu thật thay cho mock $stats/$activity
  dưới đây (giữ đúng tên biến để không phải sửa view).
--}}
@extends('layouts.admin')

@section('title', 'Tổng quan quản trị')
@section('page-title', 'Tổng quan')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\DashboardController truyền vào. --}}
    @php
        $stats = $stats ?? [];
        $activity = $activity ?? [];
    @endphp

    <x-page-header title="Tổng quan" subtitle="Số liệu vận hành theo thời gian thực (2.1, 16 mục 9)." />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        @foreach ($stats as $s)
            <a href="{{ $s['href'] }}" class="block">
                <x-stat-tile :label="$s['label']" :value="$s['value']" :tone="$s['tone']" />
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-4">Hoạt động gần đây</h2>
            <ul class="space-y-3">
                @forelse ($activity as $a)
                    <li class="flex items-start gap-3 text-sm">
                        <span class="w-2 h-2 rounded-full bg-rose-400 mt-1.5 shrink-0"></span>
                        <div>
                            <p class="text-slate-700">{{ $a['text'] }}</p>
                            <p class="text-xs text-slate-400">{{ $a['time'] }} · {{ $a['actor'] }}</p>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-400">Chưa có hoạt động nào được ghi nhận.</li>
                @endforelse
            </ul>
            {{-- TODO: link "Xem toàn bộ audit log" khi có màn audit log riêng --}}
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-4">Thao tác nhanh</h2>
            <div class="space-y-2 text-sm">
                <a href="{{ route('admin.teacher-approvals.index') }}" class="block px-3 py-2 rounded-lg bg-slate-50 hover:bg-rose-50">Duyệt giáo viên</a>
                <a href="{{ route('admin.orders.index') }}" class="block px-3 py-2 rounded-lg bg-slate-50 hover:bg-rose-50">Duyệt đơn hàng</a>
                <a href="{{ route('admin.reviews.index') }}" class="block px-3 py-2 rounded-lg bg-slate-50 hover:bg-rose-50">Kiểm duyệt review</a>
                <a href="{{ route('admin.content.index') }}" class="block px-3 py-2 rounded-lg bg-slate-50 hover:bg-rose-50">Rà soát câu hỏi OCR</a>
            </div>
        </div>
    </div>
@endsection
