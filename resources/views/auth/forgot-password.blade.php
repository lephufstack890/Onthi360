@extends('layouts.auth', [
    'authTitle' => session('status') === 'reset-link-sent' ? 'Kiểm tra hộp thư' : 'Khôi phục mật khẩu',
    'authEyebrow' => session('status') === 'reset-link-sent' ? 'Đã gửi hướng dẫn' : 'Quên mật khẩu',
    'authCompact' => true,
])

@section('title', 'Quên mật khẩu')

@section('auth-content')
{{-- ═══════════════ [AUTH-03] QUÊN MẬT KHẨU ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi
     (education-main/src/components/AccessCenterModal.jsx, view "forgot" và "resetSent").

     Đây là luồng HOÀN TOÀN MỚI: trước lần sửa này hệ thống không có cách nào cho người quên
     mật khẩu tự lấy lại tài khoản. Dùng Password broker sẵn có của Laravel — xem
     App\Http\Controllers\Auth\PasswordResetController.

     Cố ý KHÔNG cho biết email có tồn tại hay không: dù nhập email nào cũng ra cùng màn "đã
     gửi". Nếu phân biệt 2 trường hợp thì trang này thành công cụ dò xem ai đã đăng ký. --}}

@if (session('status') === 'reset-link-sent')
    <div class="py-7 text-center">
        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-sky-50 text-blue-600">
            <x-lucide name="mail" class="h-8 w-8" />
        </span>
        <p class="mt-4 text-sm leading-6 text-slate-500">
            Nếu <span class="font-bold text-slate-700">{{ session('reset-email') }}</span> đã đăng ký, chúng tôi vừa gửi liên kết đặt lại mật khẩu tới hộp thư đó.
            Liên kết có hiệu lực trong 60 phút. Nếu chưa thấy thư, hãy kiểm tra mục Spam.
        </p>

        <a href="{{ route('login') }}"
           class="mx-auto mt-6 flex min-h-12 max-w-xs items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#126F91] to-[#188DB0] px-4 py-3 text-[12px] font-extrabold text-white shadow-[0_8px_18px_rgba(18,111,145,0.2)] transition hover:from-[#0F607E] hover:to-[#147D9B]">
            Quay lại đăng nhập
        </a>

        <form method="POST" action="{{ route('password.email') }}" class="mt-3">
            @csrf
            <input type="hidden" name="email" value="{{ session('reset-email') }}">
            <button type="submit" class="auth-form-action text-[11px] font-bold text-blue-600 hover:underline">Gửi lại thư hướng dẫn</button>
        </form>
    </div>
@else
    <p class="type-body mt-1.5">Nhập email bạn đã dùng để đăng ký. Chúng tôi sẽ gửi hướng dẫn đặt lại mật khẩu nếu tài khoản tồn tại.</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-4">
        @csrf
        <label for="forgot-email" class="auth-form-label block text-[11px] font-bold text-slate-700">
            Email
            <input id="forgot-email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}"
                   placeholder="minhanh@example.com"
                   class="mt-1.5 w-full min-h-12 rounded-2xl border bg-white px-4 py-3 text-[13px] font-medium text-[#183D5E] outline-none transition placeholder:text-[#8193A3] hover:border-[#B8D0DB] focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6] {{ $errors->has('email') ? 'border-rose-300' : 'border-[#D5E3E9]' }}">
        </label>
        @error('email')
            <p class="mt-1.5 flex items-start gap-1.5 text-[11px] leading-4 text-rose-600">
                <x-lucide name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0" />{{ $message }}
            </p>
        @enderror

        <button type="submit"
                class="mt-4 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#126F91] to-[#188DB0] px-4 py-3 text-[12px] font-extrabold text-white shadow-[0_8px_18px_rgba(18,111,145,0.2)] transition hover:from-[#0F607E] hover:to-[#147D9B] active:scale-[.98]">
            Gửi hướng dẫn khôi phục <x-lucide name="mail" class="h-4 w-4" />
        </button>

        <a href="{{ route('login') }}" class="auth-form-action mx-auto mt-3 flex w-fit items-center gap-1 text-[11px] font-bold text-blue-600 hover:underline">
            <x-lucide name="arrow-left" class="h-3.5 w-3.5" />Quay lại đăng nhập
        </a>
    </form>
@endif
@endsection
