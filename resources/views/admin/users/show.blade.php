{{--
  Route: admin.users.show | tham chiếu ADM-02
  TODO controller: truyền $user thật (với roles, teacherProfile, auditLogs).
--}}
@extends('layouts.admin')

@section('title', 'Chi tiết người dùng')
@section('page-title', 'Chi tiết người dùng')

@section('content')
    @php
        $user = [
            'id' => request()->route('user', 1),
            'name' => 'Nguyễn Văn A',
            'email' => 'teacher.a@onthi360.test',
            'roles' => ['Giáo viên'],
            'status' => 'Đã duyệt',
        ];
        $availableRoles = ['Học sinh', 'Phụ huynh', 'Giáo viên', 'Editor', 'Admin', 'Super Admin'];
    @endphp

    <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại danh sách người dùng</a>

    <x-page-header :title="$user['name']" :subtitle="$user['email']" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-4">Vai trò (đa vai trò — 4.3)</h2>
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach ($availableRoles as $r)
                        <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 text-sm">
                            <input type="checkbox" @checked(in_array($r, $user['roles']))>
                            {{ $r }}
                        </label>
                    @endforeach
                </div>
                {{-- TODO: submit gọi App\Models\User::assignRole() / gỡ role, ghi audit log --}}
                <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Lưu vai trò</button>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-4">Hồ sơ giáo viên (nếu có)</h2>
                <p class="text-sm text-slate-500">Trạng thái: <x-status-badge tone="success">{{ $user['status'] }}</x-status-badge></p>
                <a href="{{ route('admin.teacher-approvals.show', $user['id']) }}" class="inline-block mt-3 text-sm text-rose-600 font-medium">Xem chi tiết hồ sơ phê duyệt ›</a>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-4">Lịch sử thay đổi (audit log)</h2>
            {{-- TODO: bind AuditLog::where('auditable_type', User::class)->where('auditable_id', $user['id']) --}}
            <p class="text-sm text-slate-400">Chưa có dữ liệu mẫu — nối bảng audit_logs.</p>
        </div>
    </div>
@endsection
