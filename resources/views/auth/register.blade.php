@extends('layouts.auth', ['authTitle' => 'Tạo tài khoản mới', 'authEyebrow' => 'Đăng ký'])

@section('title', 'Đăng ký')

@section('auth-content')
{{-- ═══════════════ [AUTH-02] ĐĂNG KÝ 3 BƯỚC ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi
     (education-main/src/components/AccessCenterModal.jsx, view "register").

     Logic thật đi kèm (bản mẫu chỉ mô phỏng ở trình duyệt):
       · Bước 1 Thông tin  — kiểm tra ngay tại chỗ, CHƯA tạo tài khoản.
       · Bước 2 Xác minh   — mã 6 số THẬT: sinh ngẫu nhiên, băm, hạn 10 phút, giới hạn số lần
                             nhập sai, gửi lại có thời gian chờ (App\Services\Auth\RegistrationService).
       · Bước 3 Vai trò    — mới thực sự tạo tài khoản, gán vai trò, giáo viên thì tạo hồ sơ
                             ở trạng thái "Chờ duyệt" (3.3).

     Vì sao bước 1 chưa tạo tài khoản: người bỏ dở giữa chừng sẽ để lại tài khoản chưa có vai
     trò và chiếm mất email đó. Toàn bộ thông tin nằm ở bảng tạm registration_verifications
     cho tới khi hoàn tất.

     KHI CHƯA CẤU HÌNH GỬI THƯ: dự án đang để MAIL_MAILER=log nên thư không tới được hộp thư
     người dùng. Lúc đó bước 2 vẫn hiện nhưng cho đi tiếp và nói rõ lý do — nếu bắt buộc nhập
     mã thì sẽ không ai đăng ký được nữa. Đặt MAIL_MAILER=smtp là bước xác minh tự bật. --}}
@php
    $verificationEnabled = $verificationEnabled ?? false;
    $resendCooldown = $resendCooldown ?? 60;

    $registerRoles = [
        ['id' => 'student', 'label' => 'Học sinh', 'note' => 'Theo lộ trình, luyện tập và lưu kết quả', 'icon' => 'book-open'],
        ['id' => 'parent', 'label' => 'Phụ huynh', 'note' => 'Theo dõi con sau khi xác minh liên kết', 'icon' => 'users'],
        ['id' => 'teacher', 'label' => 'Giáo viên', 'note' => 'Tạo hồ sơ giảng dạy và gửi yêu cầu phê duyệt', 'icon' => 'badge-check'],
    ];

    $registerState = [
        'verificationEnabled' => (bool) $verificationEnabled,
        'resendCooldown' => (int) $resendCooldown,
        'sendCodeUrl' => route('register.sendCode'),
        'resendCodeUrl' => route('register.resendCode'),
        'verifyCodeUrl' => route('register.verifyCode'),
        // Quay lại đúng bước đang dở khi máy chủ trả về lỗi kiểm tra ở bước cuối.
        'initialStep' => $errors->any() ? 3 : 1,
        'oldName' => old('name', ''),
        'oldEmail' => old('email', ''),
        'oldPhone' => old('phone', ''),
        'oldRole' => old('role', 'student'),
    ];
@endphp

