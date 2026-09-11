<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo')
    {{-- Bộ chữ lấy đúng theo source giao diện khách (education-main/index.html):
         Be Vietnam Pro cho toàn trang, Dancing Script cho câu trích của giáo viên. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800;900&family=Dancing+Script:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300..800&display=swap" rel="stylesheet">
    <style>
        .font-onthi, body { font-family: 'Be Vietnam Pro', 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        .font-script { font-family: 'Dancing Script', cursive; }
        [x-cloak] { display: none !important; }
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
