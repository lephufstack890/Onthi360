{{--
  Route: admin.teacher-approvals.index
  Spec: 3.3 (trạng thái giáo viên: Chưa đăng ký → Chờ duyệt → Đã được duyệt → Tạm dừng/Từ chối).
  TODO controller: truyền $pending = TeacherProfile::where('status', Pending)->with('user')->paginate().
--}}
@extends('layouts.admin')

@section('title', 'Duyệt giáo viên')
@section('page-title', 'Hàng đợi duyệt giáo viên')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\TeacherApprovalController truyền vào. --}}
    @php
        $pending = $pending ?? [];
    @endphp

    <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Người dùng</a>

    <x-page-header title="👥 Hàng đợi duyệt giáo viên" subtitle="Chỉ giáo viên Đã được duyệt mới mua/kích hoạt quyền dạy và gắn học liệu riêng tư vào lớp (3.3)." />

    @if (empty($pending))
        <x-empty-state title="Không có hồ sơ chờ duyệt" description="Mọi hồ sơ giáo viên đã được xử lý." />
    @else
        <x-data-table :columns="['Họ tên', 'Email', 'Môn/chuyên môn', 'Ngày nộp', '']">
            @foreach ($pending as $p)
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-700">
                        <div class="flex items-center gap-3">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($p['name']) }}&background=fecdd3&color=be123c&size=64&bold=true"
                                 alt="{{ $p['name'] }}" class="w-8 h-8 rounded-full shrink-0">
                            <span>{{ $p['name'] }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ $p['email'] }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $p['subject'] }}</td>
                    <td class="px-4 py-3 text-slate-400">{{ $p['submitted'] }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.teacher-approvals.show', $p['id']) }}" class="text-rose-600 font-medium">Xem hồ sơ</a>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    @endif
@endsection
