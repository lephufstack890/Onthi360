{{--
  Route: access.checkout | Frame: ACC-03
  Spec: 7.4/7.5 (checkout theo scope: Học cá nhân / Dùng để dạy mọi lớp
  phụ trách; sách mềm bắt buộc, checkbox mua kèm bản in không đổi quyền
  số; P0 chỉ có thanh toán ngoài hệ thống — admin duyệt).
  $product/$canTeach/$printPrice/$tokenBalance là dữ liệu thật do
  App\Http\Controllers\Access\AccessController::checkout() truyền vào qua
  App\Services\Access\AccessService::checkoutData() (7.5 — giáo viên chưa
  duyệt không thấy scope "Dùng để dạy"). SỬA 25/8: nút "Đặt đơn" giờ submit
  thật (POST access.checkout.store) — xem AccessService::placeOrder().
  SỬA 25/8 (2): thêm phương thức thanh toán Token (trừ ví, cấp quyền ngay)
  bên cạnh Offline — xem $tokenBalance. Đặt thành công giờ chuyển thẳng
  sang materials.show (không quay lại trang này nữa), nên banner
  'order-placed' cũ đã bỏ; lỗi vẫn quay lại đây qua $errors.
--}}
@extends('layouts.guest')

@section('title', 'Đặt đơn')

@section('content')
    {{-- $product, $canTeach, $printPrice, $tokenBalance do App\Http\Controllers\Access\AccessController truyền vào. --}}
    @php
        $canTeach = $canTeach ?? false;
        $printPrice = $printPrice ?? 50000;
        $tokenBalance = $tokenBalance ?? 0;
    @endphp

    <div class="max-w-2xl mx-auto px-4 py-10">
        <a href="{{ route('materials.show', $product->id) }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại</a>

        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 p-6" x-data="{ includePrint: false, scope: 'personal_learning', paymentMethod: 'offline' }">
            <h1 class="text-lg font-semibold text-slate-800 mb-1">Đặt đơn</h1>
            <p class="text-sm text-slate-500 mb-6">Tạo đơn ≠ đã thanh toán ≠ đã có quyền — thời hạn chỉ bắt đầu khi bạn kích hoạt mã (trả bằng token thì có quyền ngay lập tức)</p>

            <form method="POST" action="{{ route('access.checkout.store', $product->id) }}">
                @csrf

                <div class="flex items-center justify-between p-4 rounded-xl bg-slate-50 mb-4">
                    <div>
                        <p class="font-medium text-slate-700">{{ $product->title }}</p>
                        <p class="text-xs text-slate-400">Bản mềm — bắt buộc trong đơn số</p>
                    </div>
                    <p class="font-medium text-slate-700">{{ number_format($product->price) }}đ</p>
                </div>

                <label class="flex items-center justify-between p-4 rounded-xl border border-slate-200 mb-6 cursor-pointer">
                    <span class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="include_print" value="1" x-model="includePrint"> Mua kèm bản in (giao hàng riêng, không đổi quyền số)
                    </span>
                    <span class="text-sm text-slate-500">+{{ number_format($printPrice) }}đ</span>
                </label>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-600 mb-2">Phạm vi quyền nhận</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer" :class="scope === 'personal_learning' ? 'border-rose-300 bg-rose-50' : 'border-slate-200'">
                            <input type="radio" name="scope" value="personal_learning" x-model="scope">
                            <div>
                                <p class="text-sm font-medium text-slate-700">Học cá nhân</p>
                                <p class="text-xs text-slate-500">Đọc/làm/tự luyện nội dung trong thời hạn (7.5).</p>
                            </div>
                        </label>
                        @if ($canTeach)
                            <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer" :class="scope === 'teacher_teaching' ? 'border-rose-300 bg-rose-50' : 'border-slate-200'">
                                <input type="radio" name="scope" value="teacher_teaching" x-model="scope">
                                <div>
                                    <p class="text-sm font-medium text-slate-700">Dùng để dạy (mọi lớp phụ trách)</p>
                                    <p class="text-xs text-slate-500">Áp dụng cho mọi lớp bạn đang phụ trách, không giới hạn số lớp (7.2).</p>
                                </div>
                            </label>
                        @else
                            <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer opacity-50">
                                <input type="radio" disabled>
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
                    <div class="space-y-2">
                        <label class="flex items-center justify-between gap-3 p-3 rounded-xl border cursor-pointer" :class="paymentMethod === 'token' ? 'border-rose-300 bg-rose-50' : 'border-slate-200'">
                            <span class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="token" x-model="paymentMethod">
                                <span>
                                    <span class="block text-sm font-medium text-slate-700">Trả bằng token trong ví</span>
                                    <span class="block text-xs text-slate-500">Trừ ví ngay, có quyền đọc ngay lập tức — không chờ admin duyệt.</span>
                                </span>
                            </span>
                            <span class="text-xs font-medium shrink-0" :class="{{ (int) $tokenBalance }} >= (({{ (int) $product->price }} + (includePrint ? {{ (int) $printPrice }} : 0)) ) ? 'text-emerald-600' : 'text-rose-500'">
                                Số dư: {{ number_format($tokenBalance) }}
                            </span>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer" :class="paymentMethod === 'offline' ? 'border-rose-300 bg-rose-50' : 'border-slate-200'">
                            <input type="radio" name="payment_method" value="offline" x-model="paymentMethod">
                            <div>
                                <p class="text-sm font-medium text-slate-700">Thanh toán ngoài hệ thống — admin duyệt</p>
                                <p class="text-xs text-slate-500">Chuyển khoản như hiện nay — chờ admin duyệt rồi kích hoạt mã sau.</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer opacity-50">
                            <input type="radio" disabled>
                            <p class="text-sm font-medium text-slate-700">VNPAY (sắp mở)</p>
                        </label>
                    </div>
                    <p class="text-xs text-slate-400 mt-2" x-show="paymentMethod === 'token'" x-cloak>Không đủ token sẽ được chuyển sang trang nạp token để nạp thêm.</p>
                </div>

                <div class="flex items-center justify-between border-t border-slate-100 pt-4 mb-6">
                    <span class="text-sm text-slate-500">Tổng tiền</span>
                    <span class="text-xl font-semibold text-slate-800" x-text="(({{ (int) $product->price }} + (includePrint ? {{ (int) $printPrice }} : 0)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')) + 'đ'"></span>
                </div>

                <button type="submit" class="w-full px-5 py-3 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition">Đặt đơn</button>
                <p class="text-xs text-slate-400 text-center mt-3" x-show="paymentMethod === 'offline'">Sau khi admin duyệt, bạn sẽ nhận mã kích hoạt qua email/thông báo.</p>
                <p class="text-xs text-slate-400 text-center mt-3" x-show="paymentMethod === 'token'" x-cloak>Đặt xong sẽ có quyền đọc ngay và chuyển thẳng sang trang tài liệu.</p>
            </form>
        </div>
    </div>

    @push('scripts')
        <style>[x-cloak] { display: none !important; }</style>
    @endpush
@endsection
