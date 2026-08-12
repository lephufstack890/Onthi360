{{--
  Route: admin.settings.index
  Spec: 3.1 (Super Admin: cấu hình role, chính sách, tích hợp).
  TODO: chỉ Super Admin mới thấy trang này — kiểm tra thêm Policy/middleware riêng.
  TODO controller: SettingsController hiện chưa nối logic lưu cấu hình thật —
  4 khối dưới đây là khung UI minh họa, nút "Cấu hình" đang tắt (chưa có
  route/hành động thật) để không hứa nhầm chức năng chưa có (2.2).
--}}
@extends('layouts.admin')

@section('title', 'Cấu hình hệ thống')
@section('page-title', 'Cấu hình')

@section('content')
    <x-page-header title="⚙️ Cấu hình hệ thống" subtitle="Chỉ Super Admin có toàn quyền cấu hình role, chính sách và tích hợp (3.1)." />

    @php
        $settingGroups = [
            [
                'emoji' => '🛡️', 'tone' => 'violet',
                'title' => 'Vai trò & quyền',
                'desc' => 'Quản lý danh sách vai trò hệ thống và ma trận quyền theo từng vai trò (3.2).',
                'items' => ['Danh sách vai trò hệ thống', 'Ma trận quyền theo vai trò'],
            ],
            [
                'emoji' => '💳', 'tone' => 'emerald',
                'title' => 'Tích hợp thanh toán',
                'desc' => 'Bật/tắt cổng thanh toán và cấu hình xác nhận thanh toán ngoài hệ thống (7.4).',
                'items' => ['Bật/tắt VNPAY', 'Cấu hình thanh toán ngoài hệ thống'],
            ],
            [
                'emoji' => '🔍', 'tone' => 'sky',
                'title' => 'OCR / nhập đề',
                'desc' => 'Chọn engine OCR dùng để trích đề và ngưỡng cảnh báo khi nhận dạng kém (18 mục 7).',
                'items' => ['Chọn engine OCR', 'Ngưỡng gắn cờ nhận dạng kém'],
            ],
            [
                'emoji' => '⭐', 'tone' => 'amber',
                'title' => 'Chính sách đánh giá',
                'desc' => 'Ngưỡng số review tối thiểu để xếp hạng và thời hạn cho phép sửa review (9.2, 9.5).',
                'items' => ['Ngưỡng tối thiểu 5 review để xếp hạng', 'Thời hạn sửa review: 7 ngày'],
            ],
        ];
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach ($settingGroups as $g)
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-center gap-3">
                        <x-icon-tile :emoji="$g['emoji']" :tone="$g['tone']" />
                        <h2 class="font-medium text-slate-700">{{ $g['title'] }}</h2>
                    </div>
                    <x-status-badge tone="neutral">Chưa cấu hình</x-status-badge>
                </div>
                <p class="text-sm text-slate-500 leading-relaxed mb-3">{{ $g['desc'] }}</p>
                <ul class="space-y-1.5 mb-4">
                    @foreach ($g['items'] as $item)
                        <li class="flex items-center gap-2 text-sm text-slate-600"><span class="text-slate-300">•</span>{{ $item }}</li>
                    @endforeach
                </ul>
                <button type="button" disabled title="Sắp mở — chưa nối hành động lưu cấu hình thật"
                        class="w-full px-4 py-2 rounded-lg bg-slate-100 text-slate-400 text-sm font-medium cursor-not-allowed">
                    ⏳ Cấu hình · Sắp mở
                </button>
            </div>
        @endforeach
    </div>
@endsection
