@extends('layouts.admin')

@section('title', 'Thêm người dùng')
@section('page-title', 'Thêm người dùng')

@section('content')
    @php
        $availableRoles = $availableRoles ?? [];
        $regionOptions = \App\Support\VietnamProvinces::regionOptions();
        $provinceOptions = \App\Support\VietnamProvinces::options();
    @endphp

    {{-- SỬA 8/10 — đổi giao diện theo source mới (AdminUsers.jsx); tên field, route, validation, nút ẩn/hiện mật khẩu giữ nguyên. --}}
    @include('partials.admin-users-ui')

    <div class="acx-wrap">
    <a href="{{ route('admin.users.index') }}" class="acx-back">‹ Quay lại danh sách người dùng</a>

    <div class="acx-head">
        <div class="acx-head__id">
            <span class="acx-avatar"><x-lucide name="plus" /></span>
            <div>
                <h1>Thêm người dùng</h1>
                <div class="acx-head__meta"><span>Tạo tài khoản trực tiếp và gán vai trò ngay — khác với tự đăng ký công khai (chỉ chọn được học sinh/giáo viên/phụ huynh).</span></div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="acx-grid acx-grid--main">
        <div class="acx-card acx-card--white" x-data="{ showPassword: false, showPasswordConfirm: false }">
            <h2><x-lucide name="user-round" /> Thông tin tài khoản</h2>
            <form method="POST" action="{{ route('admin.users.store') }}" class="acx-form">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="name">Họ tên</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255"
                               class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255"
                               class="admin-input">
                    </div>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="phone">Số điện thoại (tùy chọn)</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone') }}" maxlength="30"
                           class="admin-input">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="province">Tỉnh/thành (tùy chọn)</label>
                        <x-ws.select id="province" name="province">
                            <option value="">— Chưa chọn —</option>
                            @foreach ($provinceOptions as $p)
                                <option value="{{ $p }}" @selected(old('province') === $p)>{{ $p }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="region">Khu vực (tùy chọn)</label>
                        <x-ws.select id="region" name="region">
                            <option value="">— Chưa chọn —</option>
                            @foreach ($regionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('region') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="password">Mật khẩu</label>
                        <div class="aux-pw">
                            <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required minlength="8"
                                   class="admin-input">
                            <button type="button" @click="showPassword = !showPassword" aria-label="Ẩn/hiện mật khẩu">
                                <span x-text="showPassword ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="password_confirmation">Nhập lại mật khẩu</label>
                        <div class="aux-pw">
                            <input :type="showPasswordConfirm ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" required minlength="8"
                                   class="admin-input">
                            <button type="button" @click="showPasswordConfirm = !showPasswordConfirm" aria-label="Ẩn/hiện mật khẩu">
                                <span x-text="showPasswordConfirm ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-2">Vai trò (có thể chọn nhiều — 4.3)</label>
                    <div class="aux-rolebox" style="margin-bottom:8px">
                        @foreach ($availableRoles as $key => $label)
                            <label>
                                <input type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, old('roles', []), true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Chọn "Giáo viên" vẫn cần Admin duyệt hồ sơ trước khi dạy thật (3.3) — có thể duyệt ngay sau khi tạo.</p>
                </div>

                <div class="acx-footer">
                    <small>Tài khoản có hiệu lực ngay sau khi tạo.</small>
                    <div class="acx-footer__btns">
                        <a href="{{ route('admin.users.index') }}" class="acx-btn">Huỷ</a>
                        <button type="submit" class="acx-btn acx-btn--primary">Tạo tài khoản</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="acx-card acx-card--mint">
            <h3><x-lucide name="sparkles" /> Cần biết</h3>
            <div class="acx-tips">
            <div class="flex items-start gap-3">
                <x-ws.icon-tile emoji="🔑" tone="sky" />
                <p style="margin:0">Tài khoản tạo ở đây có trạng thái "Hoạt động" ngay — người dùng đăng nhập được bằng email/mật khẩu vừa đặt.</p>
            </div>
            <div class="flex items-start gap-3">
                <x-ws.icon-tile emoji="🧾" tone="violet" />
                <p style="margin:0">Việc tạo tài khoản và gán vai trò được ghi vào audit log (16 mục 4).</p>
            </div>
            </div>
        </div>
    </div>
    </div>
@endsection
