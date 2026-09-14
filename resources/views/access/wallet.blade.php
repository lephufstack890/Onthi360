{{--
  Route: wallet.index (GET) / wallet.request (POST) | Frame: mở rộng ACC-03/ACC-07
  Spec: note họp 13/8, mục 7-8 — "Nộp tiền thành token" + "Khi đăng ký thi thì thông tin
  ngân hàng – QR người dùng chỉ cần chuyển khoản là xong". Nạp tiền MỘT LẦN thành số dư
  token dùng chung cho nhiều lần thanh toán sau này, thay vì chuyển khoản riêng lẻ từng đơn.
  Dữ liệu thật do App\Http\Controllers\Access\WalletController truyền vào qua
  App\Services\WalletService. P0 chưa có cổng thanh toán tự động — Admin đối soát sao kê
  rồi duyệt tay (xem admin.orders.index, mục "Yêu cầu nạp token").

  SỬA 14/9 — CHỈ ĐỔI GIAO DIỆN, KHÔNG ĐỔI LOGIC:
    · Trước đây trang này @extends('layouts.guest') nên bấm "Ví token" trong khu học tập là
      văng hẳn ra vỏ trang công khai. Giờ dùng chung khung khu làm việc như 4 khu còn lại,
      vai trò lấy theo đúng người đang đăng nhập nên ai bấm cũng ở lại đúng khu của mình.
    · Dựng lại theo bộ thẻ x-ws.* giống các màn đã thiết kế; vẫn nguyên các biến $balance /
      $history / $bankInfo / $pendingTopup / $pendingQrUrl và hai route cũ.
--}}
@php
    $walletUser = auth()->user();
    $walletRole = match (true) {
        (bool) $walletUser?->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN) => 'admin',
        (bool) $walletUser?->hasRole(\App\Models\Role::TEACHER) => 'teacher',
        (bool) $walletUser?->hasRole(\App\Models\Role::PARENT) => 'parent',
        default => 'student',
    };
@endphp
@extends('layouts.workspace', ['wsRole' => $walletRole])

@section('title', 'Ví token')
@section('page-title', 'Ví token')

