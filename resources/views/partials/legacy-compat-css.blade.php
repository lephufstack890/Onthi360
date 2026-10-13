{{-- SỬA 13/10 — vá CSS cho trình duyệt cũ (Chrome ≤ 110: máy Win7/8.1, Cốc Cốc/Edge bản cũ).
     Tailwind v4 viết màu bằng oklch() và gradient "in oklab" — trình duyệt cũ không hiểu nên bỏ
     trắng: nút Đăng nhập / Đăng ký (nền gradient, chữ trắng) thành chữ trắng trên nền trắng,
     trông như "biến mất". Tệp public/css/legacy-compat.css do scripts/legacy-css.mjs sinh ra sau
     mỗi lần "npm run build"; trình duyệt mới bỏ qua toàn bộ nhờ @supports not (...).
     PHẢI đặt SAU @vite để các khối @layer trong tệp vá đứng sau bản gốc. --}}
@if (is_file(public_path('css/legacy-compat.css')))
    <link rel="stylesheet" href="{{ asset('css/legacy-compat.css') }}?v={{ filemtime(public_path('css/legacy-compat.css')) }}">
@endif
