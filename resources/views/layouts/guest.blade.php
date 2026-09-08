
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Ôn Thi 360') — Ôn Thi 360</title>
    {{-- SỬA 8/9 (7) — bản design dùng chữ bo tròn; nạp Nunito (có đủ dấu tiếng Việt) CHỈ cho
         layout công khai này, các layout admin/giáo viên/học sinh giữ nguyên font cũ. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        .font-onthi { font-family: 'Nunito', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>window.__flashToasts = window.__flashToasts || [];</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="font-onthi bg-[#f6f9fd] text-slate-800 antialiased">
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
