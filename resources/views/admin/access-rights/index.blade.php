{{--
  Route: admin.access-rights.index
  Spec: 7.1–7.5 (quyền học cá nhân / quyền dạy đa lớp).
  Bất biến (docs/ARCHITECTURE.md mục 4): AccessRight chỉ tạo ở
  OrderActivationService::activate(); class_limit của teacher_teaching
  luôn null.
  TODO controller: truyền $rights (paginate) với filter scope/status.
--}}
@extends('layouts.admin')

@section('title', 'Quyền truy cập')
@section('page-title', 'Quyền truy cập đã cấp')

@section('content')
    @php
        $tabs = [
            ['label' => 'Sản phẩm', 'href' => route('admin.products.index'), 'active' => false, 'count' => 342],
            ['label' => 'Quyền đã cấp', 'href' => route('admin.access-rights.index'), 'active' => true, 'count' => 5210],
        ];
        $rights = [
            ['id' => 501, 'user' => 'Trần Thị B', 'product' => 'Sách: Ôn thi Tin học 10', 'scope' => 'Học cá nhân', 'expires' => '30/06/2027', 'status' => 'Hiệu lực', 'tone' => 'success'],
            ['id' => 502, 'user' => 'Nguyễn Văn A', 'product' => 'Chuyên đề: Cấu trúc dữ liệu nâng cao', 'scope' => 'Dùng để dạy (mọi lớp phụ trách)', 'expires' => '18/08/2026', 'status' => 'Sắp hết hạn', 'tone' => 'warning'],
            ['id' => 503, 'user' => 'Lê Văn C', 'product' => 'Đề thi thử HSG Tin 11', 'scope' => 'Dùng để dạy (mọi lớp phụ trách)', 'expires' => '01/01/2026', 'status' => 'Hết hạn', 'tone' => 'danger'],
        ];
    @endphp

    <x-page-header title="Quyền truy cập" subtitle="Quyền dạy không cấp quyền học cá nhân cho học sinh; không giới hạn class_limit khi scope = teacher_teaching (7.2)." />

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Người dùng', 'Sản phẩm', 'Phạm vi', 'Hết hạn', 'Trạng thái', '']">
        @foreach ($rights as $r)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $r['user'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $r['product'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $r['scope'] }}</td>
                <td class="px-4 py-3 text-slate-400">{{ $r['expires'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right">{{-- TODO: thu hồi (revoke) có lý do + audit log --}}<a href="#" class="text-slate-400">Chi tiết</a></td>
            </tr>
        @endforeach
    </x-data-table>
@endsection
