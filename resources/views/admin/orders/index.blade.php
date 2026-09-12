@extends('layouts.admin')

@section('title', 'Đơn hàng')
@section('page-title', 'Đơn hàng')

@section('content')
    @php
        $tab = $tab ?? 'all';
        $tabs = $tabs ?? [];
        $orders = $orders ?? [];
        $total = $total ?? count($orders);
        $tokenTopups = $tokenTopups ?? [];
    @endphp

    <x-ws.page-header title="Đơn hàng" icon="file-check-2" subtitle="Duyệt/từ chối phải ghi lý do; mọi thay đổi ghi audit log." />

    @if (session('status') === 'topup-approved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã duyệt — đã cộng token cho học sinh.'])
    @elseif (session('status') === 'topup-rejected')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã từ chối yêu cầu nạp token.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-ws.tabs :tabs="$tabs" />

    <x-ws.table :columns="['Mã đơn', 'Người mua', 'Sản phẩm', 'Tổng tiền', 'Trạng thái', '']">
        @forelse ($orders as $o)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">#OD-{{ $o['id'] }}</td>
                <td class="px-4 py-3 text-slate-500">
                    <div class="flex items-center gap-2.5">
                        <x-ws.avatar :name="$o['buyer']" size="sm" />
                        <span>{{ $o['buyer'] }}</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $o['items'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $o['total'] }}</td>
                <td class="px-4 py-3"><x-ws.badge :tone="$o['tone']">{{ $o['status'] }}</x-ws.badge></td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.orders.show', $o['id']) }}" class="text-blue-600 font-medium">Xem</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Chưa có đơn hàng nào.</td></tr>
        @endforelse
    </x-ws.table>
    <x-ws.pagination-note :shown="count($orders)" :total="$total" />

    <div class="mt-8">
        <h2 class="text-base font-semibold text-slate-700 mb-1">💳 Yêu cầu nạp token</h2>
        <p class="text-xs text-slate-400 mb-3">Đối soát đúng số tiền + nội dung chuyển khoản (mã CK) trên sao kê ngân hàng trước khi duyệt (note họp 13/8, mục 7-8).</p>

        <x-ws.table :columns="['Học sinh', 'Số tiền', 'Mã CK', 'Trạng thái', '']">
            @forelse ($tokenTopups as $t)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-700">{{ $t['user'] }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $t['amount'] }}</td>
                    <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ $t['transferCode'] }}</td>
                    <td class="px-4 py-3"><x-ws.badge :tone="$t['tone']">{{ $t['status'] }}</x-ws.badge></td>
                    <td class="px-4 py-3 text-right">
                        @if ($t['isPending'])
                            <div x-data="{ open: false }" class="inline-block text-left">
                                <div class="space-x-3">
                                    <form method="POST" action="{{ route('admin.orders.token-topups.approve', $t['id']) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-emerald-600 font-medium">Duyệt (cộng token)</button>
                                    </form>
                                    <button type="button" @click="open = !open" class="text-blue-600 font-medium" x-text="open ? 'Đóng' : 'Từ chối'"></button>
                                </div>
                                <form x-show="open" x-cloak method="POST" action="{{ route('admin.orders.token-topups.reject', $t['id']) }}"
                                      class="mt-2 space-y-2 text-left bg-slate-50 border border-sky-100 rounded-xl p-3 w-64" x-data="{ reason: '' }">
                                    @csrf
                                    <textarea name="reason" x-model="reason" rows="2" required class="admin-input" placeholder="Lý do từ chối (vd: không thấy tiền về đúng mã)..."></textarea>
                                    <button type="submit" :disabled="reason.trim().length === 0" class="w-full px-3 py-1.5 rounded-xl bg-blue-600 text-white text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed">Xác nhận từ chối</button>
                                </form>
                            </div>
                        @else
                            <span class="text-xs text-slate-400">Đã xử lý</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có yêu cầu nạp token nào.</td></tr>
            @endforelse
        </x-ws.table>
    </div>
@endsection
