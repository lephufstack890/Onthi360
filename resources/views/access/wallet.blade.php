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
        $pendingConfirmedAt = $pendingConfirmedAt ?? null;
        $bankReady = ($bankInfo['bankName'] ?? '') !== '' && ($bankInfo['accountNo'] ?? '') !== '';
    @endphp

    @if (session('status') === 'topup-requested')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo yêu cầu nạp token — chuyển khoản đúng nội dung bên dưới, xong bấm "Tôi đã chuyển khoản".'])
    @endif
    @if (session('status') === 'topup-confirmed')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã báo Admin — yêu cầu chuyển sang Chờ duyệt. Token sẽ tự cộng vào ví ngay khi Admin đối soát xong.'])
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
        <x-ws.stat :label="$pendingTopup && ! $pendingConfirmedAt ? 'Chờ chuyển khoản' : 'Đang chờ duyệt'" :value="$pendingTopup ? \App\Support\TokenPrice::label($pendingTopup->amount) : '—'"
                   :tone="$pendingTopup ? 'amber' : 'neutral'" icon="clock-3"
                   :hint="$pendingTopup ? ($pendingConfirmedAt ? 'Đã báo chuyển khoản — Admin đang đối soát' : 'Chuyển khoản xong bấm “Tôi đã chuyển khoản”') : 'Không có yêu cầu nào đang chờ'" />
        <x-ws.stat label="Số lần đã nạp" :value="count($history)" tone="blue" icon="history"
                   hint="Tính cả yêu cầu bị từ chối" />
    </div>

    {{--
        SỬA 2/11 (khách: "trang này ảnh còn xấu quá, dài khó xem, chưa gọn nhẹ"; "mỗi lần nạp tối đa 500k") — gom còn MỘT thẻ:
          · đang chờ chuyển khoản: ảnh QR nhỏ gọn (bấm để phóng to) + bảng thông tin chuyển khoản có nút Sao chép;
          · chưa có yêu cầu: chọn nhanh mức nạp (tối đa 500.000đ / lần) hoặc gõ số tiền → Tạo yêu cầu nạp.
        Bỏ cột "Nạp token hoạt động thế nào" (thành 1 dải 3 bước ngắn). CSS riêng tiền tố wp- (Tailwind không build lại trên máy chủ).
    --}}
    <style>
        .wp { container-type: inline-size; }
        .wp-note { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 14px; background: #FFF8E8; border: 1px solid #F6E2B3; font-size: 13px; line-height: 1.5; color: #8A5A00; }
        .wp-note svg { width: 18px; height: 18px; flex: none; color: #D99A1E; }
        .wp-grid { display: grid; gap: 16px; margin-top: 14px; grid-template-columns: minmax(0, 1fr); align-items: start; }
        @container (min-width: 600px) { .wp-grid { grid-template-columns: 260px minmax(0, 1fr); gap: 22px; } }
        .wp-qr { display: flex; flex-direction: column; align-items: center; gap: 8px; }
        /* SỬA 3/11 (khách: "QR to ra, bỏ khung xung quanh") — ảnh là thẻ QR đã cắt sẵn (WalletService::qrDisplayPath), hiện to, không viền. */
        .wp-qr-box { position: relative; display: block; width: 260px; max-width: 100%; padding: 0; border: 0; background: transparent; cursor: zoom-in; }
        .wp-qr-box img { display: block; width: 100%; height: auto; max-height: 460px; object-fit: contain; border-radius: 18px; box-shadow: 0 6px 22px rgba(15, 39, 68, .12); }
        .wp-qr-zoom { position: absolute; right: 10px; top: 10px; display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 999px; background: rgba(15, 39, 68, .78); color: #fff; font-size: 11px; font-weight: 700; }
        .wp-qr-zoom svg { width: 12px; height: 12px; }
        .wp-qr-dl { font-size: 12px; font-weight: 700; color: #0F5E86; }
        .wp-qr-dl:hover { text-decoration: underline; }
        .wp-noqr { display: grid; place-items: center; gap: 6px; width: 200px; height: 160px; padding: 12px; border: 1px dashed #F2D8A0; border-radius: 16px; background: #FFFBF0; text-align: center; font-size: 12px; line-height: 1.5; color: #9A6B12; }
        .wp-rows { border: 1px solid #DDEAF0; border-radius: 16px; background: #fff; overflow: hidden; }
        .wp-row { display: flex; align-items: center; gap: 10px; min-height: 52px; padding: 8px 14px; }
        .wp-row + .wp-row { border-top: 1px solid #EEF4F8; }
        .wp-row-l { width: 112px; flex: none; font-size: 12px; font-weight: 700; color: #64748B; }
        .wp-row-v { flex: 1; min-width: 0; font-size: 15px; font-weight: 800; color: #0F2744; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
        .wp-row-v small { margin-left: 6px; padding: 1px 8px; border-radius: 999px; background: #E3F6EC; font-size: 11.5px; font-weight: 800; color: #047857; white-space: nowrap; }
        .wp-row.is-code { background: #FFF4F6; }
        .wp-row.is-code .wp-row-l { color: #BE123C; }
        .wp-row.is-code .wp-row-v { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 18px; letter-spacing: .06em; color: #BE123C; }
        .wp-copy { display: inline-flex; align-items: center; gap: 5px; height: 30px; padding: 0 10px; flex: none; border-radius: 9px; border: 1px solid #CFE3EE; background: #fff; font-size: 12px; font-weight: 800; color: #0F5E86; cursor: pointer; white-space: nowrap; }
        .wp-copy:hover { background: #F2F9FF; }
        .wp-copy svg { width: 13px; height: 13px; }
        .wp-copy.is-done { border-color: #A7E3C4; background: #ECFBF3; color: #047857; }
        .wp-row.is-code .wp-copy { border-color: #FBCFD6; color: #BE123C; }
        .wp-foot { margin: 10px 2px 0; font-size: 12px; line-height: 1.5; color: #64748B; }
        @container (max-width: 599px) { .wp-qr { margin: 0 auto; } .wp-qr-box { width: 290px; } }
        @container (max-width: 420px) { .wp-row { flex-wrap: wrap; } .wp-row-l { width: 100%; } }
        .wp-modal[x-cloak] { display: none; }
        .wp-modal { position: fixed; inset: 0; z-index: 90; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, .8); }
        .wp-modal img { display: block; max-width: 92vw; max-height: 86vh; border-radius: 16px; background: #fff; }
        .wp-modal button { position: absolute; top: 16px; right: 16px; display: grid; place-items: center; width: 38px; height: 38px; border-radius: 999px; background: #fff; color: #0F172A; }
        .wp-modal button svg { width: 18px; height: 18px; }
        /* Form nạp */
        .wp-amounts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        @container (min-width: 520px) { .wp-amounts { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .wp-amt { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 10px 8px; border: 1.5px solid #DDEAF0; border-radius: 14px; background: #fff; cursor: pointer; transition: border-color .15s, background .15s; }
        .wp-amt:hover { border-color: #9CC9E3; }
        .wp-amt b { font-size: 15px; font-weight: 900; color: #0F2744; }
        .wp-amt span { font-size: 11.5px; font-weight: 700; color: #047857; }
        .wp-amt.is-on { border-color: #1D6FD8; background: #EEF5FF; box-shadow: 0 0 0 3px rgba(29, 111, 216, .12); }
        .wp-custom { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 12px; }
        .wp-input { position: relative; flex: 1 1 200px; max-width: 280px; }
        .wp-input input { box-sizing: border-box; width: 100%; height: 42px; padding: 0 40px 0 12px; border: 1px solid #CFDCE7; border-radius: 12px; font-size: 14px; font-weight: 700; color: #0F2744; background: #fff; }
        .wp-input input:focus { outline: 0; border-color: #1D6FD8; box-shadow: 0 0 0 3px rgba(29, 111, 216, .12); }
        .wp-input input.is-invalid { border-color: #F2A3B3; }
        .wp-input i { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-style: normal; font-size: 13px; font-weight: 700; color: #94A3B8; }
        .wp-get { font-size: 13px; color: #475569; }
        .wp-get b { color: #047857; }
        .wp-hint { margin: 8px 0 0; font-size: 12px; line-height: 1.5; color: #64748B; }
        .wp-steps { display: flex; flex-wrap: wrap; gap: 6px 14px; margin: 14px 0 0; padding: 10px 12px; border-radius: 12px; background: #F6FAFD; list-style: none; font-size: 12px; color: #4C6A84; }
        .wp-steps li { display: inline-flex; align-items: center; gap: 6px; }
        .wp-steps b { display: grid; place-items: center; width: 18px; height: 18px; border-radius: 6px; background: #DBEAFE; font-size: 10.5px; color: #1D4ED8; }
        html.theme-dark .wp-rows, html.theme-dark .wp-qr-box, html.theme-dark .wp-amt, html.theme-dark .wp-input input { background: #12283A; border-color: #25445C; }
        html.theme-dark .wp-row-v, html.theme-dark .wp-amt b, html.theme-dark .wp-input input { color: #E2ECF5; }
        html.theme-dark .wp-row + .wp-row { border-color: #1E3A50; }
        html.theme-dark .wp-row.is-code { background: #2A1620; }
        html.theme-dark .wp-steps { background: #15304A; color: #A9C0D2; }
        html.theme-dark .wp-note { background: #2B2410; border-color: #5A4A1E; color: #F2D28A; }
        .wp-note.is-sent { background: #ECFDF5; border-color: #A7F3D0; color: #065F46; } .wp-note.is-sent svg { color: #059669; }
        .wp-done { margin-top: 12px; }
        .wp-done-txt { display: flex; align-items: center; gap: 6px; margin: 0 0 8px 2px; font-size: 12.5px; color: #64748B; } .wp-done-txt svg { width: 15px; height: 15px; flex: none; color: #3B82F6; }
        .wp-done-btn { display: flex; align-items: center; gap: 12px; width: 100%; padding: 12px 16px; border: 0; border-radius: 16px; cursor: pointer; text-align: left; color: #fff;
            background: linear-gradient(135deg, #10B981 0%, #059669 55%, #047857 100%); box-shadow: 0 10px 22px rgba(5, 150, 105, .28); transition: transform .15s, box-shadow .15s, filter .15s; }
        .wp-done-btn:hover { transform: translateY(-1px); box-shadow: 0 14px 28px rgba(5, 150, 105, .34); filter: brightness(1.03); }
        .wp-done-btn:disabled { opacity: .7; cursor: wait; transform: none; }
        .wp-done-ic { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 12px; background: rgba(255, 255, 255, .2); } .wp-done-ic svg { width: 22px; height: 22px; }
        .wp-done-lb { flex: 1; min-width: 0; } .wp-done-lb b { display: block; font-size: 15.5px; font-weight: 900; letter-spacing: -.01em; }
        .wp-done-lb small { display: block; margin-top: 2px; font-size: 12px; font-weight: 600; color: rgba(255, 255, 255, .85); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .wp-done-go { width: 20px; height: 20px; flex: none; transition: transform .15s; } .wp-done-btn:hover .wp-done-go { transform: translateX(3px); }
        .wp-sent { display: flex; align-items: flex-start; gap: 12px; margin-top: 12px; padding: 12px 14px; border-radius: 16px; border: 1px solid #FDE68A; background: linear-gradient(135deg, #FFFBEB, #FEF3C7); }
        .wp-sent-ic { display: grid; place-items: center; width: 38px; height: 38px; flex: none; border-radius: 12px; background: #fff; color: #D97706; box-shadow: 0 2px 6px rgba(217, 119, 6, .15); } .wp-sent-ic svg { width: 19px; height: 19px; }
        .wp-sent b { color: #92400E; } .wp-sent > div > b { display: block; font-size: 14px; font-weight: 800; }
        .wp-sent span { display: block; margin-top: 2px; font-size: 12.5px; line-height: 1.5; color: #A16207; }
        html.theme-dark .wp-note.is-sent { background: #0F2E24; border-color: #1F5A45; color: #A7F3D0; }
        html.theme-dark .wp-sent { background: #2B2410; border-color: #5A4A1E; } html.theme-dark .wp-sent span { color: #F2D28A; }
        html.theme-dark .wp-copy { background: #173246; border-color: #2B4E68; color: #9BD4F5; }
    </style>

    @php
        $wpCopy = fn ($text) => "navigator.clipboard.writeText(".\Illuminate\Support\Js::from((string) $text).").then(() => { done = true; setTimeout(() => done = false, 1600) })";
        $wpMax = \App\Services\WalletService::MAX_TOPUP_AMOUNT;
        $wpRate = max(1, (int) \App\Support\TokenPrice::rate());
    @endphp

    @if ($pendingTopup)
        <x-ws.card title="Chuyển khoản để hoàn tất" icon="qr-code">
            <div class="wp">
                @if ($pendingConfirmedAt)
                    <div class="wp-note is-sent"><x-lucide name="badge-check" /><span>Bạn đã báo chuyển khoản <b>{{ \App\Support\TokenPrice::label($pendingTopup->amount) }}</b> lúc {{ $pendingConfirmedAt->format('H:i d/m/Y') }}. Admin đang đối soát sao kê — duyệt xong token tự cộng vào ví.</span></div>
                @else
                    <div class="wp-note"><x-lucide name="clock-3" /><span>Yêu cầu nạp <b>{{ \App\Support\TokenPrice::label($pendingTopup->amount) }}</b> đang chờ chuyển khoản. Ghi <b>đúng nội dung CK</b> để Admin duyệt nhanh — sai nội dung có thể bị từ chối.</span></div>
                @endif

                <div class="wp-grid">
                    @if ($pendingQrUrl)
                        <div class="wp-qr" x-data="{ zoom: false }" @keydown.escape.window="zoom = false">
                            <button type="button" class="wp-qr-box" @click="zoom = true" aria-label="Phóng to mã QR">
                                <img src="{{ $pendingQrUrl }}" alt="Mã QR ngân hàng nhận tiền">
                                <span class="wp-qr-zoom"><x-lucide name="maximize-2" />Phóng to</span>
                            </button>
                            <a href="{{ $pendingQrUrl }}" download="ma-qr-ngan-hang" class="wp-qr-dl">Tải ảnh QR</a>
                            <div class="wp-modal" x-show="zoom" x-cloak x-transition.opacity @click.self="zoom = false" role="dialog" aria-modal="true" aria-label="Mã QR ngân hàng">
                                <img src="{{ $pendingQrUrl }}" alt="Mã QR ngân hàng nhận tiền (phóng to)">
                                <button type="button" @click="zoom = false" aria-label="Đóng"><x-lucide name="x" /></button>
                            </div>
                        </div>
                    @else
                        <div class="wp-qr"><div class="wp-noqr"><x-lucide name="qr-code" style="width:24px;height:24px;color:#E0A526" /><span>Chưa có ảnh QR — chuyển khoản theo thông tin bên cạnh.</span></div></div>
                    @endif

                    <div>
                        <div class="wp-rows">
                            <div class="wp-row" x-data="{ done: false }">
                                <span class="wp-row-l">Số tiền</span>
                                <span class="wp-row-v">{{ \App\Support\TokenPrice::vnd($pendingTopup->amount) }}<small>= {{ \App\Support\TokenPrice::label($pendingTopup->amount) }}</small></span>
                                <button type="button" class="wp-copy" :class="done && 'is-done'" @click="{{ $wpCopy($pendingTopup->amount) }}" aria-label="Sao chép số tiền"><x-lucide name="copy" x-show="!done" /><x-lucide name="check" x-show="done" x-cloak /><span x-text="done ? 'Đã chép' : 'Chép'">Chép</span></button>
                            </div>
                            <div class="wp-row is-code" x-data="{ done: false }">
                                <span class="wp-row-l">Nội dung CK</span>
                                <span class="wp-row-v">{{ $pendingTopup->transfer_code }}</span>
                                <button type="button" class="wp-copy" :class="done && 'is-done'" @click="{{ $wpCopy($pendingTopup->transfer_code) }}" aria-label="Sao chép nội dung chuyển khoản"><x-lucide name="copy" x-show="!done" /><x-lucide name="check" x-show="done" x-cloak /><span x-text="done ? 'Đã chép' : 'Chép'">Chép</span></button>
                            </div>
                            <div class="wp-row">
                                <span class="wp-row-l">Ngân hàng</span>
                                <span class="wp-row-v">{{ $bankInfo['bankName'] ?: '—' }}</span>
                            </div>
                            <div class="wp-row" x-data="{ done: false }">
                                <span class="wp-row-l">Số tài khoản</span>
                                <span class="wp-row-v" style="letter-spacing:.03em">{{ $bankInfo['accountNo'] ?: '—' }}</span>
                                @if ($bankInfo['accountNo'] ?? '')
                                    <button type="button" class="wp-copy" :class="done && 'is-done'" @click="{{ $wpCopy($bankInfo['accountNo']) }}" aria-label="Sao chép số tài khoản"><x-lucide name="copy" x-show="!done" /><x-lucide name="check" x-show="done" x-cloak /><span x-text="done ? 'Đã chép' : 'Chép'">Chép</span></button>
                                @endif
                            </div>
                            <div class="wp-row">
                                <span class="wp-row-l">Chủ tài khoản</span>
                                <span class="wp-row-v">{{ $bankInfo['accountName'] ?: '—' }}</span>
                            </div>
                        </div>
                        {{-- SỬA 3/11 (khách: "chuyển khoản xong bấm nút thì báo admin, lúc đó mới chuyển sang Chờ duyệt") --}}
                        @if ($pendingConfirmedAt)
                            <div class="wp-sent">
                                <span class="wp-sent-ic"><x-lucide name="clock-3" /></span>
                                <div>
                                    <b>Đang chờ Admin duyệt</b>
                                    <span>Đã gửi thông báo cho Admin, Admin sẽ duyệt sớm nhất có thể. Lâu chưa thấy token, liên hệ hỗ trợ kèm mã <b>{{ $pendingTopup->transfer_code }}</b>.</span>
                                </div>
                            </div>
                        @else
                            <form method="POST" action="{{ route('wallet.confirm', $pendingTopup->id) }}" class="wp-done"
                                  x-data="{ busy: false }"
                                  @submit="if (! confirm({{ \Illuminate\Support\Js::from('Xác nhận bạn đã chuyển '.\App\Support\TokenPrice::vnd($pendingTopup->amount).' với nội dung '.$pendingTopup->transfer_code.'?') }})) { $event.preventDefault(); return; } busy = true">
                                @csrf
                                <p class="wp-done-txt"><x-lucide name="info" />Chuyển khoản xong, bấm nút bên dưới để báo Admin đối soát và cộng token.</p>
                                <button type="submit" class="wp-done-btn" :disabled="busy">
                                    <span class="wp-done-ic"><x-lucide name="check-circle-2" /></span>
                                    <span class="wp-done-lb"><b x-text="busy ? 'Đang gửi…' : 'Tôi đã chuyển khoản'">Tôi đã chuyển khoản</b><small>Báo Admin duyệt · {{ \App\Support\TokenPrice::vnd($pendingTopup->amount) }} · {{ $pendingTopup->transfer_code }}</small></span>
                                    <x-lucide name="arrow-right" class="wp-done-go" />
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </x-ws.card>
    @else
        <x-ws.card title="Nạp token" icon="banknote">
            <form method="POST" action="{{ route('wallet.request') }}" class="wp"
                  x-data="{ amount: {{ (int) old('amount', 100000) }}, get tokens() { const n = Math.floor((Number(this.amount) || 0) / {{ $wpRate }}); return n > 0 ? n.toLocaleString('vi-VN') + ' token' : '—'; } }">
                @csrf
                <div class="wp-amounts" role="radiogroup" aria-label="Chọn nhanh số tiền nạp">
                    @foreach ([50000, 100000, 200000, 500000] as $quick)
                        <button type="button" class="wp-amt" :class="Number(amount) === {{ $quick }} && 'is-on'" @click="amount = {{ $quick }}" role="radio" :aria-checked="Number(amount) === {{ $quick }}">
                            <b>{{ \App\Support\TokenPrice::vnd($quick) }}</b>
                            <span>{{ \App\Support\TokenPrice::label($quick) }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="wp-custom">
                    <label class="wp-input">
                        <span class="sr-only">Số tiền muốn nạp (VNĐ)</span>
                        <input id="amount" name="amount" type="number" min="10000" max="{{ $wpMax }}" step="1000" required inputmode="numeric"
                               value="{{ (int) old('amount', 100000) }}" x-model="amount" placeholder="Số tiền khác" class="{{ $errors->has('amount') ? 'is-invalid' : '' }}">
                        <i>đ</i>
                    </label>
                    <span class="wp-get">Nhận <b x-text="tokens">{{ \App\Support\TokenPrice::label((int) old('amount', 100000)) }}</b></span>
                    <x-ws.btn type="submit" variant="primary" icon="plus">Tạo yêu cầu nạp</x-ws.btn>
                </div>
                <p class="wp-hint">Tối thiểu 10.000đ, <b>tối đa {{ \App\Support\TokenPrice::vnd($wpMax) }} mỗi lần</b> · 1 token = {{ \App\Support\TokenPrice::vnd($wpRate) }}.</p>

                <ol class="wp-steps">
                    <li><b>1</b>Tạo yêu cầu nạp</li>
                    <li><b>2</b>Quét QR, chuyển khoản đúng nội dung</li>
                    <li><b>3</b>Admin duyệt, token vào ví</li>
                </ol>

                @unless ($bankReady)
                    <p class="wp-hint" style="color:#8A5A00">Hệ thống chưa cấu hình tài khoản ngân hàng nhận tiền — quản trị viên cần điền ở <b>Cấu hình hệ thống</b> (kèm ảnh QR).</p>
                @endunless
            </form>
        </x-ws.card>
    @endif
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
