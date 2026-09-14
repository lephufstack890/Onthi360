{{--
  Route: access.history (mới 25/8 (2)) | Frame: mở rộng ACC-07
  "nhớ lưu lại lịch sử đặt mua có học sinh luôn" — liệt kê TOÀN BỘ Order do CHÍNH user này đặt
  (buyer_id), khác access.myAccess vốn chỉ hiện AccessRight (quyền hiện có), không hiện Order
  (đơn) thô kèm trạng thái xử lý/phương thức thanh toán. Dữ liệu thật do
  App\Http\Controllers\Access\AccessController::history() truyền vào qua
  App\Services\Access\AccessService::purchaseHistoryData().

  SỬA 14/9 — CHỈ ĐỔI GIAO DIỆN, KHÔNG ĐỔI LOGIC:
    · Trước đây @extends('layouts.guest') nên bấm vào là văng ra vỏ trang công khai. Giờ dùng
      chung khung khu làm việc, vai trò theo đúng người đang đăng nhập (như Ví token và
      Kích hoạt mã).
    · Dựng lại theo bộ thẻ x-ws.*; vẫn nguyên biến $orders và mọi khoá trong đó.
--}}
@php
    $historyUser = auth()->user();
    $historyRole = match (true) {
        (bool) $historyUser?->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN) => 'admin',
        (bool) $historyUser?->hasRole(\App\Models\Role::TEACHER) => 'teacher',
        (bool) $historyUser?->hasRole(\App\Models\Role::PARENT) => 'parent',
        default => 'student',
    };
@endphp
@extends('layouts.workspace', ['wsRole' => $historyRole])

@section('title', 'Lịch sử đặt mua')
@section('page-title', 'Lịch sử đặt mua')

@section('content')
    @php
        $orders = $orders ?? [];

        /* Số liệu tóm tắt — đếm lại từ đúng mảng $orders đang hiển thị, không truy vấn thêm. */
        $orderCount = count($orders);
        $doneCount = 0;
        $waitingCount = 0;
        $totalSpent = 0;

        foreach ($orders as $o) {
            if (($o['tone'] ?? '') === 'success') {
                $doneCount++;
                $totalSpent += (int) ($o['totalAmount'] ?? 0);
            } elseif (($o['tone'] ?? '') === 'warning') {
                $waitingCount++;
            }
        }
    @endphp

    <x-ws.page-header title="Lịch sử đặt mua" icon="receipt-text"
                      :back="route('access.myAccess')" back-label="Quyền truy cập của tôi"
                      subtitle="Toàn bộ đơn bạn đã đặt — kể cả đơn đang chờ duyệt hoặc đã bị từ chối.">
        <x-slot:actions>
            <x-ws.btn :href="route('access.myAccess')" variant="onhero-ghost" icon="shield-check">Quyền của tôi</x-ws.btn>
            <x-ws.btn :href="route('wallet.index')" variant="onhero" icon="wallet-cards">Ví token</x-ws.btn>
        </x-slot:actions>
    </x-ws.page-header>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-ws.stat label="Tổng số đơn" :value="$orderCount" tone="blue" icon="receipt-text" hint="Tính cả đơn bị từ chối" />
        <x-ws.stat label="Đã duyệt / hoàn tất" :value="$doneCount" tone="emerald" icon="check-circle-2" hint="Đơn đã được xử lý xong" />
        <x-ws.stat label="Đang chờ" :value="$waitingCount" :tone="$waitingCount > 0 ? 'amber' : 'neutral'" icon="clock-3"
                   :hint="$waitingCount > 0 ? 'Chờ duyệt hoặc chờ thanh toán' : 'Không có đơn nào đang chờ'" />
        <x-ws.stat label="Đã chi" :value="number_format($totalSpent).'đ'" tone="violet" icon="banknote" hint="Cộng từ các đơn đã duyệt" />
    </div>

    @if ($orderCount === 0)
        <x-ws.empty-state icon="receipt-text" title="Bạn chưa đặt đơn nào"
                          description="Các đơn đặt học liệu của bạn sẽ hiện ở đây kèm trạng thái xử lý."
                          action-label="Xem học liệu" :action-href="route('materials.index')" />
    @else
        <div class="space-y-3">
            @foreach ($orders as $o)
                {{-- Mỗi đơn một thẻ: đầu thẻ là mã đơn + trạng thái, thân là các học liệu trong đơn,
                     chân thẻ là tổng tiền — dễ đọc hơn bảng vì một đơn có thể nhiều dòng học liệu. --}}
                <x-ws.card padding="p-0">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-sky-100 bg-[#F8FBFE] text-blue-600">
                                <x-lucide name="receipt-text" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-black tracking-wide text-slate-800">{{ $o['orderNo'] }}</p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[11px] text-slate-400">
                                    <span>{{ $o['createdAt']?->format('d/m/Y H:i') }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span class="inline-flex items-center gap-1">
                                        <x-lucide name="banknote" class="h-3 w-3" />{{ $o['paymentMethod'] }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <x-ws.badge :tone="$o['tone']">{{ $o['status'] }}</x-ws.badge>
                    </div>

                    <div class="divide-y divide-slate-50">
                        @foreach ($o['items'] as $item)
                            <div class="flex items-center justify-between gap-3 px-4 py-2.5 sm:px-5">
                                <p class="min-w-0 flex-1 text-[13px] font-medium text-slate-700">{{ $item['title'] }}</p>
                                <x-ws.badge tone="neutral">{{ $item['scope'] }}</x-ws.badge>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-[#F8FBFE] px-4 py-2.5 sm:px-5">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Tổng đơn</span>
                        <span class="text-[15px] font-black text-slate-800">{{ number_format($o['totalAmount']) }}đ</span>
                    </div>
                </x-ws.card>
            @endforeach
        </div>

        <x-ws.pagination-note :shown="$orderCount" :total="$orderCount" />
    @endif
@endsection
