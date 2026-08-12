{{--
  Route: admin.profile.show | Frame: ACC-01/ACC-02 áp cho khu Admin.
  Dữ liệu thật do App\Http\Controllers\Admin\ProfileController truyền vào.
--}}
@extends('layouts.admin')

@section('title', 'Hồ sơ của tôi')
@section('page-title', 'Hồ sơ của tôi')

@section('content')
    @php
        $user = $user ?? auth()->user();
    @endphp

    @if (session('status') === 'profile-updated')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 mb-6 text-sm text-emerald-700">
            Đã lưu thông tin hồ sơ.
        </div>
    @elseif (session('status') === 'password-updated')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 mb-6 text-sm text-emerald-700">
            Đã đổi mật khẩu thành công.
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-6 text-center">
            <div class="w-20 h-20 rounded-full bg-gradient-to-br from-rose-200 to-amber-100 mx-auto mb-3 flex items-center justify-center text-2xl">
                {{ mb_substr($user->name ?? 'A', 0, 1) }}
            </div>
            <h2 class="font-semibold text-slate-800">{{ $user->name ?? 'Admin' }}</h2>
            <p class="text-sm text-slate-400">{{ $user->email ?? '' }}</p>
            <p class="text-xs text-slate-400 mt-2">
                @foreach (($user->roles ?? collect()) as $role)
                    <x-status-badge tone="info">{{ $role->label ?? $role->name }}</x-status-badge>
                @endforeach
            </p>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-4">Thông tin cá nhân</h3>

                @if ($errors->hasAny(['name', 'phone']))
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 mb-4 text-sm text-rose-700">
                        @foreach ($errors->only(['name', 'phone']) as $fieldErrors)
                            @foreach ((array) $fieldErrors as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.profile.update') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm text-slate-600 mb-1" for="name">Họ tên</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}"
                               required class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1" for="email">Email</label>
                        <input id="email" type="email" value="{{ $user->email ?? '' }}" disabled
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 bg-slate-50 text-slate-400">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1" for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}"
                               placeholder="Chưa cập nhật" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">
                            Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2">Đổi mật khẩu</h3>
                <p class="text-sm text-slate-400 mb-4">Cần nhập đúng mật khẩu hiện tại trước khi đổi.</p>

                @if ($errors->hasAny(['current_password', 'password']))
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 mb-4 text-sm text-rose-700">
                        @foreach ($errors->only(['current_password', 'password']) as $fieldErrors)
                            @foreach ((array) $fieldErrors as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.profile.password') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div class="sm:col-span-2">
                        <label class="block text-sm text-slate-600 mb-1" for="current_password">Mật khẩu hiện tại</label>
                        <input id="current_password" name="current_password" type="password" required
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1" for="password">Mật khẩu mới</label>
                        <input id="password" name="password" type="password" required minlength="8"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1" for="password_confirmation">Nhập lại mật khẩu mới</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">
                            Đổi mật khẩu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
