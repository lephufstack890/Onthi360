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
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\AccessRightController truyền vào. --}}
    @php
        $tabs = $tabs ?? [];
        $rights = $rights ?? [];
    @endphp

    <x-page-header title="Quyền truy cập" subtitle="Quyền dạy không cấp quyền học cá nhân cho học sinh; không giới hạn class_limit khi scope = teacher_teaching (7.2)." />

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Người dùng', 'Sản phẩm', 'Phạm vi', 'Hết hạn', 'Trạng thái', '']">
        @forelse ($rights as $r)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $r['user'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $r['product'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $r['scope'] }}</td>
                <td class="px-4 py-3 text-slate-400">{{ $r['expires'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right">{{-- TODO: thu hồi (revoke) có lý do + audit log --}}<a href="#" class="text-slate-400">Chi tiết</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Chưa có quyền truy cập nào.</td></tr>
        @endforelse
    </x-data-table>
@endsection
