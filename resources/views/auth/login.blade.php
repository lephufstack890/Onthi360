@extends('layouts.auth', ['authTitle' => 'Đăng nhập', 'authEyebrow' => 'Tài khoản Ôn Thi 360'])

@section('title', 'Đăng nhập')

@section('auth-content')
{{-- ═══════════════ [AUTH-01] FORM ĐĂNG NHẬP ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi
     (education-main/src/components/AccessCenterModal.jsx, view "auth").

     Logic thật đi kèm:
       · 2 tab Email / Số điện thoại  -> cùng gửi lên trường "identifier";
         AuthService::attemptByIdentifier() tự nhận dạng theo nội dung đã gõ nên gõ nhầm tab
         vẫn đăng nhập được (cột users.phone đã có sẵn, unique).
       · Ghi nhớ đăng nhập            -> Auth::attempt($credentials, $remember) đã hỗ trợ sẵn.
       · Quên mật khẩu                -> route password.request (luồng MỚI thêm lần này).
       · Tài khoản bị khoá            -> AuthController chặn và báo rõ lý do.

     KHÁC bản mẫu: bỏ nút "Tiếp tục với Google" vì hệ thống chưa cấu hình đăng nhập mạng xã
     hội — để lại một nút bấm không chạy thì tệ hơn là không có. Khi nào gắn OAuth, thêm lại
     đúng chỗ này (ngay dưới đường kẻ "hoặc"). --}}
