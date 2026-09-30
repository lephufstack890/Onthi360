<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- SỬA 30/9 (3) — cắt bớt phông tải về. Trước đây tải 3 bộ phông với đủ mọi độ đậm,
         mà bản thân giao diện chỉ dùng Be Vietnam Pro (400–900) và Dancing Script đậm cho
         đúng một dòng chữ viết tay. Riêng Plus Jakarta Sans chỉ nằm dự phòng phía sau
         Be Vietnam Pro trong danh sách phông nên KHÔNG BAO GIỜ được dùng tới, vậy mà vẫn
         tải về mỗi lần mở trang. Thẻ này chặn hiển thị cho tới khi tải xong nên càng nhẹ
         càng nhanh thấy chữ. --}}
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
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
    {{-- SỬA 30/9 (3) — chỗ để từng trang tự khai ảnh to nhất của mình cho trình duyệt
         tải sớm (xem @push('head') ở trang lộ trình). --}}
    @stack('head')
</head>
<body class="font-onthi bg-[#F4F8FC] text-slate-800 antialiased min-h-screen selection:bg-blue-100">
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
