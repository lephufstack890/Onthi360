@extends('layouts.admin')

@section('title', 'Mã kích hoạt')
@section('page-title', 'Mã kích hoạt')

@section('content')
    @php
        $codes = $codes ?? [];
        $codeStatusMessage = match (session('status')) {
            'code-revoked' => 'Đã thu hồi mã, đã ghi lý do.',
            // SỬA 18/9 — vừa cấp mã xong thì mã đó cần nổi bật ngay để admin sao chép đưa cho
            // người dùng; không thì phải dò lại trong bảng.
            'code-created' => 'Đã cấp mã kích hoạt. Sao chép mã bên dưới và gửi cho đúng tài khoản được cấp.',
            default => null,
        };
        $newCode = session('newCode');
        $assignedUserReady = $assignedUserReady ?? true;
    @endphp
    @if ($codeStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $codeStatusMessage])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-ws.page-header title="Mã kích hoạt" icon="ticket" subtitle="Mã cấp tay chỉ mở đúng 1 tài liệu cho đúng 1 tài khoản. Thời hạn quyền bắt đầu tại thời điểm kích hoạt mã hợp lệ, không phải lúc đặt đơn.">
        <x-slot:actions>
            @if ($assignedUserReady)
                <a href="{{ route('admin.activation-codes.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Cấp mã kích hoạt</a>
            @endif
        </x-slot:actions>
    </x-ws.page-header>

    {{-- SỬA 18/9 — máy chủ chưa chạy migration 000860 thì chưa khoá mã theo tài khoản được;
         nói thẳng việc cần làm thay vì để nút "Cấp mã" bấm vào lại báo lỗi khó hiểu. --}}
    @if (! $assignedUserReady)
        <div class="mb-4 rounded-3xl border border-amber-200 bg-amber-50 p-4 text-[13px] text-amber-800">
            <p class="font-bold">Chưa chạy migration cho tính năng cấp mã theo tài khoản</p>
            <p class="mt-1 text-xs leading-relaxed">Chạy <code class="rounded bg-amber-100 px-1.5 py-0.5 font-mono">php artisan migrate</code> trên máy chủ rồi tải lại trang. Trước khi chạy, mục "Cấp mã kích hoạt" sẽ chưa dùng được và cột "Cấp cho" bên dưới còn trống.</p>
        </div>
    @endif

    {{-- Mã vừa cấp — hiện to, kèm nút sao chép (Alpine, không thêm tệp script nào). --}}
    @if ($newCode)
        <div x-data="{ copied: false, copyFailed: false }" class="mb-4 flex flex-wrap items-center gap-3 rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-emerald-800">Mã vừa cấp</p>
                <p class="mt-1 font-mono text-lg font-black tracking-[0.15em] text-emerald-900">{{ $newCode }}</p>
                <p class="mt-1 text-xs text-emerald-700">Gửi mã này cho đúng tài khoản được cấp — tài khoản khác nhập vào sẽ không mở được.</p>
                <p x-show="copyFailed" x-cloak class="mt-1 text-xs font-medium text-amber-700">Trình duyệt không cho sao chép tự động — hãy bôi đen mã ở trên rồi copy tay.</p>
            </div>
            {{-- navigator.clipboard chỉ có ở ngữ cảnh bảo mật (https hoặc localhost) — mở trang
                 admin qua http trên IP nội bộ là không có. Bắt lỗi để nút không "bấm không ăn"
                 im lặng, mà nói rõ hãy bôi đen mã ở trên để sao chép tay. --}}
            <button type="button" class="shrink-0 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white transition-colors hover:bg-emerald-700"
                    @click="
                        copyFailed = false;
                        try {
                            navigator.clipboard.writeText(@js($newCode))
                                .then(() => { copied = true; setTimeout(() => copied = false, 2000); })
                                .catch(() => { copyFailed = true; });
                        } catch (e) {
                            copyFailed = true;
                        }
                    "
                    x-text="copied ? 'Đã sao chép' : 'Sao chép mã'">Sao chép mã</button>
        </div>
    @endif

    <x-ws.table :columns="['Mã', 'Tài liệu', 'Cấp cho', 'Phạm vi', 'Thời hạn', 'Trạng thái', '']">
        @forelse ($codes as $c)
            <tr>
                <td class="px-4 py-3">
                    <span class="font-mono font-medium text-slate-700">{{ $c['code'] }}</span>
                    {{-- Nguồn gốc mã: sinh từ đơn hàng, hay admin cấp tay. --}}
                    @if ($c['order'])
                        <div class="text-xs font-normal text-slate-400">
                            Từ đơn <a href="{{ route('admin.orders.show', $c['order']) }}" class="text-blue-600">#OD-{{ $c['order'] }}</a>
                        </div>
                    @elseif ($c['assignedTo'])
                        <div class="text-xs font-normal text-slate-400">Admin cấp tay</div>
                    @endif
                    @if ($c['note'])
                        <div class="text-xs font-normal text-slate-400">{{ $c['note'] }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $c['product'] }}</td>
                {{-- SỬA 18/9 — "Cấp cho" là chỗ nhìn ra ngay mã này khoá cho ai. Mã sinh từ đơn
                     hàng không khoá theo người (giữ nguyên hành vi cũ) nên ghi rõ như vậy, chứ
                     không để trống khiến người đọc tưởng thiếu dữ liệu. --}}
                <td class="px-4 py-3">
                    @if ($c['assignedTo'])
                        <span class="text-slate-600">{{ $c['assignedTo'] }}</span>
                        @if ($c['activatedBy'] && $c['activatedBy'] !== $c['assignedTo'])
                            <div class="text-xs text-amber-600">Đã kích hoạt bởi: {{ $c['activatedBy'] }}</div>
                        @elseif ($c['activatedAt'])
                            <div class="text-xs text-slate-400">Đã kích hoạt {{ $c['activatedAt'] }}</div>
                        @endif
                    @else
                        <span class="text-slate-400">Không khoá theo tài khoản</span>
                        @if ($c['activatedBy'])
                            <div class="text-xs text-slate-400">Đã dùng bởi: {{ $c['activatedBy'] }}</div>
                        @endif
                    @endif
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $c['scope'] }}</td>
                <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $c['validity'] }}</td>
                <td class="px-4 py-3"><x-ws.badge :tone="$c['tone']">{{ $c['status'] }}</x-ws.badge></td>
                <td class="px-4 py-3 text-right">
                    @if ($c['canRevoke'])
                        <div x-data="{ open: false, reason: '' }" class="inline-block text-left">
                            <button type="button" @click="open = !open" class="text-blue-600 font-medium" x-text="open ? 'Đóng' : 'Thu hồi'"></button>
                            <form x-show="open" x-cloak method="POST" action="{{ route('admin.activation-codes.revoke', $c['id']) }}" class="mt-2 space-y-2 text-left bg-slate-50 border border-sky-100 rounded-xl p-3 w-64">
                                @csrf
                                <textarea name="reason" x-model="reason" rows="2" required class="admin-input" placeholder="Lý do thu hồi (bắt buộc)..."></textarea>
                                <button type="submit" :disabled="reason.trim().length === 0" class="w-full px-3 py-1.5 rounded-xl bg-blue-600 text-white text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed">Xác nhận thu hồi</button>
                            </form>
                        </div>
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Chưa có mã kích hoạt nào — bấm "+ Cấp mã kích hoạt" để cấp mã đầu tiên.</td></tr>
        @endforelse
    </x-ws.table>
@endsection
