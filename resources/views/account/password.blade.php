{{-- SỬA 9/10 (khách: "màn đổi mật khẩu tách riêng ra màn mới") — màn Đổi mật khẩu riêng cho cả 4 khu.
     $layout do App\Http\Controllers\Account\PasswordController chọn theo khu đang đứng. --}}
@extends($layout)

@section('title', 'Đổi mật khẩu')
@section('page-title', 'Đổi mật khẩu')

@section('content')
    @if (session('status') === 'password-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đổi mật khẩu thành công.'])
    @endif

    @include('partials.personal-profile-style')

    <section class="personal-profile" aria-labelledby="password-title">
        @include('partials.account-tabs', ['active' => 'password'])

        <header class="pp-heading">
            <div>
                <p>Bảo mật tài khoản</p>
                <h1 id="password-title">Đổi mật khẩu</h1>
                <span>Cần nhập đúng mật khẩu hiện tại trước khi đặt mật khẩu mới.</span>
            </div>
            <span class="pp-role"><x-lucide name="shield-check" class="h-4 w-4" />{{ $user->email }}</span>
        </header>

        <form method="POST" action="{{ $formAction }}" class="pp-single" novalidate data-pw-form autocomplete="off">
            @csrf
            @method('PUT')
            <div class="pp-card">
                <h2><x-lucide name="lock" class="h-[18px] w-[18px]" />Mật khẩu mới</h2>
                <div class="pp-fields" style="margin-top:4px">
                    @foreach ([
                        ['current_password', 'Mật khẩu hiện tại', 'current-password', 'pp-wide'],
                        ['password', 'Mật khẩu mới', 'new-password', ''],
                        ['password_confirmation', 'Nhập lại mật khẩu mới', 'new-password', ''],
                    ] as [$field, $label, $auto, $cls])
                        <label for="{{ $field }}" class="{{ $cls }}">{{ $label }}<span class="pp-req" aria-hidden="true"> *</span>
                            <span class="pp-pass-wrap">
                                <input id="{{ $field }}" name="{{ $field }}" type="password" required autocomplete="{{ $auto }}"
                                       @if ($field !== 'current_password') minlength="8" @endif
                                       @error($field) aria-invalid="true" @enderror>
                                <button type="button" data-pw-toggle aria-label="Hiện/ẩn {{ $label }}"><x-lucide name="eye" class="h-4 w-4" /></button>
                            </span>
                            @error($field)<small class="pp-field-error">{{ $message }}</small>@enderror
                        </label>
                    @endforeach
                </div>

                <div class="pp-tips">
                    <strong>Gợi ý mật khẩu an toàn</strong>
                    <ul>
                        <li>Tối thiểu 8 ký tự, nên có cả chữ, số và ký hiệu.</li>
                        <li>Khác mật khẩu hiện tại và không dùng lại ở trang web khác.</li>
                    </ul>
                </div>

                <p class="pp-error" role="alert" data-pw-error hidden></p>
            </div>

            <footer class="pp-save-bar">
                <div>
                    <strong>Mật khẩu mới có hiệu lực ngay sau khi lưu</strong>
                    <p>Bạn vẫn đang đăng nhập trên thiết bị này.</p>
                </div>
                <div>
                    <a href="{{ $profileUrl }}" class="pp-btn">Quay lại hồ sơ</a>
                    <button type="submit" class="pp-primary"><x-lucide name="save" class="h-[17px] w-[17px]" />Đổi mật khẩu</button>
                </div>
            </footer>
        </form>
    </section>

    <script>
        (function () {
            var form = document.querySelector('[data-pw-form]');
            if (!form) return;
            var err = form.querySelector('[data-pw-error]');
            [].forEach.call(form.querySelectorAll('[data-pw-toggle]'), function (btn) {
                btn.addEventListener('click', function () {
                    var input = btn.parentNode.querySelector('input');
                    input.type = input.type === 'password' ? 'text' : 'password';
                });
            });
            form.addEventListener('submit', function (e) {
                var cur = form.current_password.value, next = form.password.value, again = form.password_confirmation.value, msg = '';
                if (!cur) msg = 'Hãy nhập mật khẩu hiện tại.';
                else if (next.length < 8) msg = 'Mật khẩu mới cần tối thiểu 8 ký tự.';
                else if (next !== again) msg = 'Hai lần nhập mật khẩu mới chưa khớp.';
                else if (next === cur) msg = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
                err.textContent = msg; err.hidden = !msg;
                if (msg) e.preventDefault();
            });
        })();
    </script>
@endsection
