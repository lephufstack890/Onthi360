@extends('layouts.admin')

@section('title', 'Quyền truy cập')
@section('page-title', 'Quyền truy cập đã cấp')

@section('content')
    @php
        $tabs = $tabs ?? [];
        $rights = $rights ?? [];
    @endphp

    @if (session('status') === 'access-granted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã cấp quyền, đã ghi lý do.'])
    @endif

    <x-page-header title="🔐 Quyền truy cập" subtitle="Quyền dạy không cấp quyền học cá nhân cho học sinh; không giới hạn class_limit khi scope = teacher_teaching (7.2).">
        <x-slot:actions>
            <a href="{{ route('admin.access-rights.create') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Cấp quyền</a>
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Người dùng', 'Tài liệu', 'Phạm vi', 'Hết hạn', 'Trạng thái', '']">
        @forelse ($rights as $r)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $r['user'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $r['product'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $r['scope'] }}</td>
                <td class="px-4 py-3 text-slate-400">{{ $r['expires'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.access-rights.show', $r['id']) }}" class="text-rose-600 font-medium">Chi tiết</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Chưa có quyền truy cập nào.</td></tr>
        @endforelse
    </x-data-table>
@endsection
