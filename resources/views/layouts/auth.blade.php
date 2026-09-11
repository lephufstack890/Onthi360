{{-- ═══════════════ KHUNG MÀN TÀI KHOẢN (AuthShell) ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi:
     education-main/src/components/AccessCenterModal.jsx (hàm AuthShell + ArtPanel).

     Bản mẫu là hộp thoại nổi trên trang; ở đây là TRANG THẬT (/login, /register, /quen-mat-khau)
     vì hệ thống điều hướng bằng URL chứ không mở modal. Giữ nguyên bố cục 2 cột, bo góc, đổ
     bóng, dải viền trên màu thương hiệu và nút đóng — chỉ khác: nút đóng quay về trang chủ.

     Biến truyền vào:
       · $authTitle    — tiêu đề lớn
       · $authEyebrow  — dòng chữ nhỏ in hoa phía trên tiêu đề
       · $authCompact  — true: khung hẹp 1 cột (các màn ngắn như quên mật khẩu) --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Tài khoản') — Ôn Thi 360</title>
    <meta name="robots" content="noindex, nofollow">
    {{-- Luồng đăng ký 3 bước gọi máy chủ bằng fetch nên cần token CSRF ở đây. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.seo')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Be Vietnam Pro', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-950/60 antialiased">
@php
    $authCompact = $authCompact ?? false;
@endphp

<div class="auth-shell fixed inset-0 z-[75] flex items-center justify-center overflow-y-auto bg-slate-950/60 p-2 backdrop-blur-[3px] sm:p-5">
    <div class="relative my-auto grid max-h-[min(96dvh,740px)] w-full overflow-hidden rounded-3xl border border-white/60 bg-white shadow-[0_24px_70px_rgba(7,44,72,0.28)] {{ $authCompact ? 'max-w-xl' : 'max-w-5xl lg:grid-cols-[1.05fr_.95fr]' }}">

        @unless ($authCompact)
            {{-- Cột ảnh bên trái --}}
            <aside class="relative hidden min-h-[620px] overflow-hidden bg-[#0574ca] lg:flex lg:flex-col lg:justify-between">
                <img src="{{ asset('assets/auth-welcome.jpg') }}" alt="Học sinh học lập trình cùng Ôn Thi 360"
                     class="absolute inset-0 h-full w-full object-cover object-right">
                <div class="absolute inset-0 bg-gradient-to-br from-[#0056a8]/95 via-[#006fc8]/75 to-[#15b8df]/20"></div>

                <div class="relative p-8 xl:p-10 text-white">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/15 px-3 py-1.5 text-[11px] font-bold backdrop-blur">
                        <x-lucide name="sparkles" class="h-3.5 w-3.5 text-amber-300" />Học cùng mục tiêu, vươn xa ước mơ
                    </div>
                    <h2 class="mt-6 max-w-sm text-2xl font-black leading-tight tracking-tight text-white xl:text-3xl">Mỗi bước học tập đều được lưu lại.</h2>
                    <p class="mt-3 max-w-sm text-xs leading-5 text-sky-50">
                        Từ bài luyện đầu tiên đến hành trình chinh phục mục tiêu — Ôn Thi 360 đồng hành cùng bạn.
                    </p>
                </div>

                {{-- 3 số liệu: lấy từ dữ liệu vận hành thật (HomeService::buildStats), không phải
                     con số quảng bá cố định như bản mẫu. --}}
                @php $authStats = app(\App\Services\Public\HomeService::class)->buildStats(); @endphp
                <div class="relative m-6 mt-0 grid grid-cols-3 gap-2 rounded-2xl border border-white/25 bg-slate-950/10 p-3 backdrop-blur-md xl:m-8">
                    @foreach (array_slice($authStats, 0, 3) as $stat)
                        <div class="text-center">
                            <p class="text-lg font-black text-white">{{ $stat['value'] }}</p>
                            <p class="text-[11px] font-medium text-sky-100">{{ $stat['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </aside>
        @endunless

        {{-- Cột nội dung bên phải --}}
        <section class="relative min-w-0 overflow-y-auto bg-gradient-to-b from-white via-white to-[#F8FCFD] {{ $authCompact ? '' : 'border-t-4 border-[#126F91] lg:max-h-[min(96dvh,740px)] lg:border-l lg:border-t-0 lg:border-[#E5F0F4]' }}">
            <a href="{{ route('home') }}" aria-label="Đóng, quay về trang chủ"
               class="absolute right-3 top-3 z-10 grid h-10 w-10 place-items-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-100">
                <x-lucide name="x" class="h-5 w-5" />
            </a>

            <div class="mx-auto w-full {{ $authCompact ? 'p-5 sm:p-7' : 'max-w-md p-4 sm:p-8 lg:px-9 lg:py-9' }}">
                @unless ($authCompact)
                    <a href="{{ route('home') }}" class="flex items-center gap-2 text-[#126F91]">
                        <span class="grid h-8 w-8 place-items-center rounded-xl border border-[#C9DFE8] bg-[#EAF5F8]">
                            <x-lucide name="shield-check" class="h-4 w-4" />
                        </span>
                        <span class="text-[11px] font-black tracking-wide">ÔN THI 360</span>
                    </a>
                @endunless

                <p class="auth-eyebrow {{ $authCompact ? '' : 'mt-3' }} text-[10px] font-black uppercase tracking-[.16em] text-[#2D7FA3]">{{ $authEyebrow }}</p>
                <h1 class="mt-1 pr-9 font-black leading-tight tracking-tight text-[#123B68] {{ $authCompact ? 'text-base' : 'text-xl sm:text-2xl' }}">{{ $authTitle }}</h1>

                @yield('auth-content')
            </div>
        </section>
    </div>
</div>

@stack('scripts')
</body>
</html>
