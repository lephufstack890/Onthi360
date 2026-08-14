{{--
  Route: admin.settings.index (chỉ Super Admin — role:super_admin, routes/web.php)
  Spec: 3.1 (Super Admin: cấu hình role, chính sách, tích hợp) + 18.8 (ngưỡng
  review thành config hệ thống thay vì hard-code).
  Khối "Chính sách đánh giá" và "Tích hợp thanh toán" (ngân hàng nhận chuyển khoản
  token — note họp 13/8, mục 7-8) đã nối logic lưu thật (App\Services\Admin\SettingsService).
  2 khối còn lại (vai trò & quyền, OCR) chưa nối logic lưu thật — giữ khung UI
  minh họa, nút "Cấu hình" tắt để không hứa nhầm chức năng chưa có (2.2).
--}}
@extends('layouts.admin')

@section('title', 'Cấu hình hệ thống')
@section('page-title', 'Cấu hình')

@section('content')
    <x-page-header title="⚙️ Cấu hình hệ thống" subtitle="Chỉ Super Admin có toàn quyền cấu hình role, chính sách và tích hợp (3.1)." />

    @if (session('status') === 'settings-rating-threshold-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu ngưỡng xếp hạng.'])
    @elseif (session('status') === 'settings-wallet-bank-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thông tin ngân hàng nhận chuyển khoản nạp token.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    @php
        $ratingThreshold = $ratingThreshold ?? \App\Models\RatingSummary::MIN_REVIEWS_TO_RANK;
        $walletBankInfo = $walletBankInfo ?? [];
        $placeholderGroups = [
            [
                'emoji' => '🛡️', 'tone' => 'violet',
                'title' => 'Vai trò & quyền',
                'desc' => 'Quản lý danh sách vai trò hệ thống và ma trận quyền theo từng vai trò (3.2).',
                'items' => ['Danh sách vai trò hệ thống', 'Ma trận quyền theo vai trò'],
            ],
            [
                'emoji' => '🔍', 'tone' => 'sky',
                'title' => 'OCR / nhập đề',
                'desc' => 'Chọn engine OCR dùng để trích đề và ngưỡng cảnh báo khi nhận dạng kém (18 mục 7).',
                'items' => ['Chọn engine OCR', 'Ngưỡng gắn cờ nhận dạng kém'],
            ],
        ];
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-3">
                    <x-icon-tile emoji="⭐" tone="amber" />
                    <h2 class="font-medium text-slate-700">Chính sách đánh giá</h2>
                </div>
                <x-status-badge tone="success">Đã cấu hình</x-status-badge>
            </div>
            <p class="text-sm text-slate-500 leading-relaxed mb-3">Ngưỡng số review tối thiểu để công bố xếp hạng (9.5, 18.8) — điều chỉnh không cần release code.</p>

            <form method="POST" action="{{ route('admin.settings.rating-threshold.update') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs text-slate-500 mb-1" for="min_reviews_to_rank">Ngưỡng tối thiểu (số review)</label>
                    <input id="min_reviews_to_rank" name="min_reviews_to_rank" type="number" min="1" max="1000"
                           value="{{ old('min_reviews_to_rank', $ratingThreshold) }}"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                @if ($ratingThresholdUpdatedBy ?? null)
                    <p class="text-xs text-slate-400">Cập nhật lần cuối bởi {{ $ratingThresholdUpdatedBy }} · {{ optional($ratingThresholdUpdatedAt ?? null)->diffForHumans() }}</p>
                @endif
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">
                    Lưu ngưỡng xếp hạng
                </button>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-3">
                    <x-icon-tile emoji="💳" tone="emerald" />
                    <h2 class="font-medium text-slate-700">Tích hợp thanh toán</h2>
                </div>
                <x-status-badge :tone="($walletBankInfo['accountNo'] ?? null) ? 'success' : 'neutral'">{{ ($walletBankInfo['accountNo'] ?? null) ? 'Đã cấu hình' : 'Chưa cấu hình' }}</x-status-badge>
            </div>
            <p class="text-sm text-slate-500 leading-relaxed mb-3">Thông tin ngân hàng nhận chuyển khoản nạp token — hiện kèm mã QR VietQR cho học sinh ở trang Ví token (note họp 13/8, mục 7-8).</p>

            <form method="POST" action="{{ route('admin.settings.wallet-bank.update') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs text-slate-500 mb-1" for="bank_name">Tên ngân hàng</label>
                    <input id="bank_name" name="bank_name" type="text" required maxlength="150"
                           value="{{ old('bank_name', $walletBankInfo['bankName'] ?? '') }}" placeholder="VD: Vietcombank"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1" for="bank_bin">Mã BIN ngân hàng (chuẩn NAPAS — để tạo QR)</label>
                    <input id="bank_bin" name="bank_bin" type="text" required maxlength="10"
                           value="{{ old('bank_bin', $walletBankInfo['bin'] ?? '') }}" placeholder="VD: 970436"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1" for="bank_account_no">Số tài khoản</label>
                    <input id="bank_account_no" name="bank_account_no" type="text" required maxlength="50"
                           value="{{ old('bank_account_no', $walletBankInfo['accountNo'] ?? '') }}"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1" for="bank_account_name">Tên chủ tài khoản</label>
                    <input id="bank_account_name" name="bank_account_name" type="text" required maxlength="150"
                           value="{{ old('bank_account_name', $walletBankInfo['accountName'] ?? '') }}"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                @if ($walletBankInfo['updatedBy'] ?? null)
                    <p class="text-xs text-slate-400">Cập nhật lần cuối bởi {{ $walletBankInfo['updatedBy'] }} · {{ optional($walletBankInfo['updatedAt'] ?? null)->diffForHumans() }}</p>
                @endif
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">
                    Lưu thông tin ngân hàng
                </button>
            </form>
        </div>

        @foreach ($placeholderGroups as $g)
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
