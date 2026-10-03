{{-- ═══════════════ KHUNG TRÌNH ĐỌC TÀI LIỆU — TOÀN MÀN HÌNH ═══════════════
     SỬA 3/10 (khách: "UI chỗ đọc tài liệu nó không giống… làm UI như ảnh tôi gửi").

     Trong ảnh khách gửi, trình đọc chiếm TRỌN bề ngang cửa sổ: không thanh bên, không thanh
     tiêu đề khu học sinh. Trang này trước đây dựng trong layouts/workspace nên bị thanh bên
     230px ăn mất chỗ, và tệ hơn là có HAI bộ điều hướng chồng nhau — thanh bên của khu, cộng
     nút "Quay lại / Đóng" của chính trình đọc.

     Cùng lý do và cùng cách đã làm cho phòng thi hồi 18/9 (layouts/exam) — khác đúng một điểm:
     phòng thi khoá cuộn ở body vì khung thi cao 100dvh, còn trang đọc thì PHẢI cuộn được
     (thanh đầu trang dính theo, vùng đọc có thanh cuộn riêng bên trong).

     KHÔNG đụng tới layouts/app|student|teacher|workspace đang dùng cho 100+ view khác — đây là
     file MỚI, chỉ trình đọc tài liệu dùng.

     Giữ nguyên hợp đồng của các layout khác: section 'title'/'content' và stack 'scripts', cộng
     partial toast-root, để view không phải viết khác đi. --}}
<!DOCTYPE html>
<html lang="vi" class="h-full">
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
<body class="min-h-full bg-[#F7F9FB] text-[#466278] antialiased">
    @yield('content')

    @stack('scripts')

    @include('partials.toast-root')
</body>
</html>
