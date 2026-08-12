{{--
  Route: admin.users.show | tham chiếu ADM-02
  Dữ liệu thật do App\Http\Controllers\Admin\UserController truyền vào.
--}}
@extends('layouts.admin')

@section('title', 'Chi tiết người dùng')
@section('page-title', 'Chi tiết người dùng')

@section('content')
    @php
        $userRoleNames = $userModel->roles->pluck('name')->all();
    @endphp

    <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại danh sách người dùng</a>

    <x-page-header :title="$userModel->name" :subtitle="$userModel->email" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-4">Vai trò (đa vai trò — 4.3)</h2>
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach ($availableRoles as $key => $label)
                        <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 text-sm">
                            <input type="checkbox" @checked(in_array($key, $userRoleNames))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                {{-- TODO: submit gọi App\Models\User::assignRole() / gỡ role, ghi audit log --}}
                <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Lưu vai trò</button>
            </div>

            @if ($userModel->teacherProfile)
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-4">Hồ sơ giáo viên</h2>
                    <p class="text-sm text-slate-500">Trạng thái: <x-status-badge tone="success">{{ $userModel->teacherProfile->approval_status->label() }}</x-status-badge></p>
                    <a href="{{ route('admin.teacher-approvals.show', $userModel->id) }}" class="inline-block mt-3 text-sm text-rose-600 font-medium">Xem chi tiết hồ sơ phê duyệt ›</a>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-4">Lịch sử thay đổi (audit log)</h2>
            <div class="space-y-3">
                @forelse ($auditLogs as $log)
                    <div class="text-sm">
                        <p class="text-slate-700">{{ $log->action }}</p>
                        <p class="text-xs text-slate-400">{{ $log->created_at?->diffForHumans() }} · {{ $log->actor->email ?? 'system' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">Chưa có thay đổi nào được ghi nhận.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
