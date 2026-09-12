@extends('layouts.admin')

@section('title', 'Chi tiết đơn hàng')
@section('page-title', 'Chi tiết đơn hàng')

@section('content')
    @php
        $scopeLabel = $orderModel->items->first()?->scope?->value === 'teacher_teaching' ? 'Dùng để dạy' : 'Học cá nhân';
        $methodLabel = $orderModel->payment_method?->value === 'offline' ? 'Thanh toán ngoài hệ thống' : ($orderModel->payment_method?->value ?? '');
        $pendingStatuses = ['created', 'pending_payment', 'pending_approval'];
        $canDecide = in_array($orderModel->status->value, $pendingStatuses, true) && $orderModel->payment_method?->value === 'offline';
        $orderStatusMessage = match (session('status')) {
            'order-approved' => 'Đã duyệt đơn, đã sinh mã kích hoạt cho từng sản phẩm.',
            'order-rejected' => 'Đã từ chối đơn, đã ghi lý do.',
            default => null,
        };
    @endphp
    @if ($orderStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $orderStatusMessage])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <a href="{{ route('admin.orders.index') }}" class="text-[13px] text-slate-500 mb-4 inline-block">‹ Quay lại Đơn hàng</a>

    <x-ws.page-header :title="'🧾 Đơn #OD-'.$orderModel->id" :subtitle="'Người mua: '.($orderModel->buyer->name ?? '').' · Phạm vi quyền: '.$scopeLabel" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h2 class="font-medium text-slate-700 mb-4">Sản phẩm trong đơn</h2>
                <table class="w-full text-[13px]">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($orderModel->items as $it)
                            <tr>
                                <td class="py-2 text-slate-700">{{ $it->product->title ?? '' }}{{ $it->include_print ? ' (kèm bản in)' : '' }}</td>
                                <td class="py-2 text-slate-400 text-right">x{{ $it->quantity }}</td>
                                <td class="py-2 text-slate-500 text-right">{{ number_format($it->unit_price) }}đ</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h2 class="font-medium text-slate-700 mb-2">Phương thức thanh toán</h2>
                <p class="text-[13px] text-slate-500">{{ $methodLabel }}</p>
                {{-- TODO: hiển thị chứng từ chuyển khoản nếu có tải lên --}}
            </div>
        </div>

        <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
            <h2 class="font-medium text-slate-700 mb-4">Quyết định</h2>
            @if ($canDecide)
                <div class="space-y-3">
                    <form method="POST" action="{{ route('admin.orders.approve', $orderModel->id) }}" onsubmit="return confirm('Xác nhận duyệt đơn này? Hệ thống sẽ tự sinh mã kích hoạt cho từng sản phẩm.');">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 rounded-xl bg-emerald-600 text-white text-[13px] font-medium">Duyệt & cấp mã</button>
                    </form>
                    <form method="POST" action="{{ route('admin.orders.reject', $orderModel->id) }}" class="space-y-2" x-data="{ reason: '' }">
                        @csrf
                        <label class="block text-[13px] text-slate-600 mb-1">Lý do từ chối (bắt buộc)</label>
                        <textarea name="reason" x-model="reason" rows="3" required class="admin-input" placeholder="Nêu rõ lý do từ chối..."></textarea>
                        <button type="submit" :disabled="reason.trim().length === 0" class="w-full px-4 py-2 rounded-xl border border-blue-300 text-blue-600 text-[13px] font-medium disabled:opacity-40 disabled:cursor-not-allowed">Từ chối có lý do</button>
                    </form>
                </div>
            @else
                <p class="text-[13px] text-slate-400">Đơn này không còn ở trạng thái chờ duyệt (hoặc thanh toán qua VNPAY, chưa hỗ trợ duyệt thủ công) — không có quyết định nào để thao tác thêm.</p>
            @endif
        </div>
    </div>
@endsection
