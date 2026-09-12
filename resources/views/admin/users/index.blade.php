@extends('layouts.admin')

@section('title', 'Người dùng')
@section('page-title', 'Người dùng')

@section('content')
    @php
        $tab = $tab ?? 'all';
        $tabs = $tabs ?? [];
        $users = $users ?? [];
        $total = $total ?? count($users);
    @endphp

    @if (session('status') === 'user-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo tài khoản mới.'])
    @endif

    <x-ws.page-header title="Người dùng" icon="users" subtitle="Quản lý người dùng, vai trò và trạng thái phê duyệt giáo viên (3.3).">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Thêm người dùng</a>
            <a href="{{ route('admin.teacher-approvals.index') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-white/35 bg-white/10 px-4 py-2 text-xs font-bold text-white backdrop-blur-sm transition-colors hover:bg-white/20">
                Hàng đợi duyệt giáo viên
            </a>
        </x-slot:actions>
    </x-ws.page-header>

    <x-ws.tabs :tabs="$tabs" />

    <x-ws.table :columns="['Tên', 'Email', 'Vai trò', 'Trạng thái', 'Ngày tạo', '']">
        @forelse ($users as $u)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">
                    <div class="flex items-center gap-3">
                        <x-ws.avatar :name="$u['name']" size="sm" />
                        <span>{{ $u['name'] }}</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $u['email'] }}</td>
                <td class="px-4 py-3">
                    @foreach ($u['roles'] as $r)
                        <x-ws.badge tone="info">{{ $r }}</x-ws.badge>
                    @endforeach
                </td>
                <td class="px-4 py-3"><x-ws.badge :tone="$u['tone']">{{ $u['status'] }}</x-ws.badge></td>
                <td class="px-4 py-3 text-slate-400">{{ $u['created'] }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.users.show', $u['id']) }}" class="text-blue-600 font-medium">Xem</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Không có người dùng nào.</td></tr>
        @endforelse
    </x-ws.table>

    <x-ws.pagination-note :shown="count($users)" :total="$total" />
@endsection
