{{--
  Route: admin.orders.index | Frame: ADM-04
  Spec: 7.4 (Tạo đơn ≠ đã thanh toán ≠ đã có quyền).
  TODO controller: truyền $orders (paginate) với filter trạng thái.
--}}
@extends('layouts.admin')

@section('title', 'Đơn hàng')
@section('page-title', 'Đơn hàng')

@section('content')
    @php
        $tab = request('tab', 'all');
        $tabs = [
            ['label' => 'Tất cả', 'href' => route('admin.orders.index'), 'active' => $tab === 'all', 'count' => 412],
            ['label' => 'Chờ duyệt', 'href' => route('admin.orders.index', ['tab' => 'pending']), 'active' => $tab === 'pending', 'count' => 7],
            ['label' => 'Hoàn tất', 'href' => route('admin.orders.index', ['tab' => 'done']), 'active' => $tab === 'done', 'count' => 380],
            ['label' => 'Từ chối/hủy', 'href' => route('admin.orders.index', ['tab' => 'rejected']), 'active' => $tab === 'rejected', 'count' => 25],
        ];
        $orders = [
            ['id' => 1042, 'buyer' => 'Trần Thị B', 'items' => 'Sách: Ôn thi Tin học 10 (bản mềm + in)', 'total' => '249.000đ', 'status' => 'Chờ duyệt', 'tone' => 'warning'],
            ['id' => 1041, 'buyer' => 'Nguyễn Văn A', 'items' => 'Chuyên đề: Cấu trúc dữ liệu nâng cao (dùng để dạy)', 'total' => '349.000đ', 'status' => 'Chờ duyệt', 'tone' => 'warning'],
            ['id' => 1039, 'buyer' => 'Phạm Thị D', 'items' => 'Đề thi thử HSG Tin 11', 'total' => '99.000đ', 'status' => 'Hoàn tất', 'tone' => 'success'],
        ];
    @endphp

    <x-page-header title="Đơn hàng" subtitle="Duyệt/từ chối phải ghi lý do; mọi thay đổi ghi audit log (7.4)." />

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Mã đơn', 'Người mua', 'Sản phẩm', 'Tổng tiền', 'Trạng thái', '']">
        @foreach ($orders as $o)
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
        @endforeach
    </x-data-table>
    <x-pagination-note :shown="count($orders)" :total="412" />
@endsection
