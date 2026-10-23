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
        {{-- SỬA 18/10 (khách: "nạp 100.000đ được 100 token") — số dư / số nạp hiện bằng token (App\Support\TokenPrice). --}}
        <x-ws.stat label="Số dư hiện có" :value="\App\Support\TokenPrice::label($balance)" tone="emerald" icon="wallet-cards"
                   :hint="'1 token = '.\App\Support\TokenPrice::vnd(\App\Support\TokenPrice::rate())" />
        <x-ws.stat label="Đang chờ duyệt" :value="$pendingTopup ? \App\Support\TokenPrice::label($pendingTopup->amount) : '—'"
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
                            Yêu cầu nạp <strong>{{ \App\Support\TokenPrice::label($pendingTopup->amount) }}</strong> ({{ \App\Support\TokenPrice::vnd($pendingTopup->amount) }}) đang chờ duyệt.
                            Chuyển khoản đúng nội dung bên dưới để Admin đối soát chính xác —
                            <strong>sai nội dung có thể khiến yêu cầu bị từ chối</strong>.
                        </p>
                    </div>

                    {{-- SỬA 23/10 (khách: "chỗ chuyển khoản nhiều màn hình bị vỡ, xấu — thiết kế lại cho đẹp, hợp mọi màn hình") —
                         bố cục theo ĐỘ RỘNG CỦA KHUNG (container query) chứ không theo màn hình: khung rộng ≥ 620px thì QR bên trái +
                         thông tin bên phải; hẹp hơn (điện thoại, máy tính bảng, cột hẹp) thì xếp dọc, QR ở trên. Thông tin quan trọng
                         nhất (số tiền, nội dung CK) nằm trong 2 ô lớn có nút Sao chép; chữ dài tự xuống dòng thay vì bị cắt.
                         CSS riêng tiền tố wpay- / wqr- (Tailwind không build lại trên máy chủ). --}}
                    <style>
                        .wpay { container-type: inline-size; margin-top: 16px; }
                        .wpay-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); align-items: start; }
                        @container (min-width: 620px) { .wpay-grid { grid-template-columns: minmax(230px, 300px) minmax(0, 1fr); gap: 20px; } }
                        .wpay-qr { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 12px; border: 1px solid #DDEAF0; border-radius: 20px; background: linear-gradient(180deg, #F8FBFE, #fff); }
                        .wqr-box { display: flex; justify-content: center; width: 100%; padding: 8px; border: 1px solid #E3EDF4; border-radius: 16px; background: #fff; cursor: zoom-in; transition: box-shadow .15s, transform .15s; }
                        .wqr-box:hover { box-shadow: 0 8px 22px rgba(0, 90, 180, .12); transform: translateY(-1px); }
                        .wqr-box img { display: block; width: 100%; height: auto; max-width: 300px; max-height: 360px; object-fit: contain; border-radius: 10px; }
                        @container (max-width: 619px) { .wpay-qr { max-width: 360px; width: 100%; margin: 0 auto; } .wqr-box img { max-height: 58vh; } }
                        .wqr-cap { margin: 0; text-align: center; font-size: 12px; line-height: 1.5; color: #64748B; }
                        .wqr-acts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; width: 100%; }
                        .wqr-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 36px; padding: 0 10px; border-radius: 11px; border: 1px solid #CFE3EE; background: #fff; font-size: 12px; font-weight: 800; color: #0F5E86; white-space: nowrap; }
                        .wqr-btn:hover { background: #EAF5FB; }
                        .wqr-btn svg { width: 14px; height: 14px; flex: none; }
                        .wqr-modal { position: fixed; inset: 0; z-index: 90; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, .8); }
                        .wqr-modal-in { position: relative; }
                        .wqr-modal img { display: block; max-width: 92vw; max-height: 86vh; border-radius: 16px; background: #fff; }
                        .wqr-close { position: absolute; top: -12px; right: -12px; display: grid; place-items: center; width: 36px; height: 36px; border-radius: 999px; background: #fff; color: #0F172A; box-shadow: 0 4px 14px rgba(0,0,0,.25); }
                        .wqr-close svg { width: 18px; height: 18px; }
                        .wpay-noqr { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; min-height: 180px; padding: 16px; border: 1px dashed #F2D8A0; border-radius: 20px; background: #FFFBF0; text-align: center; font-size: 12px; line-height: 1.55; color: #9A6B12; }
                        .wpay-noqr svg { width: 26px; height: 26px; color: #E0A526; }
                        .wpay-info { display: flex; flex-direction: column; gap: 12px; min-width: 0; }
                        .wpay-key { padding: 14px 16px; border-radius: 18px; border: 1px solid #DDEAF0; background: #F8FBFE; }
                        .wpay-key.is-code { border-color: #FBCFD6; background: linear-gradient(120deg, #FFF1F3, #FFF8F9); }
                        .wpay-key-l { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 11.5px; font-weight: 800; color: #64748B; }
                        .wpay-key.is-code .wpay-key-l { color: #BE123C; }
                        .wpay-req { padding: 1px 7px; border-radius: 999px; background: #FFE4E8; font-size: 10px; font-weight: 800; color: #BE123C; }
                        .wpay-key-v { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 12px; margin-top: 6px; }
                        .wpay-big { font-size: 24px; font-weight: 900; line-height: 1.15; color: #0F2744; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
                        .wpay-key.is-code .wpay-big { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 22px; letter-spacing: .06em; color: #BE123C; }
                        .wpay-tok { display: inline-flex; align-items: center; gap: 4px; margin-left: 6px; padding: 2px 9px; border-radius: 999px; background: #E3F6EC; font-size: 12px; font-weight: 800; color: #047857; vertical-align: middle; }
                        .wpay-copy { display: inline-flex; align-items: center; gap: 6px; min-height: 34px; padding: 0 12px; border-radius: 10px; border: 1px solid #CFE3EE; background: #fff; font-size: 12px; font-weight: 800; color: #0F5E86; cursor: pointer; white-space: nowrap; }
                        .wpay-key.is-code .wpay-copy { border-color: #FBCFD6; color: #BE123C; }
                        .wpay-copy:hover { background: #F2F9FF; }
                        .wpay-key.is-code .wpay-copy:hover { background: #FFF1F3; }
                        .wpay-copy svg { width: 14px; height: 14px; }
                        .wpay-copy.is-done { border-color: #A7E3C4; background: #ECFBF3; color: #047857; }
                        .wpay-rows { border: 1px solid #DDEAF0; border-radius: 18px; background: #fff; }
                        .wpay-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 4px 12px; padding: 11px 16px; }
                        .wpay-row + .wpay-row { border-top: 1px solid #EEF4F8; }
                        .wpay-row > span:first-child { font-size: 12px; font-weight: 700; color: #64748B; }
                        .wpay-row-v { display: inline-flex; align-items: center; gap: 6px; min-width: 0; font-size: 14px; font-weight: 800; color: #0F2744; text-align: right; overflow-wrap: anywhere; }
                        .wpay-mini { display: inline-grid; place-items: center; width: 28px; height: 28px; flex: none; border-radius: 8px; border: 1px solid #DDEAF0; background: #F8FBFE; color: #5C7A94; cursor: pointer; }
                        .wpay-mini:hover { color: #0F5FA8; border-color: #B8D8F0; }
                        .wpay-mini.is-done { color: #047857; border-color: #A7E3C4; background: #ECFBF3; }
                        .wpay-mini svg { width: 13px; height: 13px; }
                        .wpay-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin: 0; padding: 0; list-style: none; }
                        .wpay-steps li { display: flex; align-items: flex-start; gap: 7px; padding: 9px 10px; border-radius: 14px; background: #F6FAFD; font-size: 11.5px; line-height: 1.45; color: #4C6A84; }
                        .wpay-steps b { display: grid; place-items: center; width: 20px; height: 20px; flex: none; border-radius: 7px; background: #DBEAFE; font-size: 11px; color: #1D4ED8; }
                        @container (max-width: 420px) { .wpay-steps { grid-template-columns: 1fr; } .wpay-big { font-size: 21px; } .wpay-key.is-code .wpay-big { font-size: 19px; } }
                        html.theme-dark .wpay-qr, html.theme-dark .wpay-rows, html.theme-dark .wqr-box, html.theme-dark .wpay-key { background: #12283A; border-color: #25445C; }
                        html.theme-dark .wpay-key.is-code { background: #2A1620; border-color: #5B2335; }
                        html.theme-dark .wpay-big, html.theme-dark .wpay-row-v { color: #E2ECF5; }
                        html.theme-dark .wpay-row + .wpay-row { border-color: #1E3A50; }
                        html.theme-dark .wpay-steps li { background: #15304A; color: #A9C0D2; }
                        html.theme-dark .wpay-copy, html.theme-dark .wpay-mini, html.theme-dark .wqr-btn { background: #173246; border-color: #2B4E68; color: #9BD4F5; }
                    </style>

                    @php
                        $wpayCopy = fn ($text) => "navigator.clipboard.writeText(".\Illuminate\Support\Js::from((string) $text).").then(() => { done = true; setTimeout(() => done = false, 1600) })";
                    @endphp

                    <div class="wpay">
                        <div class="wpay-grid">
                            @if ($pendingQrUrl)
                                <div class="wpay-qr" x-data="{ zoom: false }" @keydown.escape.window="zoom = false">
                                    <button type="button" class="wqr-box" @click="zoom = true" aria-label="Phóng to mã QR">
                                        <img src="{{ $pendingQrUrl }}" alt="Mã QR ngân hàng nhận tiền">
                                    </button>
                                    <p class="wqr-cap">Quét bằng app ngân hàng, rồi nhập <strong class="text-rose-600">đúng số tiền và nội dung CK</strong>.</p>
                                    <div class="wqr-acts">
                                        <button type="button" class="wqr-btn" @click="zoom = true"><x-lucide name="maximize-2" />Phóng to</button>
                                        <a href="{{ $pendingQrUrl }}" download="ma-qr-ngan-hang" class="wqr-btn"><x-lucide name="download" />Tải ảnh</a>
                                    </div>
                                    <div class="wqr-modal" x-show="zoom" x-cloak x-transition.opacity @click.self="zoom = false" role="dialog" aria-modal="true" aria-label="Mã QR ngân hàng">
                                        <div class="wqr-modal-in">
                                            <img src="{{ $pendingQrUrl }}" alt="Mã QR ngân hàng nhận tiền (phóng to)">
                                            <button type="button" class="wqr-close" @click="zoom = false" aria-label="Đóng"><x-lucide name="x" /></button>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="wpay-noqr">
                                    <x-lucide name="qr-code" />
                                    <span>Chưa có ảnh QR ngân hàng — chuyển khoản thủ công theo thông tin bên cạnh.</span>
                                </div>
                            @endif

                            <div class="wpay-info">
                                {{-- Số tiền cần chuyển --}}
                                <div class="wpay-key" x-data="{ done: false }">
                                    <div class="wpay-key-l"><span>Số tiền cần chuyển</span></div>
                                    <div class="wpay-key-v">
                                        <span class="wpay-big">{{ \App\Support\TokenPrice::vnd($pendingTopup->amount) }}<span class="wpay-tok"><x-lucide name="arrow-right" class="h-3 w-3" />{{ \App\Support\TokenPrice::label($pendingTopup->amount) }}</span></span>
                                        <button type="button" class="wpay-copy" :class="done && 'is-done'" @click="{{ $wpayCopy($pendingTopup->amount) }}" aria-label="Sao chép số tiền">
                                            <x-lucide name="copy" x-show="!done" /><x-lucide name="check-circle-2" x-show="done" x-cloak /><span x-text="done ? 'Đã chép' : 'Sao chép'">Sao chép</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Nội dung chuyển khoản — nổi bật nhất vì sai một ký tự là Admin không đối soát được. --}}
                                <div class="wpay-key is-code" x-data="{ done: false }">
                                    <div class="wpay-key-l"><span>Nội dung chuyển khoản</span><span class="wpay-req">Bắt buộc</span></div>
                                    <div class="wpay-key-v">
                                        <span class="wpay-big">{{ $pendingTopup->transfer_code }}</span>
                                        <button type="button" class="wpay-copy" :class="done && 'is-done'" @click="{{ $wpayCopy($pendingTopup->transfer_code) }}" aria-label="Sao chép nội dung chuyển khoản">
                                            <x-lucide name="copy" x-show="!done" /><x-lucide name="check-circle-2" x-show="done" x-cloak /><span x-text="done ? 'Đã chép' : 'Sao chép'">Sao chép</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Tài khoản nhận --}}
                                <div class="wpay-rows">
                                    <div class="wpay-row"><span>Ngân hàng</span><span class="wpay-row-v">{{ $bankInfo['bankName'] ?: '—' }}</span></div>
                                    <div class="wpay-row" x-data="{ done: false }">
                                        <span>Số tài khoản</span>
                                        <span class="wpay-row-v" style="letter-spacing:.03em">{{ $bankInfo['accountNo'] ?: '—' }}
                                            @if ($bankInfo['accountNo'])
                                                <button type="button" class="wpay-mini" :class="done && 'is-done'" @click="{{ $wpayCopy($bankInfo['accountNo']) }}" aria-label="Sao chép số tài khoản"><x-lucide name="copy" x-show="!done" /><x-lucide name="check-circle-2" x-show="done" x-cloak /></button>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="wpay-row"><span>Chủ tài khoản</span><span class="wpay-row-v">{{ $bankInfo['accountName'] ?: '—' }}</span></div>
                                </div>

                                <ol class="wpay-steps">
                                    <li><b>1</b><span>Quét QR hoặc chuyển tay tới tài khoản trên.</span></li>
                                    <li><b>2</b><span>Nhập đúng <strong>số tiền</strong> và <strong>nội dung CK</strong>.</span></li>
                                    <li><b>3</b><span>Admin đối soát xong, token tự vào ví.</span></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </x-ws.card>
            @else
                <x-ws.card title="Nạp token" icon="banknote">
                    <form method="POST" action="{{ route('wallet.request') }}" class="space-y-3">
                        @csrf
                        <x-ws.field label="Số tiền muốn nạp (VNĐ)" name="amount" required
                                    :hint="'Cứ '.\App\Support\TokenPrice::vnd(\App\Support\TokenPrice::rate()).' được 1 token (vd 100.000đ = 100 token). Tối thiểu 10.000đ. Sau khi tạo yêu cầu, bạn sẽ thấy mã QR và nội dung chuyển khoản riêng để Admin đối soát.'">
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
                                    {{ \App\Support\TokenPrice::vnd($quick) }} = {{ \App\Support\TokenPrice::label($quick) }}
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
                    {{-- SỬA 21/10 — đúng tỉ giá đang dùng (App\Support\TokenPrice), trước ghi cứng "1 token = 1đ". --}}
                    <strong class="text-slate-700">1 token = {{ \App\Support\TokenPrice::vnd(\App\Support\TokenPrice::rate()) }}.</strong>
                    Token dùng để đăng ký thi, mua học liệu... không cần chuyển khoản riêng cho từng lần.
                </p>
            </div>

            @unless ($bankReady)
                <div class="mt-3 flex items-start gap-2 rounded-2xl border border-amber-100 bg-amber-50 p-3">
                    <x-lucide name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-600" />
                    <p class="flex-1 text-[11px] leading-relaxed text-amber-800">
                        Hệ thống chưa cấu hình thông tin ngân hàng nhận tiền. Quản trị viên cần điền ở
                        <strong>Cấu hình hệ thống</strong> (kèm ảnh QR ngân hàng) thì học sinh mới nạp được.
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
                                <td class="px-4 py-3 text-[13px] font-bold text-slate-700">{{ \App\Support\TokenPrice::label($h['amount']) }} <span class="block text-[11px] font-medium text-slate-400">{{ \App\Support\TokenPrice::vnd($h['amount']) }}</span></td>
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
