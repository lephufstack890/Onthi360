<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo')
    {{-- SỬA 9/9 (10) — đổi font sang Plus Jakarta Sans theo đúng source giao diện khách gửi
         (education/index.html + src/index.css). Lớp .font-onthi giữ nguyên tên để các trang
         công khai cũ không phải sửa, chỉ đổi bộ chữ bên trong. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>
        .font-onthi, body { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        [x-cloak] { display: none !important; }
        /* Thanh cuộn ngang ẩn — dùng cho dải tab/nút trượt ngang trên mobile (source dùng .no-scrollbar) */
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        html, body { overflow-x: hidden; width: 100%; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>window.__flashToasts = window.__flashToasts || [];</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="font-onthi bg-[#EEF5FC] text-slate-800 antialiased min-h-screen selection:bg-blue-100">
    @include('partials.nav-public')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.mobile-bottom-nav')

    @include('partials.toast-root')
    @stack('scripts')
</body>
</html>
