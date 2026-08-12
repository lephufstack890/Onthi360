{{--
  Route: admin.users.index | Frame: ADM-02
  Spec: 10.4 + 3.1/3.2 (vai trò & ma trận quyền).
  TODO controller: truyền $users (paginate) đã eager-load roles; $tab hiện tại.
--}}
@extends('layouts.admin')

@section('title', 'Người dùng')
@section('page-title', 'Người dùng')

@section('content')
    @php
        $tab = request('tab', 'all');
        $tabs = [
            ['label' => 'Tất cả', 'href' => route('admin.users.index'), 'active' => $tab === 'all', 'count' => 1284],
            ['label' => 'Học sinh', 'href' => route('admin.users.index', ['tab' => 'student']), 'active' => $tab === 'student', 'count' => 980],
            ['label' => 'Giáo viên', 'href' => route('admin.users.index', ['tab' => 'teacher']), 'active' => $tab === 'teacher', 'count' => 214],
            ['label' => 'Phụ huynh', 'href' => route('admin.users.index', ['tab' => 'parent']), 'active' => $tab === 'parent', 'count' => 76],
            ['label' => 'Admin/Editor', 'href' => route('admin.users.index', ['tab' => 'staff']), 'active' => $tab === 'staff', 'count' => 14],
        ];

        $users = [
            ['id' => 1, 'name' => 'Nguyễn Văn A', 'email' => 'teacher.a@onthi360.test', 'roles' => ['Giáo viên'], 'status' => 'Đã duyệt', 'tone' => 'success', 'created' => '02/03/2026'],
            ['id' => 2, 'name' => 'Trần Thị B', 'email' => 'student.b@onthi360.test', 'roles' => ['Học sinh'], 'status' => 'Hoạt động', 'tone' => 'success', 'created' => '15/01/2026'],
            ['id' => 3, 'name' => 'Lê Văn C', 'email' => 'teacher.c@onthi360.test', 'roles' => ['Giáo viên'], 'status' => 'Chờ duyệt', 'tone' => 'warning', 'created' => '10/08/2026'],
            ['id' => 4, 'name' => 'Phạm Thị D', 'email' => 'parent.d@onthi360.test', 'roles' => ['Phụ huynh'], 'status' => 'Hoạt động', 'tone' => 'success', 'created' => '20/02/2026'],
            ['id' => 5, 'name' => 'Hoàng Văn E', 'email' => 'editor.e@onthi360.test', 'roles' => ['Editor'], 'status' => 'Hoạt động', 'tone' => 'success', 'created' => '01/01/2026'],
        ];
    @endphp

    <x-page-header title="Người dùng" subtitle="Quản lý người dùng, vai trò và trạng thái phê duyệt giáo viên (3.3).">
        <x-slot:actions>
            <a href="{{ route('admin.teacher-approvals.index') }}" class="px-4 py-2 rounded-lg border border-amber-300 text-amber-700 text-sm font-medium bg-amber-50">
                Hàng đợi duyệt giáo viên
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Tên', 'Email', 'Vai trò', 'Trạng thái', 'Ngày tạo', '']">
        @foreach ($users as $u)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $u['name'] }}</td>
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
        @endforeach
    </x-data-table>

    <x-pagination-note :shown="5" :total="1284" />
@endsection
