{{--
  Route: admin.orders.index | Frame: ADM-04
  Spec: 7.4 (Tạo đơn ≠ đã thanh toán ≠ đã có quyền).
  TODO controller: truyền $orders (paginate) với filter trạng thái.
--}}
@extends('layouts.admin')

@section('title', 'Đơn hàng')
@section('page-title', 'Đơn hàng')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\OrderController truyền vào. --}}
    @php
        $tab = $tab ?? 'all';
        $tabs = $tabs ?? [];
        $orders = $orders ?? [];
        $total = $total ?? count($orders);
    @endphp

    <x-page-header title="Đơn hàng" subtitle="Duyệt/từ chối phải ghi lý do; mọi thay đổi ghi audit log (7.4)." />

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Mã đơn', 'Người mua', 'Sản phẩm', 'Tổng tiền', 'Trạng thái', '']">
        @forelse ($orders as $o)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">#OD-{{ $o['id'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $o['buyer'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $o['items'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $o['total'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$o['tone']">{{ $o['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.orders.show', $o['id']) }}" class="text-rose-600 font-medium">Xem</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Chưa có đơn hàng nào.</td></tr>
        @endforelse
    </x-data-table>
    <x-pagination-note :shown="count($orders)" :total="$total" />
@endsection
