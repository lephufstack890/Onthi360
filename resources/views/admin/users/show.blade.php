@extends('layouts.admin')

@section('title', 'Chi tiết người dùng')
@section('page-title', 'Chi tiết người dùng')

@section('content')
    @php
        $roleNames = $roleNames ?? $userModel->roles->pluck('name')->all();
        $isSelf = $userModel->id === auth()->id();
    @endphp


    {{--
      SỬA 8/10 (khách: "cập nhật lại UI màn người dùng theo source mới, logic giữ nguyên") — trang chi tiết theo
      phong cách AdminUsers.jsx: đầu trang avatar + huy hiệu, các khối trắng bo tròn (Vai trò, Đổi mật khẩu, Hồ sơ
      giáo viên/học sinh/phụ huynh), thẻ xanh nhạt bên phải (Tài khoản + Lịch sử thay đổi). Mọi biến, route, form
      (lưu vai trò, đổi mật khẩu, xác minh/từ chối liên kết phụ huynh) và điều kiện hiển thị GIỮ NGUYÊN.
    --}}
    @include('partials.admin-users-ui')
    @php
        $badgeOf = fn ($tone) => match ($tone) {
            'success' => 'acx-badge--ok',
            'warning' => 'acx-badge--warn',
            'danger', 'error' => 'acx-badge--off',
            default => '',
        };
    @endphp

    <div class="acx-wrap">
        <a href="{{ route('admin.users.index') }}" class="acx-back">‹ Quay lại danh sách người dùng</a>

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

        <div class="acx-head">
            <div class="acx-head__id">
                <x-ws.avatar :name="$userModel->name" size="lg" />
                <div style="min-width:0">
                    <h1>{{ $userModel->name }}</h1>
                    <div class="acx-head__meta">
                        <span class="acx-badge {{ $badgeOf($userModel->status === 'active' ? 'success' : 'danger') }}">{{ $userModel->status === 'active' ? 'Hoạt động' : 'Tạm khóa' }}</span>
                        <span>{{ $userModel->email }} @if($userModel->phone) · {{ $userModel->phone }} @endif</span>
                    </div>
                </div>
            </div>
            <div class="acx-head__actions">
                <a href="{{ route('admin.users.edit', $userModel->id) }}" class="acx-btn"><x-lucide name="pen-line" /> Sửa thông tin</a>
            </div>
        </div>

        <div class="acx-grid acx-grid--main">
            <div class="acx-stack">
                <section class="acx-card acx-card--white">
                    <h2><x-lucide name="shield-check" /> Vai trò (đa vai trò — 4.3)</h2>

                    @if ($isSelf)
                        <p class="acx-note">Không thể tự sửa vai trò của chính mình ở đây — nhờ một Super Admin khác thao tác nếu cần đổi.</p>
                    @else
                        <form method="POST" action="{{ route('admin.users.roles.update', $userModel->id) }}">
                            @csrf
                            @method('PUT')
                            <div class="aux-rolebox">
                                @foreach ($availableRoles as $key => $label)
                                    <label>
                                        <input type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, $roleNames, true))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <button type="submit" class="acx-btn acx-btn--primary">Lưu vai trò</button>
                        </form>
                    @endif
                </section>

                {{-- Note họp 13/8 mục 2: "Cần có đổi mật khẩu... cho người dùng" — dùng khi
                     người dùng quên mật khẩu/mất quyền truy cập email và không tự đổi được. --}}
                <section class="acx-card acx-card--white">
                    <h2><x-lucide name="key-round" /> Đổi mật khẩu</h2>
                    <p class="apx-hint">Đặt mật khẩu mới trực tiếp cho người dùng này (dùng khi họ không tự đổi được).</p>
                    <form method="POST" action="{{ route('admin.users.password.update', $userModel->id) }}" class="apx-fields aux-pwbox">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="apx-lbl" for="pw-new">Mật khẩu mới</label>
                            <input id="pw-new" type="password" name="password" minlength="8" required class="admin-input">
                        </div>
                        <div>
                            <label class="apx-lbl" for="pw-confirm">Xác nhận mật khẩu mới</label>
                            <input id="pw-confirm" type="password" name="password_confirmation" minlength="8" required class="admin-input">
                        </div>
                        <div><button type="submit" class="acx-btn">Đổi mật khẩu</button></div>
                    </form>
                </section>

                @if ($userModel->teacherProfile)
                    <section class="acx-card acx-card--white">
                        <h2><x-lucide name="graduation-cap" /> Hồ sơ giáo viên</h2>
                        <p class="acx-note" style="margin-bottom:8px">Trạng thái duyệt: <span class="acx-badge {{ $badgeOf($userModel->teacherProfile->approval_status->value === 'approved' ? 'success' : ($userModel->teacherProfile->approval_status->value === 'pending' ? 'warning' : 'danger')) }}">{{ $userModel->teacherProfile->approval_status->label() }}</span></p>
                        @if ($userModel->teacherProfile->subjects)
                            <p class="acx-note" style="margin-bottom:4px">Môn dạy: {{ implode(', ', $userModel->teacherProfile->subjects) }}</p>
                        @endif
                        @if ($userModel->teacherProfile->bio)
                            <p class="acx-note">{{ $userModel->teacherProfile->bio }}</p>
                        @endif
                        <a href="{{ route('admin.teacher-approvals.show', $userModel->id) }}" class="apx-more">Xem/duyệt hồ sơ giáo viên ›</a>
                    </section>
                @endif

                @if (in_array('student', $roleNames, true))
                    <section class="acx-card acx-card--white">
                        <h2><x-lucide name="user-round" /> Hồ sơ học sinh</h2>
                        <p class="aux-h">Lớp đang tham gia</p>
                        <div style="display:grid;gap:8px;margin-bottom:18px">
                            @forelse ($studentEnrollments as $e)
                                <div class="aux-sub aux-sub__row">
                                    <span><strong style="color:#1e3a5f">{{ $e->classRoom->name ?? '—' }}</strong> <span class="akx-mute">({{ $e->classRoom->course->title ?? '' }})</span></span>
                                    @php
                                        // SỬA 16/9 — từ khi có luồng "học sinh xin vào lớp, giáo viên duyệt"
                                        // (App\Services\Student\ClassRoomService::requestJoin()), cột status
                                        // còn 2 giá trị mới; trước đây chỗ này in thẳng chuỗi tiếng Anh ra màn.
                                        [$enrollLabel, $enrollTone] = match ($e->status) {
                                            'active' => ['Đang học', 'success'],
                                            'pending' => ['Chờ giáo viên duyệt', 'warning'],
                                            'rejected' => ['Bị từ chối', 'danger'],
                                            'left' => ['Đã rời lớp', 'neutral'],
                                            default => [$e->status, 'neutral'],
                                        };
                                    @endphp
                                    <span class="acx-badge {{ $badgeOf($enrollTone) }}">{{ $enrollLabel }}</span>
                                </div>
                            @empty
                                <p class="acx-note">Chưa tham gia lớp nào.</p>
                            @endforelse
                        </div>
                        <p class="aux-h">Phụ huynh liên kết</p>
                        <div style="display:grid;gap:8px">
                            @forelse ($linkedParents as $link)
                                <div class="aux-sub">
                                    <div class="aux-sub__row">
                                        <strong style="color:#1e3a5f">{{ $link->parent->name ?? '—' }}</strong>
                                        <span class="acx-badge {{ $badgeOf($link->status->value === 'verified' ? 'success' : ($link->status->value === 'pending' ? 'warning' : 'danger')) }}">{{ $link->status->value }}</span>
                                    </div>
                                    @if ($link->status->value === 'pending')
                                        @include('admin.users._parent-link-actions', ['link' => $link])
                                    @endif
                                </div>
                            @empty
                                <p class="acx-note">Chưa có phụ huynh liên kết.</p>
                            @endforelse
                        </div>
                    </section>
                @endif

                @if (in_array('parent', $roleNames, true))
                    <section class="acx-card acx-card--white">
                        <h2><x-lucide name="users" /> Hồ sơ phụ huynh</h2>
                        <p class="aux-h">Con đã liên kết</p>
                        <div style="display:grid;gap:8px">
                            @forelse ($linkedChildren as $link)
                                <div class="aux-sub">
                                    <div class="aux-sub__row">
                                        <a href="{{ route('admin.users.show', $link->student_user_id) }}" class="akx-title" style="font-size:13px">{{ $link->student->name ?? '—' }}</a>
                                        <span class="acx-badge {{ $badgeOf($link->status->value === 'verified' ? 'success' : ($link->status->value === 'pending' ? 'warning' : 'danger')) }}">{{ $link->status->value }}</span>
                                    </div>
                                    @if ($link->status->value === 'pending')
                                        @include('admin.users._parent-link-actions', ['link' => $link])
                                    @endif
                                </div>
                            @empty
                                <p class="acx-note">Chưa liên kết con nào.</p>
                            @endforelse
                        </div>
                    </section>
                @endif
            </div>

            <aside class="acx-stack">
                <div class="acx-card acx-card--mint">
                    <h3><x-lucide name="info" /> Tài khoản</h3>
                    <dl class="acx-dl">
                        <div><dt>Email</dt><dd>{{ $userModel->email }}</dd></div>
                        <div><dt>Số điện thoại</dt><dd>{{ $userModel->phone ?: '— Chưa có —' }}</dd></div>
                        <div><dt>Vai trò</dt><dd>{{ count($roleNames) ? implode(', ', array_map(fn ($r) => $availableRoles[$r] ?? $r, $roleNames)) : '— Chưa có —' }}</dd></div>
                        <div><dt>Ngày tạo</dt><dd>{{ $userModel->created_at?->format('d/m/Y H:i') }}</dd></div>
                    </dl>
                </div>

                <div class="acx-card acx-card--mint">
                    <h3><x-lucide name="history" /> Lịch sử thay đổi (audit log)</h3>
                    <div>
                        @forelse ($auditLogs as $log)
                            <div class="aux-log">
                                <p>{{ $log->action }}</p>
                                @if ($log->reason)
                                    <em>"{{ $log->reason }}"</em>
                                @endif
                                <small>{{ $log->created_at?->diffForHumans() }} · {{ $log->actor->email ?? 'system' }}</small>
                            </div>
                        @empty
                            <p class="acx-note">Chưa có thay đổi nào được ghi nhận.</p>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection
