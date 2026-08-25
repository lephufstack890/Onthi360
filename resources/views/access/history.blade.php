{{--
  Route: access.history (mới 25/8 (2)) | Frame: mở rộng ACC-07
  "nhớ lưu lại lịch sử đặt mua có học sinh luôn" — liệt kê TOÀN BỘ Order do CHÍNH user này đặt
  (buyer_id), khác access.myAccess vốn chỉ hiện AccessRight (quyền hiện có), không hiện Order
  (đơn) thô kèm trạng thái xử lý/phương thức thanh toán. Dữ liệu thật do
  App\Http\Controllers\Access\AccessController::history() truyền vào qua
  App\Services\Access\AccessService::purchaseHistoryData().
--}}
@extends('layouts.guest')

@section('title', 'Lịch sử đặt mua')

@section('content')
    @php
        $orders = $orders ?? [];
    @endphp

    <div class="max-w-2xl mx-auto px-4 py-10 space-y-6">
        <a href="{{ route('access.myAccess') }}" class="text-sm text-slate-500 inline-block">‹ Quyền truy cập của tôi</a>

        <div>
            <h1 class="text-lg font-semibold text-slate-800 mb-1">Lịch sử đặt mua</h1>
            <p class="text-sm text-slate-500">Toàn bộ đơn bạn đã đặt — kể cả đơn đang chờ admin duyệt hoặc đã bị từ chối.</p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <div class="divide-y divide-slate-100">
                @forelse ($orders as $o)
                    <div class="py-4">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <div>
                                <p class="text-sm font-medium text-slate-700">{{ $o['orderNo'] }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $o['createdAt']?->format('d/m/Y H:i') }} · {{ $o['paymentMethod'] }}</p>
                            </div>
                            <x-status-badge :tone="$o['tone']">{{ $o['status'] }}</x-status-badge>
                        </div>
                        <div class="mt-2 space-y-1">
                            @foreach ($o['items'] as $item)
                                <p class="text-sm text-slate-600">{{ $item['title'] }} <span class="text-xs text-slate-400">({{ $item['scope'] }})</span></p>
                            @endforeach
                        </div>
                        <p class="text-sm font-medium text-slate-700 mt-2">{{ number_format($o['totalAmount']) }}đ</p>
                    </div>
                @empty
                    <x-empty-state title="Bạn chưa đặt đơn nào" />
                @endforelse
            </div>
        </div>
    </div>
@endsection
