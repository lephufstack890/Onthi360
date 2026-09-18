{{-- ═══════════════ KHUNG PHÒNG THI — TOÀN MÀN HÌNH ═══════════════
     SỬA 18/9 (khách: "click làm bài thì hiện trang này, copy UI từ source mới").

     Bản mẫu (education-main/src/components/AssessmentModal.jsx) dựng phòng làm bài là một
     LỚP PHỦ chiếm trọn màn hình — không sidebar, không thanh tiêu đề khu học sinh. Ở đây nó
     là một TRANG riêng nên cần đúng một layout trống như vậy; dùng layouts/student sẽ bị
     sidebar + banner ăn mất chiều cao, không đặt được khung thi cao 100dvh.

     KHÔNG đụng tới layouts/app|student|workspace đang dùng cho 100+ view khác — file này là
     file MỚI, chỉ trang làm bài dùng.

     Giữ nguyên hợp đồng của các layout khác: section 'title'/'content' và stack 'scripts',
     cộng partial toast-root, để view không phải viết khác đi. --}}
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
<body class="h-full overflow-hidden bg-[#F8FBFC] text-[#123B68] antialiased">
    @yield('content')

    @stack('scripts')

    @include('partials.toast-root')
</body>
</html>
