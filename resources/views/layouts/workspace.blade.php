{{-- ═══════════════ KHUNG CHUNG CHO 4 KHU LÀM VIỆC ═══════════════
     Quản trị · Giáo viên · Học sinh · Phụ huynh.

     SỬA 12/9 — dựng theo ĐÚNG shell <RoleWorkspace> của source giao diện khách gửi
     (education-main/src/components/RoleWorkspace.jsx): một hằng roleConfig cho 4 vai trò, lưới
     2 cột [230px | phần còn lại], cột trái là MỘT THẺ TRẮNG bo tròn, cột phải là banner + nội
     dung. Source dùng CHUNG một khung cho cả 4 vai trò nên ở đây cũng vậy — trước kia mỗi vai
     trò một file layout mà bên trong lại cùng @extends('layouts.app'), sửa một chỗ là đổi cả 4
     một cách ngoài ý muốn.

     LOGIC KHÔNG ĐỔI: vẫn nguyên các section 'page-title' / 'content' / stack 'scripts' và
     partial toast-root, nên mọi view hiện có chạy y hệt, chỉ khác lớp áo.

     $wsRole do 4 file layouts/{admin,teacher,student,parent}.blade.php truyền vào. --}}
@php
    $wsRole = $wsRole ?? 'student';

    $wsConfig = [
        'admin' => [
            'badge' => 'Quản trị',
            'navTitle' => 'Quản trị hệ thống',
            'sidebar' => 'partials.sidebar-admin',
            'home' => 'admin.dashboard',
        ],
        'teacher' => [
            'badge' => 'Giáo viên',
            'navTitle' => 'Không gian giảng dạy',
            'sidebar' => 'partials.sidebar-teacher',
            'home' => 'dashboard',
        ],
        'student' => [
            'badge' => 'Học sinh',
            'navTitle' => 'Không gian học tập',
            'sidebar' => 'partials.sidebar-student',
            'home' => 'dashboard',
        ],
        'parent' => [
            'badge' => 'Phụ huynh',
            'navTitle' => 'Không gian phụ huynh',
            'sidebar' => 'partials.sidebar-parent',
            'home' => 'dashboard',
        ],
    ][$wsRole] ?? null;

    // Khoá 'home' của mỗi vai trò giữ lại để dùng về sau (điều hướng nội bộ) — KHÔNG còn
    // dùng cho logo nữa, xem ghi chú ở thẻ <a> của logo bên dưới.

    // Vai trò lạ (không nằm trong 4 khu) vẫn phải ra được trang, lấy tạm cấu hình học sinh.
    $wsConfig = $wsConfig ?? [
        'badge' => 'Khu làm việc', 'navTitle' => 'Khu làm việc',
        'sidebar' => 'partials.sidebar-student', 'home' => 'dashboard',
    ];
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.seo', ['seoPrivate' => true])
    @include('partials.brand-font')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.legacy-compat-css')
    <style>[x-cloak] { display: none !important; }</style>
    {{-- SỬA 15/10 (khách: "UI vai trò giáo viên, phụ huynh, học sinh chữ nhỏ quá, tăng size cho dễ nhìn") — phóng
         đều cả khu làm việc lên 110% (màn ≥1536px: 115%), giống người dùng tự bấm Ctrl + trên trình duyệt: chữ, nút,
         khoảng cách to đều nhau nên bố cục không vỡ. Chỉ áp từ màn 1024px trở lên (điện thoại đã tự phóng theo máy).
         KHÔNG áp cho khu Quản trị, và tắt ở trang có ô soạn code (@section('ws-no-zoom')) để con trỏ soạn code
         không bị lệch trên trình duyệt cũ. Muốn chỉnh mức to: sửa 2 con số zoom dưới đây. --}}
    <style>
        @media (min-width: 1024px) { body.ws-roomy { zoom: 1.1; } }
        @media (min-width: 1536px) { body.ws-roomy { zoom: 1.15; } }
    </style>
    <script>window.__flashToasts = window.__flashToasts || [];</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="admin-typography bg-[#F4F8FC] text-slate-800 antialiased min-h-screen {{ $wsRole !== 'admin' && ! $__env->hasSection('ws-no-zoom') ? 'ws-roomy' : '' }}">

    {{-- ══════ THANH TRÊN CÙNG ══════ --}}
    <header class="sticky top-0 z-50 w-full border-b border-sky-100 bg-white/95 shadow-[0_1px_3px_rgba(0,0,0,0.02)] backdrop-blur-md"
            x-data="{ mobileNavOpen: false }">
        <div class="mx-auto flex w-full max-w-[1780px] items-center gap-2 px-3 py-2 sm:px-5 sm:py-2.5 lg:px-6 2xl:px-10">
            {{--
              SỬA 23/9 (khách: "bấm logo phải về trang công khai, đó là phản xạ của người dùng")
              — logo trỏ về TRANG CHỦ CÔNG KHAI chứ không về bảng điều khiển của vai trò nữa.
              Muốn quay lại khu làm việc thì đã có mục đầu tiên trong menu bên trái.
            --}}
            <a href="{{ route('home') }}" title="Về trang chủ Ôn Thi 360" class="group flex shrink-0 items-center gap-2">
                <img src="{{ asset('assets/header-logo.png') }}" alt="Ôn Thi 360"
                     class="h-7 object-contain transition-transform group-hover:scale-102 sm:h-8">
            </a>

            <span class="hidden shrink-0 items-center gap-1.5 rounded-full border border-blue-100 bg-blue-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-blue-700 sm:inline-flex">
                <x-lucide name="shield-check" class="h-3 w-3" />{{ $wsConfig['badge'] }}
            </span>

            <button type="button" @click="mobileNavOpen = !mobileNavOpen"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-sky-100 bg-white text-slate-600 transition-colors hover:bg-sky-50 hover:text-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 lg:hidden"
                    :aria-label="mobileNavOpen ? 'Đóng menu điều hướng' : 'Mở menu điều hướng'"
                    :aria-expanded="mobileNavOpen">
                <x-lucide name="x" class="h-4.5 w-4.5" x-show="mobileNavOpen" x-cloak />
                <x-lucide name="menu" class="h-4.5 w-4.5" x-show="!mobileNavOpen" />
            </button>

            <p class="ml-1 min-w-0 flex-1 truncate text-[13px] font-bold text-[#0B3C78] sm:text-sm">@yield('page-title')</p>

            <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                {{-- SỬA 14/9 — số dư ví token đặt ngay cạnh chuông cho đập vào mắt, bấm vào là
                     sang thẳng Ví token. Đọc trực tiếp cột users.token_balance (đúng thứ
                     WalletService::balanceFor trả về) nên không sinh thêm truy vấn nào. --}}
                {{-- SỬA 14/9 (khách yêu cầu) — quản trị viên đã full quyền, không mua bán gì
                     nên ẩn viên số dư với vai trò này. ẨN CHỨ KHÔNG XOÁ: bỏ điều kiện là hiện
                     lại như cũ. --}}
                @php
                    $wsTokenBalance = (int) (auth()->user()?->token_balance ?? 0);
                    $wsShowWallet = ! (auth()->user()?->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN) ?? false);
                @endphp
                @if ($wsShowWallet)
                <a href="{{ route('wallet.index') }}"
                   title="Ví token — số dư {{ \App\Support\TokenPrice::label($wsTokenBalance) }}"
                   aria-label="Ví token, số dư {{ \App\Support\TokenPrice::label($wsTokenBalance) }}"
                   class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 py-1 pl-1.5 pr-2.5 text-emerald-700 transition-colors hover:border-emerald-300 hover:bg-emerald-100">
                    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-emerald-600 text-white">
                        <x-lucide name="wallet-cards" class="h-3 w-3" />
                    </span>
                    <span class="text-[12px] font-black leading-none tabular-nums">{{ \App\Support\TokenPrice::number($wsTokenBalance) }}</span>
                    <span class="hidden text-[10px] font-bold uppercase tracking-wide text-emerald-600 sm:inline">token</span>
                </a>
                @endif

                @include('partials.admin-role-switcher')
                @include('partials.admin-notifications-bell')
                @include('partials.admin-profile-menu')
            </div>
        </div>

        {{-- Ngăn kéo điều hướng cho màn hình nhỏ --}}
        <div x-show="mobileNavOpen" x-cloak x-transition
             class="border-t border-sky-100 bg-white px-3 py-3 shadow-xl lg:hidden">
            @include($wsConfig['sidebar'], ['wsNavCompact' => true, 'wsNavTitle' => $wsConfig['navTitle']])
        </div>
    </header>

    {{-- ══════ THÂN TRANG ══════ --}}
    <div class="mx-auto grid w-full max-w-[1780px] min-w-0 gap-4 px-3 py-3 sm:px-5 sm:py-5 lg:grid-cols-[230px_minmax(0,1fr)] lg:gap-5 lg:px-6 2xl:px-10">

        <aside class="hidden min-w-0 lg:block lg:sticky lg:top-[68px] lg:h-fit">
            @include($wsConfig['sidebar'], ['wsNavTitle' => $wsConfig['navTitle']])
        </aside>

        <main class="min-w-0 space-y-4">
            {{-- SỬA 15/10 — nhắc rõ đang dùng giao diện Giáo viên bằng tài khoản quản trị, kèm nút quay về. --}}
            @if (auth()->user()?->actingRole() !== null)
                <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-[13px] text-amber-900">
                    <x-lucide name="eye" class="h-4 w-4 shrink-0" />
                    <span class="min-w-0 flex-1">Bạn đang xem <b>giao diện Giáo viên</b> bằng tài khoản quản trị. Mọi thao tác ở đây được thực hiện như một giáo viên.</span>
                    <form method="POST" action="{{ route('acting.stop') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-3 py-1.5 text-[12px] font-bold text-white hover:bg-amber-700">
                            <x-lucide name="shield-check" class="h-3.5 w-3.5" />Về Quản trị
                        </button>
                    </form>
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    @stack('scripts')

    @include('partials.toast-root')
</body>
</html>
