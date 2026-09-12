@extends('layouts.admin')

@section('title', 'Sửa người dùng')
@section('page-title', 'Sửa người dùng')

@section('content')
    @php
        $regionOptions = \App\Support\VietnamProvinces::regionOptions();
        $provinceOptions = \App\Support\VietnamProvinces::options();
    @endphp

    <a href="{{ route('admin.users.show', $userModel->id) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại chi tiết</a>

    <x-ws.page-header title="Sửa người dùng" icon="pencil" :subtitle="$userModel->name" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6" x-data="{ status: '{{ old('status', $userModel->status) }}' }">
        <form method="POST" action="{{ route('admin.users.update', $userModel->id) }}" class="space-y-4">
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

            <div class="flex gap-3 pt-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Lưu thay đổi</button>
                <a href="{{ route('admin.users.show', $userModel->id) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
