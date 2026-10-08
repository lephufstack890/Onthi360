@extends('layouts.admin')

@section('title', 'Sửa người dùng')
@section('page-title', 'Sửa người dùng')

@section('content')
    @php
        $regionOptions = \App\Support\VietnamProvinces::regionOptions();
        $provinceOptions = \App\Support\VietnamProvinces::options();
    @endphp

    {{-- SỬA 8/10 — đổi giao diện theo source mới (AdminUsers.jsx); tên field, route, validation, ô "lý do tạm khóa" giữ nguyên. --}}
    @include('partials.admin-users-ui')

    <div class="acx-wrap">
    <a href="{{ route('admin.users.show', $userModel->id) }}" class="acx-back">‹ Quay lại chi tiết</a>

    <div class="acx-head">
        <div class="acx-head__id">
            <x-ws.avatar :name="$userModel->name" size="lg" />
            <div style="min-width:0">
                <h1>Sửa người dùng</h1>
                <div class="acx-head__meta"><span>{{ $userModel->name }}</span></div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="acx-card acx-card--white" x-data="{ status: '{{ old('status', $userModel->status) }}' }">
        <h2><x-lucide name="pen-line" /> Thông tin tài khoản</h2>
        <form method="POST" action="{{ route('admin.users.update', $userModel->id) }}" class="acx-form">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="name">Họ tên</label>
                <input id="name" name="name" type="text" value="{{ old('name', $userModel->name) }}" required maxlength="255"
                       class="admin-input">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $userModel->email) }}" required maxlength="255"
                           class="admin-input">
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="phone">Số điện thoại</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone', $userModel->phone) }}" maxlength="30"
                           class="admin-input">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="province">Tỉnh/thành</label>
                    <x-ws.select id="province" name="province">
                        <option value="">— Chưa chọn —</option>
                        @foreach ($provinceOptions as $p)
                            <option value="{{ $p }}" @selected(old('province', $userModel->province) === $p)>{{ $p }}</option>
                        @endforeach
                    </x-ws.select>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="region">Khu vực</label>
                    <x-ws.select id="region" name="region">
                        <option value="">— Chưa chọn —</option>
                        @foreach ($regionOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('region', $userModel->region) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ws.select>
                </div>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="status">Trạng thái tài khoản</label>
                <x-ws.select id="status" name="status" x-model="status" required>
                    <option value="active" @selected(old('status', $userModel->status) === 'active')>Hoạt động</option>
                    <option value="suspended" @selected(old('status', $userModel->status) === 'suspended')>Tạm khóa</option>
                </x-ws.select>
            </div>

            <div x-show="status === 'suspended'" x-cloak>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="reason">Lý do tạm khóa (bắt buộc, 10.4)</label>
                <textarea id="reason" name="reason" rows="3" maxlength="1000" placeholder="Nêu rõ lý do..."
                          class="admin-input">{{ old('reason') }}</textarea>
            </div>

            <div class="acx-footer">
                <small>Kiểm tra lại thông tin trước khi lưu.</small>
                <div class="acx-footer__btns">
                    <a href="{{ route('admin.users.show', $userModel->id) }}" class="acx-btn">Huỷ</a>
                    <button type="submit" class="acx-btn acx-btn--primary">Lưu thay đổi</button>
                </div>
            </div>
        </form>
    </div>
    </div>
@endsection
