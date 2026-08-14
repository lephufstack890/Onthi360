{{--
  Route: wallet.index (GET) / wallet.request (POST) | Frame: mở rộng ACC-03/ACC-07
  Spec: note họp 13/8, mục 7-8 — "Nộp tiền thành token" + "Khi đăng ký thi thì thông tin
  ngân hàng – QR người dùng chỉ cần chuyển khoản là xong". Nạp tiền MỘT LẦN thành số dư
  token dùng chung cho nhiều lần thanh toán sau này, thay vì chuyển khoản riêng lẻ từng đơn.
  Dữ liệu thật do App\Http\Controllers\Access\WalletController truyền vào qua
  App\Services\WalletService. P0 chưa có cổng thanh toán tự động — Admin đối soát sao kê
  rồi duyệt tay (xem admin.orders.index, mục "Yêu cầu nạp token").
--}}
@extends('layouts.guest')

@section('title', 'Ví token')

@section('content')
    @php
        $balance = $balance ?? 0;
        $history = $history ?? [];
        $bankInfo = $bankInfo ?? [];
        $pendingTopup = $pendingTopup ?? null;
        $pendingQrUrl = $pendingQrUrl ?? null;
    @endphp

    <div class="max-w-2xl mx-auto px-4 py-10 space-y-6">
        <a href="{{ route('access.myAccess') }}" class="text-sm text-slate-500 inline-block">‹ Quyền truy cập của tôi</a>

        @if (session('status') === 'topup-requested')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo yêu cầu nạp token — chuyển khoản đúng nội dung bên dưới rồi chờ Admin duyệt.'])
        @endif
        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <p class="text-sm text-slate-500">Số dư token hiện có</p>
            <p class="text-3xl font-semibold text-rose-600 mt-1">{{ number_format($balance) }} <span class="text-base font-normal text-slate-400">token</span></p>
            <p class="text-xs text-slate-400 mt-2">1 token = 1đ. Dùng để đăng ký thi, mua học liệu... không cần chuyển khoản riêng cho từng lần (note họp 13/8).</p>
        </div>

        @if ($pendingTopup)
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6">
                <p class="text-sm font-medium text-amber-700 mb-3">⏳ Yêu cầu nạp {{ number_format($pendingTopup->amount) }}đ đang chờ duyệt</p>

                @if ($pendingQrUrl)
                    <div class="flex flex-col items-center mb-4">
                        <img src="{{ $pendingQrUrl }}" alt="QR chuyển khoản" class="w-48 h-48 rounded-xl border border-amber-200 bg-white">
                    </div>
                @else
                    <p class="text-xs text-amber-600 mb-3">Chưa cấu hình đủ thông tin ngân hàng để tạo QR — vui lòng chuyển khoản thủ công theo thông tin bên dưới.</p>
                @endif

                <div class="bg-white rounded-xl border border-amber-100 p-4 text-sm space-y-1.5">
                    <div class="flex justify-between"><span class="text-slate-500">Ngân hàng</span><span class="font-medium text-slate-700">{{ $bankInfo['bankName'] ?: '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Số tài khoản</span><span class="font-medium text-slate-700">{{ $bankInfo['accountNo'] ?: '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Chủ tài khoản</span><span class="font-medium text-slate-700">{{ $bankInfo['accountName'] ?: '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Số tiền</span><span class="font-medium text-slate-700">{{ number_format($pendingTopup->amount) }}đ</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Nội dung CK (bắt buộc)</span><span class="font-semibold text-rose-600">{{ $pendingTopup->transfer_code }}</span></div>
                </div>
                <p class="text-xs text-amber-600 mt-3">Chuyển khoản đúng nội dung ở trên để Admin đối soát chính xác — sai nội dung có thể khiến yêu cầu bị từ chối.</p>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h3 class="font-medium text-slate-700 mb-3">Nạp token</h3>
                <form method="POST" action="{{ route('wallet.request') }}" class="flex gap-3">
                    @csrf
                    <input type="number" name="amount" min="10000" step="1000" value="{{ old('amount', 100000) }}" required
                           class="flex-1 rounded-lg border border-slate-200 text-sm p-2.5" placeholder="Số tiền (VNĐ), tối thiểu 10.000đ">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shrink-0">Tạo yêu cầu nạp</button>
                </form>
                <p class="text-xs text-slate-400 mt-2">Sau khi tạo yêu cầu, bạn sẽ thấy mã QR + nội dung chuyển khoản riêng để Admin đối soát.</p>
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h3 class="font-medium text-slate-700 mb-3">Lịch sử nạp token</h3>
            <div class="divide-y divide-slate-100">
                @forelse ($history as $h)
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-700">{{ number_format($h['amount']) }}đ · <span class="text-xs text-slate-400">{{ $h['transferCode'] }}</span></p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $h['createdAt']?->format('d/m/Y H:i') }}</p>
                            @if ($h['rejectReason'])
                                <p class="text-xs text-rose-500 mt-0.5">Lý do từ chối: {{ $h['rejectReason'] }}</p>
                            @endif
                        </div>
                        <x-status-badge :tone="$h['tone']">{{ $h['status'] }}</x-status-badge>
                    </div>
                @empty
                    <x-empty-state title="Chưa có lần nạp token nào" />
                @endforelse
            </div>
        </div>
    </div>
@endsection
