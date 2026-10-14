{{-- SỬA 13/10 — vá CSS cho trình duyệt cũ (Chrome ≤ 110: máy Win7/8.1, Cốc Cốc/Edge bản cũ).
     Tailwind v4 viết màu bằng oklch() và gradient "in oklab" — trình duyệt cũ không hiểu nên bỏ
     trắng: nút Đăng nhập / Đăng ký (nền gradient, chữ trắng) thành chữ trắng trên nền trắng,
     trông như "biến mất". Tệp public/build/legacy-compat.css do scripts/legacy-css.mjs sinh ra sau
     mỗi lần "npm run build"; trình duyệt mới bỏ qua toàn bộ nhờ @supports not (...).
     PHẢI đặt SAU @vite để các khối @layer trong tệp vá đứng sau bản gốc. --}}
{{-- SỬA 14/10 — tệp sinh ra nằm cạnh bản build (public/build); bản cũ ở public/css chỉ còn là dự phòng. --}}
@php
    $legacyCssPath = collect(['build/legacy-compat.css', 'css/legacy-compat.css'])->first(fn ($p) => is_file(public_path($p)));
@endphp
@if ($legacyCssPath)
    <link rel="stylesheet" href="{{ asset($legacyCssPath) }}?v={{ filemtime(public_path($legacyCssPath)) }}">
@endif
