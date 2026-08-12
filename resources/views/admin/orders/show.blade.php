{{--
  Route: admin.orders.show
  Spec: 7.4 luồng: Đặt đơn → Thanh toán → Admin duyệt → Cấp mã → Kích hoạt → Quyền bắt đầu.
  TODO controller: truyền $order thật; xử lý submit duyệt/từ chối gọi
  App\Services\OrderActivationService::approve()/reject(), sinh mã qua service.
--}}
@extends('layouts.admin')

@section('title', 'Chi tiết đơn hàng')
@section('page-title', 'Chi tiết đơn hàng')

@section('content')
    @php
        $order = [
            'id' => request()->route('order', 1042),
            'buyer' => 'Trần Thị B',
            'scope' => 'Học cá nhân',
            'method' => 'Thanh toán ngoài hệ thống',
            'status' => 'Chờ duyệt',
            'items' => [
                ['title' => 'Sách: Ôn thi Tin học 10', 'qty' => 1, 'price' => '199.000đ'],
                ['title' => 'Mua kèm bản in', 'qty' => 1, 'price' => '50.000đ'],
            ],
        ];
    @endphp

    <a href="{{ route('admin.orders.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Đơn hàng</a>

    <x-page-header :title="'Đơn #OD-'.$order['id']" :subtitle="'Người mua: '.$order['buyer'].' · Phạm vi quyền: '.$order['scope']" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-4">Sản phẩm trong đơn</h2>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($order['items'] as $it)
                            <tr>
                                <td class="py-2 text-slate-700">{{ $it['title'] }}</td>
                                <td class="py-2 text-slate-400 text-right">x{{ $it['qty'] }}</td>
                                <td class="py-2 text-slate-500 text-right">{{ $it['price'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-2">Phương thức thanh toán</h2>
                <p class="text-sm text-slate-500">{{ $order['method'] }}</p>
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
