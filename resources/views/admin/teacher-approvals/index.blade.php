@extends('layouts.admin')

@section('title', 'Duyệt giáo viên')
@section('page-title', 'Hàng đợi duyệt giáo viên')

@section('content')
    @php
        $pending = $pending ?? [];
    @endphp

    <a href="{{ route('admin.users.index') }}" class="text-[13px] text-slate-500 mb-4 inline-block">‹ Quay lại Người dùng</a>

    <x-ws.page-header title="Hàng đợi duyệt giáo viên" icon="users" subtitle="Chỉ giáo viên Đã được duyệt mới mua/kích hoạt quyền dạy và gắn học liệu riêng tư vào lớp (3.3)." />

    @if (empty($pending))
        <x-ws.empty-state title="Không có hồ sơ chờ duyệt" description="Mọi hồ sơ giáo viên đã được xử lý." />
    @else
        <x-ws.table :columns="['Họ tên', 'Email', 'Môn/chuyên môn', 'Ngày nộp', '']">
            @foreach ($pending as $p)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-700">
                        <div class="flex items-center gap-3">
                            <x-ws.avatar :name="$p['name']" size="sm" />
                            <span>{{ $p['name'] }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ $p['email'] }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $p['subject'] }}</td>
                    <td class="px-4 py-3 text-slate-400">{{ $p['submitted'] }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.teacher-approvals.show', $p['id']) }}" class="text-blue-600 font-medium">Xem hồ sơ</a>
                    </td>
                </tr>
            @endforeach
        </x-ws.table>
    @endif
@endsection