@section('content')
    @php
        $balance = $balance ?? 0;
        $history = $history ?? [];
        $bankInfo = $bankInfo ?? [];
        $pendingTopup = $pendingTopup ?? null;
        $pendingQrUrl = $pendingQrUrl ?? null;
        $bankReady = ($bankInfo['bankName'] ?? '') !== '' && ($bankInfo['accountNo'] ?? '') !== '';
    @endphp

    @if (session('status') === 'topup-requested')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo yêu cầu nạp token — chuyển khoản đúng nội dung bên dưới rồi chờ Admin duyệt.'])
    @endif
    {{-- SỬA 25/8 (2): "nếu không đủ token thì chuyển sang trang bắt học sinh phải nạp" —
         AccessController::store() chuyển hẳn về đây khi placeOrder() báo thiếu token. --}}
    @if (session('status') === 'need-topup')
        @include('partials.toast-flash', ['type' => 'warning', 'message' => 'Số dư token không đủ để đặt mua học liệu — vui lòng nạp thêm token bên dưới rồi quay lại đặt đơn.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-ws.page-header title="Ví token" icon="wallet-cards"
                      :back="route('access.myAccess')" back-label="Quyền truy cập của tôi"
                      subtitle="Nạp một lần thành số dư token, dùng chung cho đăng ký thi và mua học liệu — không phải chuyển khoản riêng cho từng lần.">
        <x-slot:actions>
            <x-ws.btn :href="route('access.myAccess')" variant="onhero-ghost" icon="shield-check">Quyền của tôi</x-ws.btn>
        </x-slot:actions>
    </x-ws.page-header>

    {{-- ══════ Số liệu nhanh ══════ --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ws.stat label="Số dư hiện có" :value="number_format($balance).' token'" tone="emerald" icon="wallet-cards"
                   hint="1 token = 1đ" />
        <x-ws.stat label="Đang chờ duyệt" :value="$pendingTopup ? number_format($pendingTopup->amount).'đ' : '—'"
                   :tone="$pendingTopup ? 'amber' : 'neutral'" icon="clock-3"
                   :hint="$pendingTopup ? 'Chuyển khoản xong chờ Admin đối soát' : 'Không có yêu cầu nào đang chờ'" />
        <x-ws.stat label="Số lần đã nạp" :value="count($history)" tone="blue" icon="history"
                   hint="Tính cả yêu cầu bị từ chối" />
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1.45fr_1fr]">

        {{-- ══════ Cột trái: đang chờ chuyển khoản HOẶC tạo yêu cầu mới ══════ --}}
        <div class="space-y-4">
            @if ($pendingTopup)
                <x-ws.card title="Chuyển khoản để hoàn tất" icon="qr-code" class="border-amber-200">
                    <div class="flex items-start gap-3 rounded-2xl border border-amber-100 bg-amber-50 p-3">
                        <x-ws.icon-tile icon="clock-3" tone="amber" />
                        <p class="flex-1 text-[13px] leading-relaxed text-amber-800">
                            Yêu cầu nạp <strong>{{ number_format($pendingTopup->amount) }}đ</strong> đang chờ duyệt.
                            Chuyển khoản đúng nội dung bên dưới để Admin đối soát chính xác —
                            <strong>sai nội dung có thể khiến yêu cầu bị từ chối</strong>.
                        </p>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-[auto_minmax(0,1fr)]">
                        @if ($pendingQrUrl)
                            <div class="mx-auto sm:mx-0">
                                <img src="{{ $pendingQrUrl }}" alt="Mã QR chuyển khoản {{ number_format($pendingTopup->amount) }}đ"
                                     class="h-44 w-44 rounded-2xl border border-sky-100 bg-white p-1.5">
                                <p class="mt-1.5 text-center text-[10px] text-slate-400">Quét bằng app ngân hàng</p>
                            </div>
                        @else
                            <div class="flex h-44 w-44 flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-amber-200 bg-amber-50/60 p-3 text-center">
                                <x-lucide name="qr-code" class="h-6 w-6 text-amber-500" />
                                <p class="text-[11px] leading-relaxed text-amber-700">Chưa cấu hình đủ thông tin ngân hàng để tạo QR — chuyển khoản thủ công theo thông tin bên cạnh.</p>
                            </div>
                        @endif

                        <div class="min-w-0 divide-y divide-slate-100 rounded-2xl border border-sky-100 bg-[#F8FBFE]">
                            <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                <span class="text-[11px] font-bold text-slate-500">Ngân hàng</span>
                                <span class="min-w-0 truncate text-[13px] font-bold text-slate-700">{{ $bankInfo['bankName'] ?: '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                <span class="text-[11px] font-bold text-slate-500">Số tài khoản</span>
                                <span class="min-w-0 truncate text-[13px] font-bold tracking-wide text-slate-700">{{ $bankInfo['accountNo'] ?: '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                <span class="text-[11px] font-bold text-slate-500">Chủ tài khoản</span>
                                <span class="min-w-0 truncate text-[13px] font-bold text-slate-700">{{ $bankInfo['accountName'] ?: '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                <span class="text-[11px] font-bold text-slate-500">Số tiền</span>
                                <span class="text-[13px] font-black text-slate-800">{{ number_format($pendingTopup->amount) }}đ</span>
                            </div>
                            {{-- Nội dung chuyển khoản — nổi bật nhất vì sai một ký tự là Admin không đối soát được. --}}
                            <div x-data="{ copied: false }" class="flex items-center justify-between gap-3 bg-rose-50/70 px-3.5 py-2.5">
                                <span class="text-[11px] font-bold text-rose-600">Nội dung CK<br><span class="font-medium">(bắt buộc)</span></span>
                                <span class="flex min-w-0 items-center gap-1.5">
                                    <span class="min-w-0 truncate text-[13px] font-black tracking-wide text-rose-700">{{ $pendingTopup->transfer_code }}</span>
                                    <button type="button" aria-label="Sao chép nội dung chuyển khoản"
                                            @click="navigator.clipboard.writeText(@js($pendingTopup->transfer_code)).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
                                            class="grid h-7 w-7 shrink-0 place-items-center rounded-lg border border-rose-200 bg-white text-rose-600 transition-colors hover:bg-rose-50">
                                        <x-lucide name="copy" class="h-3.5 w-3.5" x-show="!copied" />
                                        <x-lucide name="check-circle-2" class="h-3.5 w-3.5 text-emerald-600" x-show="copied" x-cloak />
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </x-ws.card>
            @else
                <x-ws.card title="Nạp token" icon="banknote">
                    <form method="POST" action="{{ route('wallet.request') }}" class="space-y-3">
                        @csrf
                        <x-ws.field label="Số tiền muốn nạp (VNĐ)" name="amount" required
                                    hint="Tối thiểu 10.000đ. Sau khi tạo yêu cầu, bạn sẽ thấy mã QR và nội dung chuyển khoản riêng để Admin đối soát.">
                            <input id="amount" name="amount" type="number" min="10000" step="1000" required
                                   value="{{ old('amount', 100000) }}" placeholder="100000"
                                   class="admin-input {{ $errors->has('amount') ? 'is-invalid' : '' }}">
                        </x-ws.field>

                        {{-- Mấy mức hay dùng — bấm là điền sẵn vào ô trên, vẫn gửi đúng một trường amount. --}}
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] font-bold text-slate-400">Chọn nhanh:</span>
                            @foreach ([50000, 100000, 200000, 500000] as $quick)
                                <button type="button" onclick="document.getElementById('amount').value = {{ $quick }}"
                                        class="rounded-xl border border-sky-100 bg-[#F8FBFE] px-2.5 py-1 text-[11px] font-bold text-slate-600 transition-colors hover:border-sky-200 hover:bg-sky-50 hover:text-blue-700">
                                    {{ number_format($quick) }}đ
                                </button>
                            @endforeach
                        </div>

                        <x-ws.btn type="submit" variant="primary" icon="plus">Tạo yêu cầu nạp</x-ws.btn>
                    </form>
                </x-ws.card>
            @endif
        </div>

        {{-- ══════ Cột phải: giải thích cách nạp ══════ --}}
        <x-ws.card title="Nạp token hoạt động thế nào" icon="info">
            <ol class="space-y-3">
                @foreach ([
                    ['Tạo yêu cầu nạp', 'Nhập số tiền muốn nạp. Hệ thống sinh cho bạn một mã chuyển khoản riêng.'],
                    ['Chuyển khoản đúng nội dung', 'Quét QR hoặc chuyển tay, ghi đúng mã ở ô "Nội dung CK".'],
                    ['Admin đối soát và duyệt', 'Đối chiếu sao kê xong, số dư token vào ví của bạn.'],
                ] as $i => $step)
                    <li class="flex items-start gap-2.5">
                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg bg-blue-50 text-[11px] font-black text-blue-700">{{ $i + 1 }}</span>
                        <span class="min-w-0">
                            <span class="block text-[13px] font-bold text-slate-700">{{ $step[0] }}</span>
                            <span class="mt-0.5 block text-[11px] leading-relaxed text-slate-500">{{ $step[1] }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>

            <div class="mt-4 rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3">
                <p class="text-[11px] leading-relaxed text-slate-500">
                    <strong class="text-slate-700">1 token = 1đ.</strong>
                    Token dùng để đăng ký thi, mua học liệu... không cần chuyển khoản riêng cho từng lần.
                </p>
            </div>

            @unless ($bankReady)
                <div class="mt-3 flex items-start gap-2 rounded-2xl border border-amber-100 bg-amber-50 p-3">
                    <x-lucide name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-600" />
                    <p class="flex-1 text-[11px] leading-relaxed text-amber-800">
                        Hệ thống chưa cấu hình thông tin ngân hàng nhận tiền. Quản trị viên cần điền ở
                        <strong>Cấu hình hệ thống</strong> thì mới tạo được mã QR.
                    </p>
                </div>
            @endunless
        </x-ws.card>
    </div>

    {{-- ══════ Lịch sử nạp ══════ --}}
    <x-ws.card title="Lịch sử nạp token" icon="history" padding="p-0">
        @if (count($history) === 0)
            <div class="p-4 sm:p-5">
                <x-ws.empty-state icon="wallet-cards" title="Chưa có lần nạp token nào"
                                  description="Các yêu cầu nạp của bạn sẽ hiện ở đây kèm trạng thái duyệt." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-xs">
                    <thead class="bg-[#F8FBFE] text-[10px] font-bold uppercase tracking-[0.06em] text-slate-500">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 font-bold">Số tiền</th>
                            <th class="whitespace-nowrap px-4 py-3 font-bold">Nội dung CK</th>
                            <th class="whitespace-nowrap px-4 py-3 font-bold">Thời gian</th>
                            <th class="whitespace-nowrap px-4 py-3 font-bold">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 [&>tr:hover]:bg-sky-50/60 [&>tr]:transition-colors">
                        @foreach ($history as $h)
                            <tr>
                                <td class="px-4 py-3 text-[13px] font-bold text-slate-700">{{ number_format($h['amount']) }}đ</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold tracking-wide text-slate-600">{{ $h['transferCode'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-[11px] text-slate-400">{{ $h['createdAt']?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    <x-ws.badge :tone="$h['tone']">{{ $h['status'] }}</x-ws.badge>
                                    @if ($h['rejectReason'])
                                        <p class="mt-1 max-w-xs text-[11px] leading-relaxed text-rose-600">Lý do từ chối: {{ $h['rejectReason'] }}</p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ws.card>
@endsection
