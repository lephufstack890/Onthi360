
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Ôn Thi 360') — Ôn Thi 360</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>window.__flashToasts = window.__flashToasts || [];</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
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
