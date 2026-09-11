@extends('layouts.auth', ['authTitle' => 'Đặt lại mật khẩu', 'authEyebrow' => 'Bảo mật tài khoản', 'authCompact' => true])

@section('title', 'Đặt lại mật khẩu')

@section('auth-content')
{{-- ═══════════════ [AUTH-04] ĐẶT LẠI MẬT KHẨU ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi
     (education-main/src/components/AccessCenterModal.jsx, view "resetPassword").

     Mở từ liên kết trong thư khôi phục; token đi kèm URL và được Password broker của Laravel
     kiểm tra lại ở máy chủ. Lưu mật khẩu mới xong sẽ đổi luôn remember_token để mọi phiên
     "ghi nhớ đăng nhập" cũ trên máy khác mất hiệu lực. --}}
<div x-data="{ showPassword: false }">
    <p class="type-body mt-1.5">Đặt mật khẩu mới cho tài khoản <span class="font-bold text-slate-700">{{ $email }}</span>.</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        @error('email')
            <p class="mb-3 flex items-start gap-1.5 rounded-2xl border border-rose-100 bg-rose-50 p-3 text-[11px] leading-4 text-rose-700">
                <x-lucide name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0" />{{ $message }}
            </p>
        @enderror

        <label for="reset-password" class="auth-form-label block text-[11px] font-bold text-slate-700">
            Mật khẩu mới
            <span class="relative block">
                <input id="reset-password" name="password" required :type="showPassword ? 'text' : 'password'"
                       autocomplete="new-password" minlength="8" placeholder="Tối thiểu 8 ký tự"
                       class="mt-1.5 w-full min-h-12 rounded-2xl border bg-white px-4 py-3 pr-12 text-[13px] font-medium text-[#183D5E] outline-none transition placeholder:text-[#8193A3] hover:border-[#B8D0DB] focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6] {{ $errors->has('password') ? 'border-rose-300' : 'border-[#D5E3E9]' }}">
                <button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                        class="absolute right-2 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-xl text-slate-400 transition hover:bg-slate-50 hover:text-slate-700">
                    <x-lucide name="eye-off" class="h-4 w-4" x-show="showPassword" x-cloak />
                    <x-lucide name="eye" class="h-4 w-4" x-show="!showPassword" />
                </button>
            </span>
        </label>
        @error('password')
            <p class="mt-1.5 text-[11px] leading-4 text-rose-600">{{ $message }}</p>
        @enderror

        <label for="reset-password2" class="auth-form-label mt-4 block text-[11px] font-bold text-slate-700">
            Nhập lại mật khẩu
            <input id="reset-password2" name="password_confirmation" required :type="showPassword ? 'text' : 'password'"
                   autocomplete="new-password" minlength="8" placeholder="Nhập lại mật khẩu mới"
                   class="mt-1.5 w-full min-h-12 rounded-2xl border border-[#D5E3E9] bg-white px-4 py-3 text-[13px] font-medium text-[#183D5E] outline-none transition placeholder:text-[#8193A3] hover:border-[#B8D0DB] focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6]">
        </label>

        <button type="submit"
                class="mt-5 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#126F91] to-[#188DB0] px-4 py-3 text-[12px] font-extrabold text-white shadow-[0_8px_18px_rgba(18,111,145,0.2)] transition hover:from-[#0F607E] hover:to-[#147D9B] active:scale-[.98]">
            Lưu mật khẩu mới <x-lucide name="check-circle" class="h-4 w-4" />
        </button>

        <a href="{{ route('login') }}" class="auth-form-action mx-auto mt-3 flex w-fit items-center gap-1 text-[11px] font-bold text-blue-600 hover:underline">
            <x-lucide name="arrow-left" class="h-3.5 w-3.5" />Quay lại đăng nhập
        </a>
    </form>
</div>
@endsection
