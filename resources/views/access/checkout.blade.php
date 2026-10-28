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
    // SỬA 28/10 — giáo viên đã có quyền Học cá nhân: chỉ còn mua thêm quyền Dùng để dạy (ô Học cá nhân khoá lại).
    $ownedScopes = $ownedScopes ?? [];
    $ownsPersonal = in_array('personal_learning', $ownedScopes, true);

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
              {{-- SỬA 25/10 — chọn "Bản mềm + sách in" ở hộp mua tài liệu (?ban-in=1) thì tích sẵn ô Mua kèm bản in. --}}
              includePrint: {{ (! $isCourse && $product->has_print_option && old('include_print', request()->boolean('ban-in'))) ? 'true' : 'false' }},
              scope: '{{ $ownsPersonal && $canTeach ? 'teacher_teaching' : 'personal_learning' }}',
              paymentMethod: 'token',
              priceLearning: {{ (int) $product->price }},
              {{-- SỬA 14/10 — gộp còn MỘT giá: dạy hay học cùng một số. --}}
              priceTeaching: {{ (int) $product->price }},
              printPrice: {{ $isCourse ? 0 : (int) $printPrice }},
              balance: {{ $tokenBalance }},
              get unitPrice() { return this.scope === 'teacher_teaching' ? this.priceTeaching : this.priceLearning; },
              get total() { return this.unitPrice + (this.includePrint ? this.printPrice : 0); },
              {{-- SỬA 18/10 (khách: "giá hiển thị bằng token, 200.000đ = 200 token") — số tiền lưu bằng đồng, hiện chia tỉ giá. --}}
              rate: {{ \App\Support\TokenPrice::rate() }},
              money(n) { return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format((Number(n) || 0) / this.rate) + ' token'; },
              vnd(n) { return new Intl.NumberFormat('vi-VN').format(Number(n) || 0) + 'đ'; },
              get enoughToken() { return this.balance >= this.total; },
          }">
        @csrf

        <div class="space-y-4">
            {{-- ══ Phạm vi quyền ══ --}}
            <x-ws.card title="Phạm vi quyền nhận" icon="shield-check">
                <div class="space-y-2">
                    <label class="flex items-start gap-3 rounded-2xl border p-3 transition-colors {{ $ownsPersonal ? 'cursor-default opacity-60' : 'cursor-pointer' }}"
                           :class="scope === 'personal_learning' ? 'border-blue-300 bg-blue-50' : 'border-sky-100 bg-white {{ $ownsPersonal ? '' : 'hover:bg-sky-50' }}'">
                        <input type="radio" name="scope" value="personal_learning" x-model="scope" class="mt-0.5 h-4 w-4 text-blue-600" @disabled($ownsPersonal)>
                        <span class="min-w-0">
                            <span class="block text-[13px] font-bold text-slate-800">Học cá nhân @if ($ownsPersonal)<span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">Đã sở hữu</span>@endif</span>
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

                    {{-- SỬA 20/10 (khách: "mua không cần duyệt, duyệt nạp token là đủ") — bỏ "Chuyển khoản — quản trị duyệt":
                         chỉ trả bằng token, trừ ví và có quyền ngay. Hết token thì nạp ở Ví (admin duyệt yêu cầu nạp). --}}

                    <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 opacity-60">
                        <span class="mt-0.5 grid h-4 w-4 place-items-center rounded-full border border-slate-300 bg-white"></span>
                        <span class="text-[13px] font-bold text-slate-600">VNPAY (sắp mở)</span>
                    </div>
                </div>

                <p class="mt-2.5 text-[11px] leading-relaxed text-slate-400" x-show="paymentMethod === 'token' && ! enoughToken" x-cloak>
                    Số dư chưa đủ — đặt đơn sẽ chuyển bạn sang trang Ví token để nạp thêm.
                </p>
            </x-ws.card>

            @unless ($isCourse || ! $product->has_print_option)
                {{-- Bản in chỉ có nghĩa với sách/chuyên đề in được. Khoá học không có bản in.
                     SỬA 14/10 — tài liệu không bật "Có bản in" thì không hiện ô mua kèm bản in. --}}
                <x-ws.card title="Bản in" icon="package">
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-2xl border border-sky-100 bg-white p-3 transition-colors hover:bg-sky-50">
                        <span class="flex items-center gap-2.5 text-[12.5px] text-slate-700">
                            <input type="checkbox" name="include_print" value="1" x-model="includePrint" class="h-4 w-4 rounded border-slate-300 text-blue-600">
                            Mua kèm bản in — giao hàng riêng, không đổi quyền số
                        </span>
                        <span class="shrink-0 text-[12px] font-bold text-slate-500">+{{ \App\Support\TokenPrice::label($printPrice) }}</span>
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
                        <dt class="text-slate-500">Giá tài liệu</dt>
                        <dd class="font-bold text-slate-700" x-text="money(unitPrice)"></dd>
                    </div>
                    <div class="flex items-center justify-between" x-show="includePrint" x-cloak>
                        <dt class="text-slate-500">Bản in</dt>
                        <dd class="font-bold text-slate-700" x-text="money(printPrice)"></dd>
                    </div>
                </dl>

                <div class="mt-3 flex items-center justify-between border-t border-sky-100 pt-3">
                    <span class="text-[12px] font-bold text-slate-500">Tổng cộng</span>
                    <span class="text-xl font-black text-slate-800" x-text="money(total)"></span>
                </div>

                <div class="mt-3">
                    <x-ws.btn type="submit" variant="primary" icon="banknote" class="w-full justify-center">Đặt đơn</x-ws.btn>
                </div>

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
                    <li class="flex gap-1.5"><span class="text-slate-300">•</span><span>Đặt đơn là trừ token trong ví và có quyền ngay — không phải chờ duyệt, không cần kích hoạt mã.</span></li>
                    <li class="flex gap-1.5"><span class="text-slate-300">•</span><span>Ví chưa đủ token thì nạp thêm ở trang Ví token, được duyệt nạp xong là mua được.</span></li>
                </ul>
            </x-ws.card>
        </div>
    </form>

    {{-- SỬA 28/10 — mua xong rồi bấm Back của trình duyệt: trình duyệt hiện lại trang đặt đơn cũ từ bộ nhớ đệm (không hỏi máy
         chủ). Tải lại để máy chủ chuyển thẳng về trang tài liệu — không đặt đơn lần nữa được. --}}
    <script>
        window.addEventListener('pageshow', function (e) { if (e.persisted) { window.location.reload(); } });
    </script>
@endsection