<div x-data="onthiRegisterWizard({{ Js::from($registerState) }})">

    {{-- Tiêu đề đổi theo bước, đúng như bản mẫu --}}
    <p class="sr-only" aria-live="polite" x-text="'Bước ' + step + ' trên 3'"></p>

    {{-- Thanh tiến trình 3 bước --}}
    <div class="mt-4 flex items-center gap-2">
        @for ($n = 1; $n <= 3; $n++)
            <span class="grid h-6 w-6 place-items-center rounded-full text-[10px] font-black transition-colors"
                  :class="{{ $n }} <= step ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-400'">
                <span x-show="{{ $n }} < step" x-cloak>✓</span>
                <span x-show="{{ $n }} >= step">{{ $n }}</span>
            </span>
            @if ($n < 3)
                <span class="h-1 flex-1 rounded-full transition-colors" :class="{{ $n }} < step ? 'bg-blue-500' : 'bg-slate-100'"></span>
            @endif
        @endfor
    </div>

    {{-- Lỗi máy chủ trả về ở bước cuối --}}
    @if ($errors->any())
        <div class="mt-3 rounded-2xl border border-rose-100 bg-rose-50 p-3">
            <p class="flex items-center gap-1.5 text-[11px] font-bold text-rose-700">
                <x-lucide name="info" class="h-3.5 w-3.5 shrink-0" />Chưa tạo được tài khoản
            </p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-[11px] leading-4 text-rose-600">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ══════ BƯỚC 1 — THÔNG TIN TÀI KHOẢN ══════ --}}
    <div x-show="step === 1" class="mt-3">
        <label for="reg-name" class="auth-form-label block text-[11px] font-bold text-slate-700">
            Họ và tên
            <input id="reg-name" x-model="form.name" required autocomplete="name" placeholder="Nguyễn Minh Anh"
                   class="mt-1.5 w-full min-h-12 rounded-2xl border border-[#D5E3E9] bg-white px-4 py-3 text-[13px] font-medium text-[#183D5E] outline-none transition placeholder:text-[#8193A3] hover:border-[#B8D0DB] focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6]">
        </label>
        <p x-show="fieldErrors.name" x-cloak class="mt-1.5 text-[11px] leading-4 text-rose-600" x-text="fieldErrors.name"></p>

        {{-- Tab Email / Số điện thoại --}}
        <div role="tablist" aria-label="Phương thức đăng ký" class="mt-4 grid grid-cols-2 gap-1 rounded-2xl border border-[#E4EFF3] bg-[#F3F7F9] p-1">
            <button type="button" role="tab" :aria-selected="method === 'email'" @click="method = 'email'"
                    class="flex min-h-10 items-center justify-center gap-1.5 rounded-xl text-xs font-bold transition-all"
                    :class="method === 'email' ? 'bg-white text-[#216F8E] shadow-[0_2px_7px_rgba(64,105,125,0.1)]' : 'text-slate-500 hover:text-slate-700'">
                <x-lucide name="mail" class="h-3.5 w-3.5" />Email
            </button>
            <button type="button" role="tab" :aria-selected="method === 'phone'" @click="method = 'phone'"
                    class="flex min-h-10 items-center justify-center gap-1.5 rounded-xl text-xs font-bold transition-all"
                    :class="method === 'phone' ? 'bg-white text-[#216F8E] shadow-[0_2px_7px_rgba(64,105,125,0.1)]' : 'text-slate-500 hover:text-slate-700'">
                <x-lucide name="phone" class="h-3.5 w-3.5" />Số điện thoại
            </button>
        </div>

        {{-- Email luôn bắt buộc: đây là kênh nhận mã xác minh và khôi phục mật khẩu.
             Số điện thoại là tuỳ chọn, nhưng đã nhập thì đăng nhập được bằng số đó. --}}
        <div class="mt-2.5">
            <label for="reg-email" class="auth-form-label block text-[11px] font-bold text-slate-700">
                Email
                <input id="reg-email" x-model="form.email" required type="email" autocomplete="email" inputmode="email" placeholder="minhanh@example.com"
                       class="mt-1.5 w-full min-h-12 rounded-2xl border border-[#D5E3E9] bg-white px-4 py-3 text-[13px] font-medium text-[#183D5E] outline-none transition placeholder:text-[#8193A3] hover:border-[#B8D0DB] focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6]">
            </label>
            <p x-show="fieldErrors.email" x-cloak class="mt-1.5 text-[11px] leading-4 text-rose-600" x-text="fieldErrors.email"></p>
        </div>

        <div class="mt-2.5" x-show="method === 'phone'" x-cloak>
            <label for="reg-phone" class="auth-form-label block text-[11px] font-bold text-slate-700">
                Số điện thoại
                <input id="reg-phone" x-model="form.phone" autocomplete="tel" inputmode="tel" placeholder="098 123 4567"
                       class="mt-1.5 w-full min-h-12 rounded-2xl border border-[#D5E3E9] bg-white px-4 py-3 text-[13px] font-medium text-[#183D5E] outline-none transition placeholder:text-[#8193A3] hover:border-[#B8D0DB] focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6]">
            </label>
            <p x-show="fieldErrors.phone" x-cloak class="mt-1.5 text-[11px] leading-4 text-rose-600" x-text="fieldErrors.phone"></p>
            <p class="mt-1 text-[11px] leading-4 text-slate-400">Nhập số này để đăng nhập bằng số điện thoại. Có thể bỏ trống.</p>
        </div>

        <label for="reg-password" class="auth-form-label mt-3 block text-[11px] font-bold text-slate-700">
            Mật khẩu
            <span class="relative block">
                <input id="reg-password" x-model="form.password" required :type="showPassword ? 'text' : 'password'"
                       autocomplete="new-password" minlength="8" placeholder="Tối thiểu 8 ký tự"
                       class="mt-1.5 w-full min-h-12 rounded-2xl border border-[#D5E3E9] bg-white px-4 py-3 pr-12 text-[13px] font-medium text-[#183D5E] outline-none transition placeholder:text-[#8193A3] hover:border-[#B8D0DB] focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6]">
                <button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                        class="absolute right-2 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-xl text-slate-400 transition hover:bg-slate-50 hover:text-slate-700">
                    <x-lucide name="eye-off" class="h-4 w-4" x-show="showPassword" x-cloak />
                    <x-lucide name="eye" class="h-4 w-4" x-show="!showPassword" />
                </button>
            </span>
        </label>
        <p x-show="fieldErrors.password" x-cloak class="mt-1.5 text-[11px] leading-4 text-rose-600" x-text="fieldErrors.password"></p>

        <label for="reg-password2" class="auth-form-label mt-3 block text-[11px] font-bold text-slate-700">
            Nhập lại mật khẩu
            <input id="reg-password2" x-model="form.passwordConfirmation" required :type="showPassword ? 'text' : 'password'"
                   autocomplete="new-password" minlength="8" placeholder="Nhập lại mật khẩu"
                   class="mt-1.5 w-full min-h-12 rounded-2xl border border-[#D5E3E9] bg-white px-4 py-3 text-[13px] font-medium text-[#183D5E] outline-none transition placeholder:text-[#8193A3] hover:border-[#B8D0DB] focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6]">
        </label>
        <p x-show="fieldErrors.passwordConfirmation" x-cloak class="mt-1.5 text-[11px] leading-4 text-rose-600" x-text="fieldErrors.passwordConfirmation"></p>

        <label class="auth-form-helper mt-2.5 flex items-start gap-2 text-[11px] leading-4 text-slate-600">
            <input type="checkbox" x-model="form.terms" class="mt-0.5 h-4 w-4 shrink-0 rounded accent-[#126F91]">
            <span>Tôi đồng ý với
                <a href="{{ route('info.policies.show', 'dieu-khoan') }}" target="_blank" rel="noopener" class="font-bold text-blue-600 hover:underline">Điều khoản sử dụng</a>
                và
                <a href="{{ route('info.policies.show', 'bao-mat') }}" target="_blank" rel="noopener" class="font-bold text-blue-600 hover:underline">Chính sách bảo mật</a>.
            </span>
        </label>
        <p x-show="fieldErrors.terms" x-cloak class="mt-1.5 text-[11px] leading-4 text-rose-600" x-text="fieldErrors.terms"></p>

        <button type="button" @click="goToStep2()" :disabled="busy"
                class="mt-3 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#126F91] to-[#188DB0] px-4 py-3 text-[12px] font-extrabold text-white shadow-[0_8px_18px_rgba(18,111,145,0.2)] transition hover:from-[#0F607E] hover:to-[#147D9B] disabled:cursor-not-allowed disabled:opacity-60 active:scale-[.98]">
            <span x-text="busy ? 'Đang gửi mã…' : 'Tiếp tục xác minh'"></span>
            <x-lucide name="arrow-right" class="h-4 w-4" x-show="!busy" />
        </button>
    </div>

    {{-- ══════ BƯỚC 2 — XÁC MINH ══════ --}}
    <div x-show="step === 2" x-cloak class="mt-5">
        <template x-if="verificationEnabled">
            <div>
                <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4">
                    <x-lucide name="mail" class="h-5 w-5 text-blue-600" />
                    <p class="auth-form-label mt-1.5 text-[11px] font-bold text-slate-800">Mã xác minh đã được gửi</p>
                    <p class="auth-form-helper mt-1 text-[11px] leading-4 text-slate-500">
                        Nhập 6 chữ số chúng tôi vừa gửi đến <span class="font-bold text-slate-700" x-text="maskedEmail"></span>.
                    </p>
                </div>

                <div class="mt-4 flex justify-between gap-2">
                    <template x-for="i in 6" :key="'d' + i">
                        <input :aria-label="'Chữ số ' + i" inputmode="numeric" maxlength="1" placeholder="•"
                               :data-index="i - 1" x-model="digits[i - 1]"
                               @input="onDigitInput($event, i - 1)" @keydown.backspace="onDigitBackspace($event, i - 1)"
                               @paste.prevent="onDigitPaste($event)"
                               class="h-10 min-w-0 flex-1 rounded-xl border border-slate-200 text-center text-base font-black outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                    </template>
                </div>

                <p x-show="codeError" x-cloak class="mt-2 flex items-start gap-1.5 text-[11px] leading-4 text-rose-600">
                    <x-lucide name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0" /><span x-text="codeError"></span>
                </p>

                <button type="button" @click="verifyCode()" :disabled="busy || code.length !== 6"
                        class="mt-4 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#126F91] to-[#188DB0] px-4 py-3 text-[12px] font-extrabold text-white shadow-[0_8px_18px_rgba(18,111,145,0.2)] transition hover:from-[#0F607E] hover:to-[#147D9B] disabled:cursor-not-allowed disabled:opacity-60 active:scale-[.98]">
                    <span x-text="busy ? 'Đang kiểm tra…' : 'Xác minh tài khoản'"></span>
                    <x-lucide name="check-circle" class="h-4 w-4" x-show="!busy" />
                </button>

                <div class="auth-form-helper mt-2.5 flex justify-between text-[11px]">
                    <button type="button" @click="step = 1" class="font-bold text-slate-500 hover:text-slate-700">Quay lại</button>
                    <button type="button" @click="resendCode()" :disabled="cooldown > 0 || busy"
                            class="auth-form-action font-bold text-blue-600 disabled:cursor-not-allowed disabled:text-slate-400"
                            x-text="cooldown > 0 ? ('Gửi lại mã sau ' + countdownLabel) : 'Gửi lại mã'"></button>
                </div>
            </div>
        </template>

        {{-- Chưa cấu hình gửi thư: nói thật, cho đi tiếp --}}
        <template x-if="!verificationEnabled">
            <div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
                    <x-lucide name="info" class="h-5 w-5 text-amber-600" />
                    <p class="auth-form-label mt-1.5 text-[11px] font-bold text-amber-900">Hệ thống chưa bật gửi thư xác minh</p>
                    <p class="auth-form-helper mt-1 text-[11px] leading-4 text-amber-800">
                        Bạn có thể tiếp tục tạo tài khoản ngay. Khi quản trị viên bật gửi thư, bước xác minh email sẽ tự động áp dụng.
                    </p>
                </div>
                <button type="button" @click="step = 3"
                        class="mt-4 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#126F91] to-[#188DB0] px-4 py-3 text-[12px] font-extrabold text-white shadow-[0_8px_18px_rgba(18,111,145,0.2)] transition hover:from-[#0F607E] hover:to-[#147D9B] active:scale-[.98]">
                    Tiếp tục <x-lucide name="arrow-right" class="h-4 w-4" />
                </button>
                <button type="button" @click="step = 1" class="auth-form-helper mt-2.5 block text-[11px] font-bold text-slate-500 hover:text-slate-700">Quay lại</button>
            </div>
        </template>
    </div>

    {{-- ══════ BƯỚC 3 — CHỌN VAI TRÒ ══════ --}}
    <form method="POST" action="{{ route('register') }}" x-show="step === 3" x-cloak class="mt-4">
        @csrf
        {{-- Dữ liệu bước 1 + token xác minh của bước 2 gửi kèm ở đây --}}
        <input type="hidden" name="name" :value="form.name">
        <input type="hidden" name="email" :value="form.email">
        <input type="hidden" name="phone" :value="form.phone">
        <input type="hidden" name="password" :value="form.password">
        <input type="hidden" name="password_confirmation" :value="form.passwordConfirmation">
        <input type="hidden" name="verification_token" :value="verificationToken">

        <p class="type-body leading-5">Chọn một vai trò để cá nhân hoá lộ trình và quyền sử dụng. Bạn luôn có thể cập nhật trong hồ sơ.</p>

        <div class="mt-3 space-y-2" role="radiogroup" aria-label="Vai trò đăng ký">
            @foreach ($registerRoles as $r)
                <label class="flex w-full cursor-pointer items-center gap-3 rounded-2xl border p-3 text-left transition"
                       :class="form.role === '{{ $r['id'] }}' ? 'border-[#2D7FA3] bg-[#EAF5F8]' : 'border-slate-200 bg-white hover:border-[#C9DFE8] hover:bg-[#F8FAFB]'">
                    <input type="radio" name="role" value="{{ $r['id'] }}" x-model="form.role" class="sr-only">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl"
                          :class="form.role === '{{ $r['id'] }}' ? 'bg-[#2D7FA3] text-white' : 'bg-slate-100 text-slate-500'">
                        <x-lucide :name="$r['icon']" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-black text-slate-800">{{ $r['label'] }}</span>
                        <span class="auth-form-helper mt-0.5 block text-[11px] leading-4 text-slate-500">{{ $r['note'] }}</span>
                    </span>
                    <span aria-hidden="true" class="ml-auto grid h-5 w-5 shrink-0 place-items-center rounded-full border"
                          :class="form.role === '{{ $r['id'] }}' ? 'border-[#2D7FA3] bg-[#2D7FA3] text-white' : 'border-slate-300 bg-white'">
                        <x-lucide name="check-circle" class="h-3.5 w-3.5" x-show="form.role === '{{ $r['id'] }}'" x-cloak />
                    </span>
                </label>
            @endforeach
        </div>

        {{-- Giáo viên khai thêm môn dạy và giới thiệu — AuthService đã dùng 2 trường này để
             tạo hồ sơ giảng dạy ở trạng thái "Chờ duyệt" (3.3). --}}
        <div x-show="form.role === 'teacher'" x-cloak class="mt-3 space-y-2.5 rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3">
            <p class="flex items-center gap-1.5 text-[11px] font-bold text-[#0066CC]">
                <x-lucide name="info" class="h-3.5 w-3.5 shrink-0" />Hồ sơ giáo viên cần quản trị viên duyệt trước khi mở lớp.
            </p>
            <label class="auth-form-label block text-[11px] font-bold text-slate-700">
                Môn dạy <span class="font-medium text-slate-400">(cách nhau bằng dấu phẩy)</span>
                <input name="subjects" value="{{ old('subjects') }}" placeholder="Tin học, Toán"
                       class="mt-1.5 w-full min-h-11 rounded-xl border border-[#D5E3E9] bg-white px-3 py-2 text-[13px] text-[#183D5E] outline-none focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6]">
            </label>
            <label class="auth-form-label block text-[11px] font-bold text-slate-700">
                Giới thiệu ngắn
                <textarea name="bio" rows="2" placeholder="Kinh nghiệm giảng dạy, thành tích nổi bật…"
                          class="mt-1.5 w-full rounded-xl border border-[#D5E3E9] bg-white px-3 py-2 text-[13px] leading-relaxed text-[#183D5E] outline-none focus:border-[#2D7FA3] focus:ring-4 focus:ring-[#DDF1F6]">{{ old('bio') }}</textarea>
            </label>
        </div>

        <button type="submit"
                class="mt-4 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#126F91] to-[#188DB0] px-4 py-3 text-[12px] font-extrabold text-white shadow-[0_8px_18px_rgba(18,111,145,0.2)] transition hover:from-[#0F607E] hover:to-[#147D9B] active:scale-[.98]">
            Hoàn tất tạo tài khoản <x-lucide name="arrow-right" class="h-4 w-4" />
        </button>

        <button type="button" @click="step = verificationEnabled ? 2 : 1"
                class="auth-form-helper mx-auto mt-3 flex items-center gap-1 text-[11px] font-bold text-slate-500 hover:text-slate-700">
            <x-lucide name="arrow-left" class="h-3.5 w-3.5" />Quay lại
        </button>
    </form>

    <p class="auth-form-helper mt-3 text-center text-[11px] text-slate-500">
        Đã có tài khoản?
        <a href="{{ route('login') }}" class="auth-form-action font-black text-blue-600 hover:underline">Đăng nhập</a>
    </p>
</div>
@endsection

@push('scripts')
    @include('partials.register-wizard-script')
@endpush
