{{-- SỬA 9/10 — thanh chọn giữa "Thông tin cá nhân" và "Đổi mật khẩu" (2 màn riêng).
     Nhận $profileUrl, $passwordUrl, $active ('profile' | 'password'). --}}
<nav class="pp-tabs" aria-label="Tài khoản">
    <a href="{{ $profileUrl }}" @if (($active ?? 'profile') === 'profile') aria-current="page" @endif>
        <x-lucide name="user-round" class="h-4 w-4" />Thông tin cá nhân
    </a>
    <a href="{{ $passwordUrl }}" @if (($active ?? 'profile') === 'password') aria-current="page" @endif>
        <x-lucide name="lock" class="h-4 w-4" />Đổi mật khẩu
    </a>
</nav>
