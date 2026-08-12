{{--
  Route: access.checkout | Frame: ACC-03
  Spec: 7.4/7.5 (checkout theo scope: Học cá nhân / Dùng để dạy mọi lớp
  phụ trách; sách mềm bắt buộc, checkbox mua kèm bản in không đổi quyền
  số; P0 chỉ có thanh toán ngoài hệ thống — admin duyệt).
  TODO controller: truyền $product thật + $scopeOptions theo vai trò user
  hiện tại (7.5 — giáo viên chưa duyệt không thấy scope "Dùng để dạy").
--}}
@extends('layouts.guest')

@section('title', 'Đặt đơn')

@section('content')
    {{-- $product, $canTeach, $printPrice do App\Http\Controllers\Access\AccessController truyền vào. --}}
    @php
        $canTeach = $canTeach ?? false;
        $printPrice = $printPrice ?? 50000;
    @endphp

    <div class="max-w-2xl mx-auto px-4 py-10">
        <a href="{{ route('materials.show', 1) }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại</a>

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h1 class="text-lg font-semibold text-slate-800 mb-1">Đặt đơn</h1>
            <p class="text-sm text-slate-500 mb-6">Tạo đơn ≠ đã thanh toán ≠ đã có quyền — thời hạn chỉ bắt đầu khi bạn kích hoạt mã (7.4).</p>

            <div class="flex items-center justify-between p-4 rounded-xl bg-slate-50 mb-4">
                <div>
                    <p class="font-medium text-slate-700">{{ $product->title }}</p>
                    <p class="text-xs text-slate-400">Bản mềm — bắt buộc trong đơn số</p>
                </div>
                <p class="font-medium text-slate-700">{{ number_format($product->price) }}đ</p>
            </div>

            <label class="flex items-center justify-between p-4 rounded-xl border border-slate-200 mb-6 cursor-pointer">
                <span class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox"> Mua kèm bản in (giao hàng riêng, không đổi quyền số)
                </span>
                <span class="text-sm text-slate-500">+{{ number_format($printPrice) }}đ</span>
            </label>

            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-600 mb-2">Phạm vi quyền nhận</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-rose-300 bg-rose-50 cursor-pointer">
                        <input type="radio" name="scope" checked>
                        <div>
                            <p class="text-sm font-medium text-slate-700">Học cá nhân</p>
                            <p class="text-xs text-slate-500">Đọc/làm/tự luyện nội dung trong thời hạn (7.5).</p>
                        </div>
                    </label>
                    @if ($canTeach)
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer">
                            <input type="radio" name="scope">
                            <div>
                                <p class="text-sm font-medium text-slate-700">Dùng để dạy (mọi lớp phụ trách)</p>
                                <p class="text-xs text-slate-500">Áp dụng cho mọi lớp bạn đang phụ trách, không giới hạn số lớp (7.2).</p>
                            </div>
                        </label>
                    @else
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer opacity-50">
                            <input type="radio" name="scope" disabled>
                            <div>
                                <p class="text-sm font-medium text-slate-700">Dùng để dạy (mọi lớp phụ trách)</p>
                                <p class="text-xs text-slate-500">Cần hoàn tất phê duyệt giáo viên trước (7.2, 7.5).</p>
                            </div>
                        </label>
                    @endif
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-600 mb-2">Phương thức thanh toán</label>
                <select class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    <option>Thanh toán ngoài hệ thống — admin duyệt</option>
                    <option disabled>VNPAY (sắp mở)</option>
                </select>
            </div>

            <div class="flex items-center justify-between border-t border-slate-100 pt-4 mb-6">
                <span class="text-sm text-slate-500">Tổng tiền</span>
                <span class="text-xl font-semibold text-slate-800">{{ number_format($product->price) }}đ</span>
            </div>

            <button type="button" class="w-full px-5 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium">Đặt đơn</button>
            <p class="text-xs text-slate-400 text-center mt-3">Sau khi admin duyệt, bạn sẽ nhận mã kích hoạt qua email/thông báo.</p>
        </div>
    </div>
@endsection
