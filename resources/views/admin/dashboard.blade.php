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
    @php
        $stats = [
            ['label' => 'Giáo viên chờ duyệt', 'value' => 4, 'tone' => 'warning', 'href' => route('admin.teacher-approvals.index')],
            ['label' => 'Đơn hàng chờ duyệt', 'value' => 7, 'tone' => 'warning', 'href' => route('admin.orders.index')],
            ['label' => 'Review chờ kiểm duyệt', 'value' => 12, 'tone' => 'warning', 'href' => route('admin.reviews.index')],
            ['label' => 'Quyền dạy sắp hết hạn (7 ngày)', 'value' => 3, 'tone' => 'danger', 'href' => route('admin.access-rights.index')],
            ['label' => 'Tổng người dùng', 'value' => '1.284', 'tone' => 'neutral', 'href' => route('admin.users.index')],
            ['label' => 'Câu hỏi chờ rà soát (OCR)', 'value' => 9, 'tone' => 'warning', 'href' => route('admin.content.index')],
        ];

        $activity = [
            ['time' => '10 phút trước', 'text' => 'Admin duyệt giáo viên "Nguyễn Văn A"', 'actor' => 'admin@onthi360.test'],
            ['time' => '32 phút trước', 'text' => 'Đơn hàng #OD-1042 được duyệt, cấp mã kích hoạt', 'actor' => 'admin@onthi360.test'],
            ['time' => '1 giờ trước', 'text' => 'Review lớp "10CT-2026" được công bố', 'actor' => 'editor@onthi360.test'],
            ['time' => '3 giờ trước', 'text' => 'Câu hỏi #Q-3391 bị chặn phát hành: thiếu test ẩn', 'actor' => 'system'],
        ];
    @endphp

    <x-page-header title="Tổng quan" subtitle="Số liệu vận hành theo thời gian thực — dữ liệu mẫu, chờ nối API." />

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
                @foreach ($activity as $a)
                    <li class="flex items-start gap-3 text-sm">
                        <span class="w-2 h-2 rounded-full bg-rose-400 mt-1.5 shrink-0"></span>
                        <div>
                            <p class="text-slate-700">{{ $a['text'] }}</p>
                            <p class="text-xs text-slate-400">{{ $a['time'] }} · {{ $a['actor'] }}</p>
                        </div>
                    </li>
                @endforeach
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
