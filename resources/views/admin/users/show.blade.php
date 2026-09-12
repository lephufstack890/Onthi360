@extends('layouts.admin')

@section('title', 'Chi tiết người dùng')
@section('page-title', 'Chi tiết người dùng')

@section('content')
    @php
        $roleNames = $roleNames ?? $userModel->roles->pluck('name')->all();
        $isSelf = $userModel->id === auth()->id();
    @endphp

    <a href="{{ route('admin.users.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại danh sách người dùng</a>

    @php
        $userStatusMessage = match (session('status')) {
            'user-created' => 'Đã tạo tài khoản mới.',
            'roles-updated' => 'Đã cập nhật vai trò, đã ghi audit log.',
            'user-updated' => 'Đã lưu thay đổi.',
            'password-updated' => 'Đã đổi mật khẩu cho người dùng này.',
            'parent-link-approved' => 'Đã xác minh liên kết phụ huynh — con.',
            'parent-link-rejected' => 'Đã từ chối/thu hồi liên kết, đã ghi lý do.',
            default => session('status') ? 'Đã lưu thay đổi.' : null,
        };
    @endphp
    @if ($userStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $userStatusMessage])
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div class="flex items-center gap-4">
            <x-admin.avatar :name="$userModel->name" size="lg" />
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">{{ $userModel->name }}</h1>
                    <x-admin.badge :tone="$userModel->status === 'active' ? 'success' : 'danger'">{{ $userModel->status === 'active' ? 'Hoạt động' : 'Tạm khóa' }}</x-admin.badge>
                </div>
                <p class="text-[13px] text-slate-500 mt-1">{{ $userModel->email }} @if($userModel->phone) · {{ $userModel->phone }} @endif</p>
            </div>
        </div>
        <a href="{{ route('admin.users.edit', $userModel->id) }}"
           class="px-4 py-2 rounded-xl border border-sky-100 bg-white text-slate-600 text-[13px] font-medium hover:border-blue-200 hover:text-blue-600 transition shrink-0">
            ✏️ Sửa thông tin
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h2 class="font-medium text-slate-700 mb-4">Vai trò (đa vai trò — 4.3)</h2>

                @if ($isSelf)
                    <p class="text-[13px] text-slate-400">Không thể tự sửa vai trò của chính mình ở đây — nhờ một Super Admin khác thao tác nếu cần đổi.</p>
                @else
                    <form method="POST" action="{{ route('admin.users.roles.update', $userModel->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="flex flex-wrap gap-2 mb-4">
                            @foreach ($availableRoles as $key => $label)
                                <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-sky-100 text-[13px]">
                                    <input type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, $roleNames, true))>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Lưu vai trò</button>
                    </form>
                @endif
            </div>

            {{-- Note họp 13/8 mục 2: "Cần có đổi mật khẩu... cho người dùng" — dùng khi
                 người dùng quên mật khẩu/mất quyền truy cập email và không tự đổi được. --}}
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h2 class="font-medium text-slate-700 mb-1">Đổi mật khẩu</h2>
                <p class="text-xs text-slate-400 mb-4">Đặt mật khẩu mới trực tiếp cho người dùng này (dùng khi họ không tự đổi được).</p>
                <form method="POST" action="{{ route('admin.users.password.update', $userModel->id) }}" class="space-y-3 max-w-sm">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="text-xs text-slate-500">Mật khẩu mới</label>
                        <input type="password" name="password" minlength="8" required
                               class="mt-1 w-full rounded-xl border border-sky-100 text-[13px] p-2">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Xác nhận mật khẩu mới</label>
                        <input type="password" name="password_confirmation" minlength="8" required
                               class="mt-1 w-full rounded-xl border border-sky-100 text-[13px] p-2">
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-xl border border-sky-100 text-slate-600 text-[13px] font-medium hover:border-blue-300 transition">Đổi mật khẩu</button>
                </form>
            </div>

            @if ($userModel->teacherProfile)
                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <h2 class="font-medium text-slate-700 mb-3">👩‍🏫 Hồ sơ giáo viên</h2>
                    <p class="text-[13px] text-slate-500 mb-2">Trạng thái duyệt: <x-admin.badge :tone="$userModel->teacherProfile->approval_status->value === 'approved' ? 'success' : ($userModel->teacherProfile->approval_status->value === 'pending' ? 'warning' : 'danger')">{{ $userModel->teacherProfile->approval_status->label() }}</x-admin.badge></p>
                    @if ($userModel->teacherProfile->subjects)
                        <p class="text-[13px] text-slate-500 mb-1">Môn dạy: {{ implode(', ', $userModel->teacherProfile->subjects) }}</p>
                    @endif
                    @if ($userModel->teacherProfile->bio)
                        <p class="text-[13px] text-slate-500">{{ $userModel->teacherProfile->bio }}</p>
                    @endif
                    <a href="{{ route('admin.teacher-approvals.show', $userModel->id) }}" class="inline-block mt-3 text-[13px] text-blue-600 font-medium">Xem/duyệt hồ sơ giáo viên ›</a>
                </div>
            @endif

            @if (in_array('student', $roleNames, true))
                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <h2 class="font-medium text-slate-700 mb-3">🎓 Hồ sơ học sinh</h2>
                    <p class="text-xs text-slate-400 mb-2">Lớp đang tham gia</p>
                    <div class="space-y-2 mb-4">
                        @forelse ($studentEnrollments as $e)
                            <div class="flex items-center justify-between px-3 py-2 rounded-xl bg-slate-50 text-[13px]">
                                <span class="text-slate-700">{{ $e->classRoom->name ?? '—' }} <span class="text-slate-400">({{ $e->classRoom->course->title ?? '' }})</span></span>
                                <x-admin.badge :tone="$e->status === 'active' ? 'success' : 'neutral'">{{ $e->status === 'active' ? 'Đang học' : $e->status }}</x-admin.badge>
                            </div>
                        @empty
                            <p class="text-[13px] text-slate-400">Chưa tham gia lớp nào.</p>
                        @endforelse
                    </div>
                    <p class="text-xs text-slate-400 mb-2">Phụ huynh liên kết</p>
                    <div class="space-y-2">
                        @forelse ($linkedParents as $link)
                            <div class="rounded-xl bg-slate-50 text-[13px] p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-700">{{ $link->parent->name ?? '—' }}</span>
                                    <x-admin.badge :tone="$link->status->value === 'verified' ? 'success' : ($link->status->value === 'pending' ? 'warning' : 'danger')">{{ $link->status->value }}</x-admin.badge>
                                </div>
                                @if ($link->status->value === 'pending')
                                    @include('admin.users._parent-link-actions', ['link' => $link])
                                @endif
                            </div>
                        @empty
                            <p class="text-[13px] text-slate-400">Chưa có phụ huynh liên kết.</p>
                        @endforelse
                    </div>
                </div>
            @endif

            @if (in_array('parent', $roleNames, true))
                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <h2 class="font-medium text-slate-700 mb-3">👨‍👩‍👧 Hồ sơ phụ huynh</h2>
                    <p class="text-xs text-slate-400 mb-2">Con đã liên kết</p>
                    <div class="space-y-2">
                        @forelse ($linkedChildren as $link)
                            <div class="rounded-xl bg-slate-50 text-[13px] p-3">
                                <div class="flex items-center justify-between">
                                    <a href="{{ route('admin.users.show', $link->student_user_id) }}" class="text-slate-700 hover:text-blue-600">{{ $link->student->name ?? '—' }}</a>
                                    <x-admin.badge :tone="$link->status->value === 'verified' ? 'success' : ($link->status->value === 'pending' ? 'warning' : 'danger')">{{ $link->status->value }}</x-admin.badge>
                                </div>
                                @if ($link->status->value === 'pending')
                                    @include('admin.users._parent-link-actions', ['link' => $link])
                                @endif
                            </div>
                        @empty
                            <p class="text-[13px] text-slate-400">Chưa liên kết con nào.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>

        <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
            <h2 class="font-medium text-slate-700 mb-4">Lịch sử thay đổi (audit log)</h2>
            <div class="space-y-3">
                @forelse ($auditLogs as $log)
                    <div class="text-[13px] border-b border-slate-100 pb-2 last:border-0">
                        <p class="text-slate-700">{{ $log->action }}</p>
                        @if ($log->reason)
                            <p class="text-xs text-slate-500 italic">"{{ $log->reason }}"</p>
                        @endif
                        <p class="text-xs text-slate-400">{{ $log->created_at?->diffForHumans() }} · {{ $log->actor->email ?? 'system' }}</p>
                    </div>
                @empty
                    <p class="text-[13px] text-slate-400">Chưa có thay đổi nào được ghi nhận.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
