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
            <div class="bg-white rounded-3xl border border-sky-100 overflow-hidden sticky top-24">
                <div class="h-16 bg-gradient-to-br from-slate-800 to-slate-600"></div>
                <div class="px-6 pb-6 text-center">
                    <x-admin.avatar :name="$user->name ?? 'Admin'" size="xl" class="-mt-10 mx-auto border-4 border-white shadow-md" />
                    <h2 class="font-semibold text-slate-800 mt-3">{{ $user->name ?? 'Admin' }}</h2>
                    <p class="text-[13px] text-slate-400">{{ $user->email ?? '' }}</p>
                    <div class="flex items-center justify-center gap-1.5 flex-wrap mt-3">
                        @forelse (($user->roles ?? collect()) as $role)
                            <x-admin.badge tone="info">{{ $role->label ?? $role->name }}</x-admin.badge>
                        @empty
                            <x-admin.badge tone="neutral">Chưa gán vai trò</x-admin.badge>
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
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span><x-lucide name="check-circle-2" class="h-4 w-4" /></span> Thông tin cá nhân</h3>

                @if ($errors->hasAny(['name', 'phone']))
                    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', \Illuminate\Support\Arr::flatten($errors->only(['name', 'phone'])))])
                @endif

                <form method="POST" action="{{ route('admin.profile.update') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="name">Họ tên</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[13px] text-slate-400"><x-lucide name="check-circle-2" class="h-4 w-4" /></span>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required
                                   class="w-full rounded-xl border border-sky-100 text-[13px] py-2.5 pl-9 pr-3 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="email">Email</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[13px] text-slate-400"><x-lucide name="mail" class="h-4 w-4" /></span>
                            <input id="email" type="email" value="{{ $user->email ?? '' }}" disabled
                                   class="w-full rounded-xl border border-sky-100 text-[13px] py-2.5 pl-9 pr-3 bg-slate-50 text-slate-400">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="phone">Số điện thoại</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[13px] text-slate-400"><x-lucide name="phone" class="h-4 w-4" /></span>
                            <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}"
                                   placeholder="Chưa cập nhật" class="w-full rounded-xl border border-sky-100 text-[13px] py-2.5 pl-9 pr-3 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">
                            Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>

            {{-- Đổi mật khẩu --}}
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5" x-data="{ showCurrent: false, showNew: false, showConfirm: false }">
                <h3 class="font-medium text-slate-700 mb-1 flex items-center gap-2"><span><x-lucide name="lock" class="h-4 w-4" /></span> Đổi mật khẩu</h3>
                <p class="text-[13px] text-slate-400 mb-4">Cần nhập đúng mật khẩu hiện tại trước khi đổi.</p>

                @if ($errors->hasAny(['current_password', 'password']))
                    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', \Illuminate\Support\Arr::flatten($errors->only(['current_password', 'password'])))])
                @endif

                <form method="POST" action="{{ route('admin.profile.password') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div class="sm:col-span-2">
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="current_password">Mật khẩu hiện tại</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[13px] text-slate-400"><x-lucide name="lock" class="h-4 w-4" /></span>
                            <input id="current_password" name="current_password" :type="showCurrent ? 'text' : 'password'" required
                                   class="w-full rounded-xl border border-sky-100 text-[13px] py-2.5 pl-9 pr-10 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                            <button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-[13px]">
                                <span x-text="showCurrent ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="password">Mật khẩu mới</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[13px] text-slate-400"><x-lucide name="lock" class="h-4 w-4" /></span>
                            <input id="password" name="password" :type="showNew ? 'text' : 'password'" required minlength="8"
                                   class="w-full rounded-xl border border-sky-100 text-[13px] py-2.5 pl-9 pr-10 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                            <button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-[13px]">
                                <span x-text="showNew ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="password_confirmation">Nhập lại mật khẩu mới</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[13px] text-slate-400"><x-lucide name="lock" class="h-4 w-4" /></span>
                            <input id="password_confirmation" name="password_confirmation" :type="showConfirm ? 'text' : 'password'" required minlength="8"
                                   class="w-full rounded-xl border border-sky-100 text-[13px] py-2.5 pl-9 pr-10 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                            <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-[13px]">
                                <span x-text="showConfirm ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">
                            Đổi mật khẩu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
