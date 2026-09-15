{{--
  Route: access.checkout | Frame: ACC-03
  Spec: 7.4/7.5 (checkout theo scope: Học cá nhân / Dùng để dạy mọi lớp phụ trách; sách mềm
  bắt buộc, checkbox mua kèm bản in không đổi quyền số).

  $product/$canTeach/$printPrice/$tokenBalance/$isCourse/$courseId do
  App\Services\Access\AccessService::checkoutData() trả về.

  SỬA 15/9 — CHỈ ĐỔI GIAO DIỆN VÀ CÁCH NÓI, KHÔNG ĐỔI LOGIC ĐẶT ĐƠN:
    · Trước đây trang này @extends('layouts.guest') nên bấm mua là văng ra vỏ trang công
      khai, lệch hẳn với Ví token / Kích hoạt mã / Quyền của tôi đã dựng lại. Giờ dùng chung
      khung khu làm việc, vai trò theo đúng người đang đăng nhập.
    · Sản phẩm loại Khóa học (C2): ẩn ô "mua kèm bản in" vì khoá học không có bản in để giao,
      và đường quay lại trỏ về trang khoá học. Tên trường, route và luật tính tiền giữ nguyên.
--}}
@php
    $coUser = auth()->user();
    $coRole = match (true) {
        (bool) $coUser?->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN) => 'admin',
        (bool) $coUser?->hasRole(\App\Models\Role::TEACHER) => 'teacher',
        (bool) $coUser?->hasRole(\App\Models\Role::PARENT) => 'parent',
        default => 'student',
    };
    $canTeach = $canTeach ?? false;
    $printPrice = $printPrice ?? 50000;
    $tokenBalance = (int) ($tokenBalance ?? 0);
    $isCourse = $isCourse ?? false;
    $courseId = $courseId ?? null;

    $backHref = $isCourse && $courseId
        ? route('courses.show', $courseId)
        : route('materials.show', $product->id);
    $backLabel = $isCourse ? 'Quay lại khoá học' : 'Quay lại tài liệu';
@endphp
@extends('layouts.workspace', ['wsRole' => $coRole])

@section('title', 'Đặt đơn · '.$product->title)
@section('page-title', 'Đặt đơn')

