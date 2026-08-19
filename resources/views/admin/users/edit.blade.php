@extends('layouts.admin')

@section('title', 'Sửa người dùng')
@section('page-title', 'Sửa người dùng')

@section('content')
    @php
        $regionOptions = \App\Support\VietnamProvinces::regionOptions();
        $provinceOptions = \App\Support\VietnamProvinces::options();
    @endphp

    <a href="{{ route('admin.users.show', $userModel->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại chi tiết</a>

    <x-page-header title="✏️ Sửa người dùng" :subtitle="$userModel->name" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-6" x-data="{ status: '{{ old('status', $userModel->status) }}' }">
        <form method="POST" action="{{ route('admin.users.update', $userModel->id) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="name">Họ tên</label>
                <input id="name" name="name" type="text" value="{{ old('name', $userModel->name) }}" required maxlength="255"
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $userModel->email) }}" required maxlength="255"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="phone">Số điện thoại</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone', $userModel->phone) }}" maxlength="30"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="province">Tỉnh/thành</label>
                    <x-select id="province" name="province">
                        <option value="">— Chưa chọn —</option>
                        @foreach ($provinceOptions as $p)
                            <option value="{{ $p }}" @selected(old('province', $userModel->province) === $p)>{{ $p }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="region">Khu vực</label>
                    <x-select id="region" name="region">
                        <option value="">— Chưa chọn —</option>
                        @foreach ($regionOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('region', $userModel->region) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái tài khoản</label>
                <x-select id="status" name="status" x-model="status" required>
                    <option value="active" @selected(old('status', $userModel->status) === 'active')>Hoạt động</option>
                    <option value="suspended" @selected(old('status', $userModel->status) === 'suspended')>Tạm khóa</option>
                </x-select>
            </div>

            <div x-show="status === 'suspended'" x-cloak>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="reason">Lý do tạm khóa (bắt buộc, 10.4)</label>
                <textarea id="reason" name="reason" rows="3" maxlength="1000" placeholder="Nêu rõ lý do..."
                          class="w-full rounded-lg border border-slate-200 text-sm p-2.5">{{ old('reason') }}</textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu thay đổi</button>
                <a href="{{ route('admin.users.show', $userModel->id) }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
