{{--
  Route: admin.activation-codes.index
  Spec: 7.4 — mã sai scope không tự chuyển đổi (mã quyền dạy không kích hoạt thành quyền học sinh).
  TODO controller: truyền $codes (paginate) với filter trạng thái.
--}}
@extends('layouts.admin')

@section('title', 'Mã kích hoạt')
@section('page-title', 'Mã kích hoạt')

@section('content')
    @php
        $codes = [
            ['code' => 'OT360-8F3K-2Q1Z', 'order' => 1039, 'scope' => 'Học cá nhân', 'status' => 'Đã dùng', 'tone' => 'success'],
            ['code' => 'OT360-7A2L-9X4P', 'order' => 1038, 'scope' => 'Dùng để dạy', 'status' => 'Chưa dùng', 'tone' => 'neutral'],
            ['code' => 'OT360-1B9M-3Y7R', 'order' => 1030, 'scope' => 'Học cá nhân', 'status' => 'Hết hạn', 'tone' => 'danger'],
        ];
    @endphp

    <x-page-header title="Mã kích hoạt" subtitle="Thời hạn quyền bắt đầu tại thời điểm kích hoạt mã hợp lệ, không phải lúc đặt đơn (7.4)." />

    <x-data-table :columns="['Mã', 'Đơn liên quan', 'Phạm vi', 'Trạng thái', '']">
        @foreach ($codes as $c)
            <tr>
                <td class="px-4 py-3 font-mono text-slate-700">{{ $c['code'] }}</td>
                <td class="px-4 py-3 text-slate-500"><a href="{{ route('admin.orders.show', $c['order']) }}" class="text-rose-600">#OD-{{ $c['order'] }}</a></td>
                <td class="px-4 py-3 text-slate-500">{{ $c['scope'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$c['tone']">{{ $c['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right"><a href="#" class="text-slate-400">Chi tiết</a></td>
            </tr>
        @endforeach
    </x-data-table>
@endsection