@section('content')
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-ws.page-header title="Đặt đơn" :icon="$isCourse ? 'school' : 'book-open'"
                      :back="$backHref" :back-label="$backLabel"
                      :subtitle="$product->title">
        <x-slot:actions>
            <x-ws.btn :href="route('wallet.index')" variant="onhero-ghost" icon="wallet-cards">Ví token</x-ws.btn>
        </x-slot:actions>
    </x-ws.page-header>

    {{-- priceLearning/priceTeaching nạp sẵn vào Alpine để số tiền đổi theo scope đang chọn —
         khớp đúng số sẽ bị trừ ở AccessService::placeOrder(). --}}
    <form method="POST" action="{{ route('access.checkout.store', $product->id) }}"
          class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.5fr_1fr]"
          x-data="{
              includePrint: false,
              scope: 'personal_learning',
              paymentMethod: 'offline',
              priceLearning: {{ (int) $product->price }},
              priceTeaching: {{ (int) $product->price_teaching }},
              printPrice: {{ $isCourse ? 0 : (int) $printPrice }},
              balance: {{ $tokenBalance }},
              get unitPrice() { return this.scope === 'teacher_teaching' ? this.priceTeaching : this.priceLearning; },
              get total() { return this.unitPrice + (this.includePrint ? this.printPrice : 0); },
              money(n) { return new Intl.NumberFormat('vi-VN').format(n) + 'đ'; },
              get enoughToken() { return this.balance >= this.total; },
          }">
        @csrf

        <div class="space-y-4">
            {{-- ══ Phạm vi quyền ══ --}}
            <x-ws.card title="Phạm vi quyền nhận" icon="shield-check">
                <div class="space-y-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-3 transition-colors"
                           :class="scope === 'personal_learning' ? 'border-blue-300 bg-blue-50' : 'border-sky-100 bg-white hover:bg-sky-50'">
                        <input type="radio" name="scope" value="personal_learning" x-model="scope" class="mt-0.5 h-4 w-4 text-blue-600">
                        <span class="min-w-0">
                            <span class="block text-[13px] font-bold text-slate-800">Học cá nhân</span>
                            <span class="mt-0.5 block text-[11.5px] leading-relaxed text-slate-500">
                                @if ($isCourse)
                                    Được chọn lớp và vào học khoá này trong thời hạn quyền.
                                @else
                                    Đọc, làm bài và tự luyện nội dung trong thời hạn quyền.
                                @endif
                            </span>
                        </span>
                    </label>

                    @if ($canTeach)
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-3 transition-colors"
                               :class="scope === 'teacher_teaching' ? 'border-blue-300 bg-blue-50' : 'border-sky-100 bg-white hover:bg-sky-50'">
                            <input type="radio" name="scope" value="teacher_teaching" x-model="scope" class="mt-0.5 h-4 w-4 text-blue-600">
                            <span class="min-w-0">
                                <span class="block text-[13px] font-bold text-slate-800">Dùng để dạy (mọi lớp phụ trách)</span>
                                <span class="mt-0.5 block text-[11.5px] leading-relaxed text-slate-500">Áp dụng cho mọi lớp bạn đang phụ trách, không giới hạn số lớp.</span>
                            </span>
                        </label>
                    @else
                        <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 opacity-60">
                            <span class="mt-0.5 grid h-4 w-4 place-items-center rounded-full border border-slate-300 bg-white"></span>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-bold text-slate-600">Dùng để dạy (mọi lớp phụ trách)</span>
                                <span class="mt-0.5 block text-[11.5px] leading-relaxed text-slate-500">Cần hoàn tất phê duyệt giáo viên trước.</span>
                            </span>
                        </div>
                    @endif
                </div>
            </x-ws.card>

            {{-- ══ Thanh toán ══ --}}
            <x-ws.card title="Phương thức thanh toán" icon="banknote">
                <div class="space-y-2">
                    <label class="flex cursor-pointer items-start justify-between gap-3 rounded-2xl border p-3 transition-colors"
                           :class="paymentMethod === 'token' ? 'border-blue-300 bg-blue-50' : 'border-sky-100 bg-white hover:bg-sky-50'">
                        <span class="flex min-w-0 items-start gap-3">
                            <input type="radio" name="payment_method" value="token" x-model="paymentMethod" class="mt-0.5 h-4 w-4 text-blue-600">
                            <span class="min-w-0">
                                <span class="block text-[13px] font-bold text-slate-800">Trả bằng token trong ví</span>
                                <span class="mt-0.5 block text-[11.5px] leading-relaxed text-slate-500">
                                    Trừ ví ngay và có quyền ngay lập tức — không phải chờ duyệt.
                                </span>
                            </span>
                        </span>
                        <span class="shrink-0 rounded-lg px-2 py-1 text-[11px] font-bold"
                              :class="enoughToken ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600'"
                              x-text="'Số dư: ' + money(balance)"></span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-3 transition-colors"
                           :class="paymentMethod === 'offline' ? 'border-blue-300 bg-blue-50' : 'border-sky-100 bg-white hover:bg-sky-50'">
                        <input type="radio" name="payment_method" value="offline" x-model="paymentMethod" class="mt-0.5 h-4 w-4 text-blue-600">
                        <span class="min-w-0">
                            <span class="block text-[13px] font-bold text-slate-800">Chuyển khoản — quản trị duyệt</span>
                            <span class="mt-0.5 block text-[11.5px] leading-relaxed text-slate-500">Chuyển khoản như hiện nay, duyệt xong bạn nhận mã kích hoạt.</span>
                        </span>
                    </label>

                    <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 opacity-60">
                        <span class="mt-0.5 grid h-4 w-4 place-items-center rounded-full border border-slate-300 bg-white"></span>
                        <span class="text-[13px] font-bold text-slate-600">VNPAY (sắp mở)</span>
                    </div>
                </div>

                <p class="mt-2.5 text-[11px] leading-relaxed text-slate-400" x-show="paymentMethod === 'token' && ! enoughToken" x-cloak>
                    Số dư chưa đủ — đặt đơn sẽ chuyển bạn sang trang Ví token để nạp thêm.
                </p>
            </x-ws.card>

            @unless ($isCourse)
                {{-- Bản in chỉ có nghĩa với sách/chuyên đề in được. Khoá học không có bản in. --}}
                <x-ws.card title="Bản in" icon="package">
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-2xl border border-sky-100 bg-white p-3 transition-colors hover:bg-sky-50">
                        <span class="flex items-center gap-2.5 text-[12.5px] text-slate-700">
                            <input type="checkbox" name="include_print" value="1" x-model="includePrint" class="h-4 w-4 rounded border-slate-300 text-blue-600">
                            Mua kèm bản in — giao hàng riêng, không đổi quyền số
                        </span>
                        <span class="shrink-0 text-[12px] font-bold text-slate-500">+{{ number_format($printPrice) }}đ</span>
                    </label>
                </x-ws.card>
            @endunless
        </div>

        {{-- ══ Cột phải: tóm tắt đơn ══ --}}
        <div class="space-y-4">
            <x-ws.card title="Đơn của bạn" icon="receipt-text">
                <div class="rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3">
                    <p class="text-[13px] font-bold leading-snug text-slate-800">{{ $product->title }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        {{ $isCourse ? 'Khoá học — mua xong chọn lớp để vào học' : 'Bản mềm — luôn có trong đơn' }}
                    </p>
                </div>

                <dl class="mt-3 space-y-2 text-[12px]">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500" x-text="scope === 'teacher_teaching' ? 'Giá dùng để dạy' : 'Giá học cá nhân'"></dt>
                        <dd class="font-bold text-slate-700" x-text="money(unitPrice)"></dd>
                    </div>
                    <div class="flex items-center justify-between" x-show="includePrint" x-cloak>
                        <dt class="text-slate-500">Bản in</dt>
                        <dd class="font-bold text-slate-700" x-text="money(printPrice)"></dd>
                    </div>
                </dl>

                <div class="mt-3 flex items-center justify-between border-t border-sky-100 pt-3">
                    <span class="text-[12px] font-bold text-slate-500">Tổng tiền</span>
                    <span class="text-xl font-black text-slate-800" x-text="money(total)"></span>
                </div>

                <div class="mt-3">
                    <x-ws.btn type="submit" variant="primary" icon="banknote" class="w-full justify-center">Đặt đơn</x-ws.btn>
                </div>

                <p class="mt-2 text-[11px] leading-relaxed text-slate-400" x-show="paymentMethod === 'offline'">
                    Quản trị duyệt xong bạn sẽ nhận mã kích hoạt qua thông báo.
                </p>
                <p class="mt-2 text-[11px] leading-relaxed text-slate-400" x-show="paymentMethod === 'token'" x-cloak>
                    @if ($isCourse)
                        Trả bằng token là có quyền ngay và chuyển thẳng sang màn chọn lớp.
                    @else
                        Trả bằng token là có quyền đọc ngay và chuyển thẳng sang trang tài liệu.
                    @endif
                </p>
            </x-ws.card>

            <x-ws.card title="Cần biết trước khi đặt" icon="info">
                <ul class="space-y-1.5 text-[11.5px] leading-relaxed text-slate-500">
                    <li class="flex gap-1.5"><span class="text-slate-300">•</span><span>Tạo đơn chưa phải là đã thanh toán và chưa phải là đã có quyền.</span></li>
                    <li class="flex gap-1.5"><span class="text-slate-300">•</span><span>Thời hạn quyền bắt đầu tính từ lúc kích hoạt mã, không phải lúc đặt đơn.</span></li>
                    <li class="flex gap-1.5"><span class="text-slate-300">•</span><span>Trả bằng token thì có quyền ngay, không cần kích hoạt mã.</span></li>
                </ul>
            </x-ws.card>
        </div>
    </form>
@endsection