<div x-data="onthiLoginForm()">

    <p class="mt-1.5 type-body leading-5 text-[#536D86]">Lưu tiến độ và tiếp tục học tập trên mọi thiết bị.</p>

    <div class="mt-1.5 flex items-center gap-1.5 text-[11px] leading-4 text-[#71869A]">
        <x-lucide name="shield-check" class="h-3.5 w-3.5 shrink-0 text-[#2D7FA3]" />
        <span>Dữ liệu được lưu an toàn theo vai trò của bạn.</span>
    </div>

    {{-- Báo đặt lại mật khẩu thành công (chuyển về từ màn đặt lại) --}}
    @if (session('status') === 'password-reset-success')
        <div class="mt-3 flex items-start gap-2 rounded-2xl border border-emerald-100 bg-emerald-50 p-3 text-[11px] leading-4 text-emerald-800">
            <x-lucide name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
            <span>Mật khẩu mới đã được lưu. Bạn đăng nhập lại bằng mật khẩu vừa đặt nhé.</span>
        </div>
    @endif

    {{-- Tab chọn cách đăng nhập --}}
    <div role="tablist" aria-label="Phương thức đăng nhập" class="mt-4 grid grid-cols-2 gap-1 rounded-2xl border border-[#E4EFF3] bg-[#F3F7F9] p-1">
        <button type="button" role="tab" :aria-selected="method === 'email'" @click="setMethod('email')"
                class="flex min-h-10 items-center justify-center gap-1.5 rounded-xl text-xs font-bold transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-200"
                :class="method === 'email' ? 'bg-white text-[#216F8E] shadow-[0_2px_7px_rgba(64,105,125,0.1)]' : 'text-slate-500 hover:text-slate-700'">
            <x-lucide name="mail" class="h-3.5 w-3.5" />Email
        </button>
        <button type="button" role="tab" :aria-selected="method === 'phone'" @click="setMethod('phone')"
                class="flex min-h-10 items-center justify-center gap-1.5 rounded-xl text-xs font-bold transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-200"
                :class="method === 'phone' ? 'bg-white text-[#216F8E] shadow-[0_2px_7px_rgba(64,105,125,0.1)]' : 'text-slate-500 hover:text-slate-700'">
            <x-lucide name="phone" class="h-3.5 w-3.5" />Số điện thoại
        </button>
    </div>

    <form method="POST" action="{{ route('login') }}" class="mt-3">
        @csrf

        <label for="auth-contact" class="auth-form-label block text-[11px] font-bold text-slate-700">
            <span x-text="method === 'email' ? 'Email' : 'Số điện thoại'">Email</span>
            <input id="auth-contact" name="identifier" required value="{{ old('identifier') }}"
                   :autocomplete="method === 'email' ? 'email' : 'tel'"
                   :inputmode="method === 'email' ? 'email' : 'tel'"
                   :placeholder="method === 'email' ? 'minhanh@example.com' : '098 123 4567'"
                   class="mt-1.5 w-full min-h-12 rounded-2xl border bg-white px-4 py-3 text-[13px] font-medium text-[#183D5E] outline-none transition placeholder:text-[#8193A3] hover:border-[#B8D0DB] focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6] {{ $errors->has('identifier') ? 'border-rose-300' : 'border-[#D5E3E9]' }}">
        </label>
        @error('identifier')
            <p class="mt-1.5 flex items-start gap-1.5 text-[11px] leading-4 text-rose-600">
                <x-lucide name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0" />{{ $message }}
            </p>
        @enderror

        <label for="auth-password" class="auth-form-label mt-3 block text-[11px] font-bold text-slate-700">
            Mật khẩu
            <span class="relative block">
                <input id="auth-password" name="password" required autocomplete="current-password"
                       :type="showPassword ? 'text' : 'password'" minlength="6" placeholder="Tối thiểu 6 ký tự"
                       class="mt-1.5 w-full min-h-12 rounded-2xl border bg-white px-4 py-3 pr-12 text-[13px] font-medium text-[#183D5E] outline-none transition placeholder:text-[#8193A3] hover:border-[#B8D0DB] focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6] {{ $errors->has('password') ? 'border-rose-300' : 'border-[#D5E3E9]' }}">
                <button type="button" @click="showPassword = !showPassword"
                        :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                        class="absolute right-2 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-xl text-slate-400 transition hover:bg-slate-50 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-200">
                    <x-lucide name="eye-off" class="h-4 w-4" x-show="showPassword" x-cloak />
                    <x-lucide name="eye" class="h-4 w-4" x-show="!showPassword" />
                </button>
            </span>
        </label>
        @error('password')
            <p class="mt-1.5 text-[11px] leading-4 text-rose-600">{{ $message }}</p>
        @enderror

        <div class="mt-2.5 flex items-center justify-between gap-3">
            <label class="auth-form-helper flex items-center gap-2 text-[11px] text-slate-500">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                       class="h-4 w-4 rounded accent-[#126F91]">Ghi nhớ đăng nhập
            </label>
            <a href="{{ route('password.request') }}"
               class="auth-form-action text-[11px] font-bold text-[#126F91] hover:text-[#0F607E] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-200">Quên mật khẩu?</a>
        </div>

        <button type="submit"
                class="mt-4 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#126F91] to-[#188DB0] px-4 py-3 text-[12px] font-extrabold text-white shadow-[0_8px_18px_rgba(18,111,145,0.2)] transition hover:from-[#0F607E] hover:to-[#147D9B] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#CBEAF1] active:scale-[.98]">
            Đăng nhập <x-lucide name="arrow-right" class="h-4 w-4" />
        </button>
    </form>

    <div class="my-3 flex items-center gap-3 text-[10px] font-semibold text-slate-400 before:h-px before:flex-1 before:bg-slate-100 after:h-px after:flex-1 after:bg-slate-100">hoặc</div>

    <a href="{{ route('practice.index') }}"
       class="flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl border border-[#D5E3E9] bg-white px-4 py-3 text-xs font-bold text-[#183D5E] transition hover:border-[#B8D0DB] hover:bg-[#F8FCFD] focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-100">
        <x-lucide name="code-2" class="h-4 w-4 text-[#126F91]" />Luyện tập thử không cần tài khoản
    </a>

    <p class="auth-form-helper mt-3 text-center text-[11px] text-slate-500">
        Chưa có tài khoản?
        <a href="{{ route('register') }}" class="auth-form-action font-black text-[#126F91] hover:text-[#0F607E] hover:underline">Tạo tài khoản miễn phí</a>
    </p>
</div>
@endsection

@push('scripts')
<script>
    function onthiLoginForm() {
        return {
            // Bản mẫu dùng useState cho tab và ẩn/hiện mật khẩu.
            method: 'email',
            showPassword: false,

            setMethod(value) {
                this.method = value;
                // Đổi tab thì đưa con trỏ về ô nhập cho liền tay.
                this.$nextTick(() => document.getElementById('auth-contact')?.focus());
            },
        };
    }
</script>
@endpush
