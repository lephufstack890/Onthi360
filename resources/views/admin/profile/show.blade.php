@extends('layouts.admin')

@section('title', 'Hồ sơ của tôi')
@section('page-title', 'Hồ sơ của tôi')

@section('content')
    @php
        $user = $user ?? auth()->user();
    @endphp

    @if (session('status') === 'profile-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thông tin hồ sơ.'])
    @elseif (session('status') === 'password-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đổi mật khẩu thành công.'])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Thẻ hồ sơ --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden sticky top-24">
                <div class="h-16 bg-gradient-to-br from-slate-800 to-slate-600"></div>
                <div class="px-6 pb-6 text-center">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name ?? 'Admin') }}&background=1e293b&color=ffffff&size=128&bold=true"
                         alt="{{ $user->name ?? 'Admin' }}" class="w-20 h-20 rounded-full border-4 border-white shadow-md -mt-10 mx-auto">
                    <h2 class="font-semibold text-slate-800 mt-3">{{ $user->name ?? 'Admin' }}</h2>
                    <p class="text-sm text-slate-400">{{ $user->email ?? '' }}</p>
                    <div class="flex items-center justify-center gap-1.5 flex-wrap mt-3">
                        @forelse (($user->roles ?? collect()) as $role)
                            <x-status-badge tone="info">{{ $role->label ?? $role->name }}</x-status-badge>
                        @empty
                            <x-status-badge tone="neutral">Chưa gán vai trò</x-status-badge>
                        @endforelse
                    </div>
                </div>
                <div class="border-t border-slate-100 px-6 py-4">
                    <p class="text-xs text-slate-400 leading-relaxed">🔒 Mọi thay đổi hồ sơ và mật khẩu ở trang này chỉ áp dụng cho tài khoản của chính bạn.</p>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            {{-- Thông tin cá nhân --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>🙂</span> Thông tin cá nhân</h3>

                @if ($errors->hasAny(['name', 'phone']))
                    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', \Illuminate\Support\Arr::flatten($errors->only(['name', 'phone'])))])
                @endif

                <form method="POST" action="{{ route('admin.profile.update') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="name">Họ tên</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">🙂</span>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required
                                   class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-3 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="email">Email</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">📧</span>
                            <input id="email" type="email" value="{{ $user->email ?? '' }}" disabled
                                   class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-3 bg-slate-50 text-slate-400">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="phone">Số điện thoại</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">📱</span>
                            <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}"
                                   placeholder="Chưa cập nhật" class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-3 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">
                            Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>

            {{-- Đổi mật khẩu --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5" x-data="{ showCurrent: false, showNew: false, showConfirm: false }">
                <h3 class="font-medium text-slate-700 mb-1 flex items-center gap-2"><span>🔒</span> Đổi mật khẩu</h3>
                <p class="text-sm text-slate-400 mb-4">Cần nhập đúng mật khẩu hiện tại trước khi đổi.</p>

                @if ($errors->hasAny(['current_password', 'password']))
                    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', \Illuminate\Support\Arr::flatten($errors->only(['current_password', 'password'])))])
                @endif

                <form method="POST" action="{{ route('admin.profile.password') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="current_password">Mật khẩu hiện tại</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">🔒</span>
                            <input id="current_password" name="current_password" :type="showCurrent ? 'text' : 'password'" required
                                   class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-10 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                            <button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm">
                                <span x-text="showCurrent ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="password">Mật khẩu mới</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">🔒</span>
                            <input id="password" name="password" :type="showNew ? 'text' : 'password'" required minlength="8"
                                   class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-10 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                            <button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm">
                                <span x-text="showNew ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="password_confirmation">Nhập lại mật khẩu mới</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">🔒</span>
                            <input id="password_confirmation" name="password_confirmation" :type="showConfirm ? 'text' : 'password'" required minlength="8"
                                   class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-10 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                            <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm">
                                <span x-text="showConfirm ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">
                            Đổi mật khẩu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
