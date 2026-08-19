<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ôn Thi 360')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>window.__flashToasts = window.__flashToasts || [];</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
    <div class="flex min-h-screen">
        <aside class="hidden lg:flex lg:flex-col w-64 bg-white border-r border-slate-200">
            <div class="h-16 flex items-center px-5 font-semibold text-rose-600">Ôn Thi 360</div>
            <nav class="flex-1 px-3 space-y-1 overflow-y-auto">
                @yield('sidebar')
            </nav>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-6">
                <div class="flex items-center gap-3">
                    <button type="button" class="lg:hidden text-slate-500" aria-label="Mở menu">☰</button>
                    <h1 class="font-medium text-slate-700">@yield('page-title')</h1>
                </div>
                <div class="flex items-center gap-3">
                    @include('partials.role-switcher')
                    @include('partials.notifications-bell')
                    @include('partials.profile-menu')
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-6">
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')

    @include('partials.toast-root')
</body>
</html>
