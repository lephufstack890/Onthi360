{{--
  Route: admin.users.index | Frame: ADM-02
  Spec: 10.4 + 3.1/3.2 (vai trò & ma trận quyền).
  TODO controller: truyền $users (paginate) đã eager-load roles; $tab hiện tại.
--}}
@extends('layouts.admin')

@section('title', 'Người dùng')
@section('page-title', 'Người dùng')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\UserController truyền vào. --}}
    @php
        $tab = $tab ?? 'all';
        $tabs = $tabs ?? [];
        $users = $users ?? [];
        $total = $total ?? count($users);
    @endphp

    <x-page-header title="👥 Người dùng" subtitle="Quản lý người dùng, vai trò và trạng thái phê duyệt giáo viên (3.3).">
        <x-slot:actions>
            <a href="{{ route('admin.teacher-approvals.index') }}" class="px-4 py-2 rounded-lg border border-amber-300 text-amber-700 text-sm font-medium bg-amber-50">
                Hàng đợi duyệt giáo viên
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Tên', 'Email', 'Vai trò', 'Trạng thái', 'Ngày tạo', '']">
        @forelse ($users as $u)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">
                    <div class="flex items-center gap-3">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($u['name']) }}&background=1e293b&color=ffffff&size=64&bold=true"
                             alt="{{ $u['name'] }}" class="w-8 h-8 rounded-full shrink-0">
                        <span>{{ $u['name'] }}</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $u['email'] }}</td>
                <td class="px-4 py-3">
                    @foreach ($u['roles'] as $r)
                        <x-status-badge tone="info">{{ $r }}</x-status-badge>
                    @endforeach
                </td>
                <td class="px-4 py-3"><x-status-badge :tone="$u['tone']">{{ $u['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-slate-400">{{ $u['created'] }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.users.show', $u['id']) }}" class="text-rose-600 font-medium">Xem</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Không có người dùng nào.</td></tr>
        @endforelse
    </x-data-table>

    <x-pagination-note :shown="count($users)" :total="$total" />
@endsection
