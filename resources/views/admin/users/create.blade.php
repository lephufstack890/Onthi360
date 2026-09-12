@extends('layouts.admin')

@section('title', 'Thêm người dùng')
@section('page-title', 'Thêm người dùng')

@section('content')
    @php
        $availableRoles = $availableRoles ?? [];
        $regionOptions = \App\Support\VietnamProvinces::regionOptions();
        $provinceOptions = \App\Support\VietnamProvinces::options();
    @endphp

    <a href="{{ route('admin.users.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại danh sách người dùng</a>

    <x-ws.page-header title="Thêm người dùng" icon="plus" subtitle="Tạo tài khoản trực tiếp và gán vai trò ngay — khác với tự đăng ký công khai (chỉ chọn được học sinh/giáo viên/phụ huynh)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6" x-data="{ showPassword: false, showPasswordConfirm: false }">
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
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
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required minlength="8"
                                   class="w-full rounded-xl border border-sky-100 text-[13px] p-2.5 pr-10 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[13px]">
                                <span x-text="showPassword ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="password_confirmation">Nhập lại mật khẩu</label>
                        <div class="relative">
                            <input :type="showPasswordConfirm ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" required minlength="8"
                                   class="w-full rounded-xl border border-sky-100 text-[13px] p-2.5 pr-10 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                            <button type="button" @click="showPasswordConfirm = !showPasswordConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[13px]">
                                <span x-text="showPasswordConfirm ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-2">Vai trò (có thể chọn nhiều — 4.3)</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($availableRoles as $key => $label)
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-sky-100 text-[13px]">
                                <input type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, old('roles', []), true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Chọn "Giáo viên" vẫn cần Admin duyệt hồ sơ trước khi dạy thật (3.3) — có thể duyệt ngay sau khi tạo.</p>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Tạo tài khoản</button>
                    <a href="{{ route('admin.users.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-sky-100 p-6 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="sparkles" class="h-4 w-4" /></span> Cần biết</h3>
            <div class="flex items-start gap-3">
                <x-ws.icon-tile emoji="🔑" tone="sky" />
                <p class="text-[13px] text-slate-500">Tài khoản tạo ở đây có trạng thái "Hoạt động" ngay — người dùng đăng nhập được bằng email/mật khẩu vừa đặt.</p>
            </div>
            <div class="flex items-start gap-3">
                <x-ws.icon-tile emoji="🧾" tone="violet" />
                <p class="text-[13px] text-slate-500">Việc tạo tài khoản và gán vai trò được ghi vào audit log (16 mục 4).</p>
            </div>
        </div>
    </div>
@endsection
