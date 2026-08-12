{{--
  Layout công khai (chưa đăng nhập) — dùng cho toàn bộ menu 4.1:
  Trang chủ, Khóa học, Luyện tập, Tài liệu, Cuộc thi, Bảng xếp hạng,
  Giáo viên tiêu biểu, Thông tin.

  Cách dùng ở view con:
    @extends('layouts.guest')
    @section('title', 'Tên trang')
    @section('content') ... @endsection
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Ôn Thi 360') — Ôn Thi 360</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Alpine.js qua CDN: welcome.blade.php và register.blade.php dùng x-data
         (FAQ accordion, chọn vai trò đăng ký) — nạp ở đây để chạy trên toàn bộ
         layout công khai, giống layouts/app.blade.php. --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
    @include('partials.nav-public')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.mobile-bottom-nav')
</body>
</html>
