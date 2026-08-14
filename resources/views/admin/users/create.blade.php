{{--
  Route: admin.users.create / admin.users.store
  Spec: ADM-02 + 4.3 (đa vai trò — chọn nhiều vai trò ngay lúc tạo). Chọn vai trò "teacher"
  vẫn tạo hồ sơ giáo viên ở trạng thái "Chờ duyệt" (3.3, xem UserService::store()) — không có
  lối tắt bỏ qua bước duyệt chỉ vì tài khoản do admin tạo trực tiếp. Tỉnh thành/khu vực (note
  họp 13/8, mục 2: "để quảng cáo cho giáo viên") tùy chọn, có thể bổ sung sau ở trang Sửa.
  Dữ liệu thật ($availableRoles) do UserController::create() truyền vào qua
  UserService::createFormData().
--}}
@extends('layouts.admin')

@section('title', 'Thêm người dùng')
@section('page-title', 'Thêm người dùng')

@section('content')
    @php
        $availableRoles = $availableRoles ?? [];
        $regionOptions = \App\Support\VietnamProvinces::regionOptions();
        $provinceOptions = \App\Support\VietnamProvinces::options();
    @endphp

    <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại danh sách người dùng</a>

    <x-page-header title="➕ Thêm người dùng" subtitle="Tạo tài khoản trực tiếp và gán vai trò ngay — khác với tự đăng ký công khai (chỉ chọn được học sinh/giáo viên/phụ huynh)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6" x-data="{ showPassword: false, showPasswordConfirm: false }">
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="name">Họ tên</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="phone">Số điện thoại (tùy chọn)</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone') }}" maxlength="30"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="province">Tỉnh/thành (tùy chọn)</label>
                        <select id="province" name="province" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                            <option value="">— Chưa chọn —</option>
                            @foreach ($provinceOptions as $p)
                                <option value="{{ $p }}" @selected(old('province') === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="region">Khu vực (tùy chọn)</label>
                        <select id="region" name="region" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                            <option value="">— Chưa chọn —</option>
                            @foreach ($regionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('region') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="password">Mật khẩu</label>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required minlength="8"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5 pr-10 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">
                                <span x-text="showPassword ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="password_confirmation">Nhập lại mật khẩu</label>
                        <div class="relative">
                            <input :type="showPasswordConfirm ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" required minlength="8"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5 pr-10 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                            <button type="button" @click="showPasswordConfirm = !showPasswordConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">
                                <span x-text="showPasswordConfirm ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Vai trò (có thể chọn nhiều — 4.3)</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($availableRoles as $key => $label)
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 text-sm">
                                <input type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, old('roles', []), true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Chọn "Giáo viên" vẫn cần Admin duyệt hồ sơ trước khi dạy thật (3.3) — có thể duyệt ngay sau khi tạo.</p>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Tạo tài khoản</button>
                    <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>💡</span> Cần biết</h3>
            <div class="flex items-start gap-3">
                <x-icon-tile emoji="🔑" tone="sky" />
                <p class="text-sm text-slate-500">Tài khoản tạo ở đây có trạng thái "Hoạt động" ngay — người dùng đăng nhập được bằng email/mật khẩu vừa đặt.</p>
            </div>
            <div class="flex items-start gap-3">
                <x-icon-tile emoji="🧾" tone="violet" />
                <p class="text-sm text-slate-500">Việc tạo tài khoản và gán vai trò được ghi vào audit log (16 mục 4).</p>
            </div>
        </div>
    </div>
@endsection
