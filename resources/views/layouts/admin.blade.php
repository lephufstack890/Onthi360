{{-- ═══════════════ KHUNG TRANG QUẢN TRỊ ═══════════════
     SỬA 12/9 (khách: "trang admin chưa có thiết kế, làm lại toàn bộ UI theo phong cách các
     trang đã thiết kế") — dựng theo ĐÚNG không gian quản trị của source giao diện khách gửi:
     education-main/src/components/RoleWorkspace.jsx (roleConfig.admin + RoleWorkspace shell).

     Bố cục của source: lưới 2 cột [230px | phần còn lại], cột trái là MỘT THẺ TRẮNG bo tròn
     (không phải thanh bên dính mép màn hình như bản cũ), cột phải là banner + nội dung.

     TRƯỚC ĐÂY layouts/admin chỉ @extends('layouts.app') — mà layouts.app còn dùng chung cho
     giáo viên / học sinh / phụ huynh. Nếu sửa thẳng vào đó thì đổi luôn cả 3 khu vực kia,
     ngoài phạm vi khách yêu cầu. Vì vậy khung này ĐỨNG RIÊNG, layouts.app giữ nguyên không
     đụng tới.

     LOGIC KHÔNG ĐỔI: vẫn nguyên các section 'page-title' / 'content' / stack 'scripts' và
     toast-root như cũ, nên mọi view admin hiện có chạy y hệt, chỉ khác lớp áo. Ba partial
     thanh trên cùng dùng BẢN RIÊNG cho admin (admin-role-switcher / admin-notifications-bell
     / admin-profile-menu) — bản gốc giữ nguyên cho layouts/app. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.seo', ['seoPrivate' => true])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
    <script>window.__flashToasts = window.__flashToasts || [];</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="admin-typography bg-[#F4F8FC] text-slate-800 antialiased min-h-screen">

    {{-- ══════ THANH TRÊN CÙNG ══════ --}}
    <header class="sticky top-0 z-50 w-full border-b border-sky-100 bg-white/95 shadow-[0_1px_3px_rgba(0,0,0,0.02)] backdrop-blur-md"
            x-data="{ mobileNavOpen: false }">
        <div class="mx-auto flex w-full max-w-[1780px] items-center gap-2 px-3 py-2 sm:px-5 sm:py-2.5 lg:px-6 2xl:px-10">
            <a href="{{ route('admin.dashboard') }}" class="group flex shrink-0 items-center gap-2">
                <img src="{{ asset('assets/header-logo.png') }}" alt="Ôn Thi 360"
                     class="h-7 object-contain transition-transform group-hover:scale-102 sm:h-8">
            </a>

            <span class="hidden shrink-0 items-center gap-1.5 rounded-full border border-blue-100 bg-blue-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-blue-700 sm:inline-flex">
                <x-lucide name="shield-check" class="h-3 w-3" />Quản trị
            </span>

            <button type="button" @click="mobileNavOpen = !mobileNavOpen"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-sky-100 bg-white text-slate-600 transition-colors hover:bg-sky-50 hover:text-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 lg:hidden"
                    :aria-label="mobileNavOpen ? 'Đóng menu quản trị' : 'Mở menu quản trị'"
                    :aria-expanded="mobileNavOpen">
                <x-lucide name="x" class="h-4.5 w-4.5" x-show="mobileNavOpen" x-cloak />
                <x-lucide name="menu" class="h-4.5 w-4.5" x-show="!mobileNavOpen" />
            </button>

            <p class="ml-1 min-w-0 flex-1 truncate text-[13px] font-bold text-[#0B3C78] sm:text-sm">@yield('page-title')</p>

            <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                @include('partials.admin-role-switcher')
                @include('partials.admin-notifications-bell')
                @include('partials.admin-profile-menu')
            </div>
        </div>

        {{-- Ngăn kéo điều hướng cho màn hình nhỏ --}}
        <div x-show="mobileNavOpen" x-cloak x-transition
             class="border-t border-sky-100 bg-white px-3 py-3 shadow-xl lg:hidden">
            @include('partials.sidebar-admin', ['adminNavCompact' => true])
        </div>
    </header>

    {{-- ══════ THÂN TRANG ══════ --}}
    <div class="mx-auto grid w-full max-w-[1780px] min-w-0 gap-4 px-3 py-3 sm:px-5 sm:py-5 lg:grid-cols-[230px_minmax(0,1fr)] lg:gap-5 lg:px-6 2xl:px-10">

        <aside class="hidden min-w-0 lg:block lg:sticky lg:top-[68px] lg:h-fit">
            @include('partials.sidebar-admin')
        </aside>

        <main class="min-w-0 space-y-4">
            @yield('content')
        </main>
    </div>

    @stack('scripts')

    @include('partials.toast-root')
</body>
</html>
