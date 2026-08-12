{{--
  Route: admin.orders.show
  Spec: 7.4 luồng: Đặt đơn → Thanh toán → Admin duyệt → Cấp mã → Kích hoạt → Quyền bắt đầu.
  $orderModel (Eloquent thật) do App\Http\Controllers\Admin\OrderController truyền vào.
  TODO: xử lý submit duyệt/từ chối gọi App\Services\OrderActivationService::approve()/reject().
--}}
@extends('layouts.admin')

@section('title', 'Chi tiết đơn hàng')
@section('page-title', 'Chi tiết đơn hàng')

@section('content')
    @php
        $scopeLabel = $orderModel->items->first()?->scope?->value === 'teacher_teaching' ? 'Dùng để dạy' : 'Học cá nhân';
        $methodLabel = $orderModel->payment_method?->value === 'offline' ? 'Thanh toán ngoài hệ thống' : ($orderModel->payment_method?->value ?? '');
    @endphp

    <a href="{{ route('admin.orders.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Đơn hàng</a>

    <x-page-header :title="'Đơn #OD-'.$orderModel->id" :subtitle="'Người mua: '.($orderModel->buyer->name ?? '').' · Phạm vi quyền: '.$scopeLabel" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-4">Sản phẩm trong đơn</h2>
                <table class="w-full text-sm">
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
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-2">Phương thức thanh toán</h2>
                <p class="text-sm text-slate-500">{{ $methodLabel }}</p>
                {{-- TODO: hiển thị chứng từ chuyển khoản nếu có tải lên --}}
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-4">Quyết định</h2>
            <form class="space-y-3">
                <button type="button" class="w-full px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium">Duyệt & cấp mã</button>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Lý do từ chối (bắt buộc nếu từ chối)</label>
                    <textarea rows="3" class="w-full rounded-lg border border-slate-200 text-sm p-2"></textarea>
                </div>
                <button type="button" class="w-full px-4 py-2 rounded-lg border border-rose-300 text-rose-600 text-sm font-medium">Từ chối có lý do</button>
            </form>
        </div>
    </div>
@endsection
