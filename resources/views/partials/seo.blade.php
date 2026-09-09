@php
    $seoPrivate = $seoPrivate ?? false;
    $seoSite = 'Ôn Thi 360';
    // Số hiệu bộ icon = thời điểm sửa file favicon.svg. Trình duyệt cache favicon RẤT dai
    // (Ctrl/Cmd+Shift+R thường không ăn thua), nên gắn số này vào sau đường dẫn icon: cứ thay
    // file icon là đường dẫn tự đổi, trình duyệt buộc phải tải bản mới — khỏi phải nhớ sửa tay.
    $seoIconVersion = @filemtime(public_path('favicon.svg')) ?: '3';

    $seoPageTitle = trim((string) $__env->yieldContent('title'));
    $seoTitle = ($seoPageTitle !== '' && $seoPageTitle !== $seoSite)
        ? $seoPageTitle.' — '.$seoSite
        : $seoSite.' — Học và luyện thi trực tuyến';

    $seoDescription = trim((string) $__env->yieldContent('meta-description'));
    if ($seoDescription === '') {
        $seoDescription = 'Ôn Thi 360 — nền tảng học và luyện thi trực tuyến: khoá học theo môn và khối lớp, kho tài liệu, luyện tập theo từng câu hỏi biết đúng/sai ngay, cuộc thi và bảng xếp hạng.';
    }
    if (mb_strlen($seoDescription) > 165) {
        $seoDescription = rtrim(mb_substr($seoDescription, 0, 162), " \t\n\r\0\x0B.,;:-").'…';
    }

    $seoRobots = trim((string) $__env->yieldContent('meta-robots'));
    if ($seoPrivate) {
        $seoRobots = 'noindex, nofollow';
    } elseif ($seoRobots === '') {
        $seoRobots = 'index, follow, max-image-preview:large, max-snippet:-1';
    }

    $seoImage = trim((string) $__env->yieldContent('meta-image')) ?: asset('og-image.png');
    $seoUrl = url()->current();
@endphp
    <title>{{ $seoTitle }}</title>
    <meta name="robots" content="{{ $seoRobots }}">
@unless ($seoPrivate)
    <meta name="description" content="{{ $seoDescription }}">
    <link rel="canonical" href="{{ $seoUrl }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $seoSite }}">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoUrl }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $seoSite }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
@endunless

    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $seoIconVersion }}" sizes="16x16 24x24 32x32 48x48">
    {{-- 3 dòng PNG này là DÀNH CHO SAFARI: Safari không dùng được favicon SVG, mà thẻ .ico thì
         nó nhận rất thất thường -> đưa sẵn PNG khai rõ kích thước để Safari luôn có bản chắc
         chắn hiển thị được. Trình duyệt khác vẫn ưu tiên SVG ở dòng dưới (nét ở mọi cỡ). --}}
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}?v={{ $seoIconVersion }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}?v={{ $seoIconVersion }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('favicon-96.png') }}?v={{ $seoIconVersion }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ $seoIconVersion }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $seoIconVersion }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v={{ $seoIconVersion }}">
    <meta name="theme-color" content="#3b8ef7">

@if (! $seoPrivate && request()->routeIs('home'))
    {{-- Dữ liệu có cấu trúc: giúp Google hiểu đây là 1 tổ chức giáo dục và gắn được tên/logo
         site vào kết quả tìm kiếm. Chỉ gắn ở trang chủ để không khai trùng trên mọi trang.

         LƯU Ý khi sửa: mảng phải dựng sẵn ở khối php bên dưới rồi mới in ra bằng json_encode.
         KHÔNG viết mảng nhiều dòng thẳng vào directive (kiểu "json(...)" của Blade) — bộ biên
         dịch đọc tham số directive theo dấu ngoặc nên gặp mảng lồng nhiều dòng là hỏng, báo
         "Unclosed '[' ... does not match ')'". Cũng KHÔNG viết tên directive kèm dấu a-còng
         vào trong chú thích Blade: chú thích bị gỡ SAU khi directive đã được biên dịch, nên
         một chữ như vậy nằm trong chú thích vẫn bị dịch thành mã PHP và làm vỡ trang. --}}
    @php
        $seoHome = url('/');
        $seoJsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'EducationalOrganization',
                    '@id' => $seoHome.'#to-chuc',
                    'name' => $seoSite,
                    'url' => $seoHome,
                    'logo' => asset('icon-512.png'),
                    'description' => 'Nền tảng học và luyện thi trực tuyến: khoá học, tài liệu, luyện tập theo câu hỏi, cuộc thi và bảng xếp hạng.',
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $seoHome.'#website',
                    'url' => $seoHome,
                    'name' => $seoSite,
                    'inLanguage' => 'vi-VN',
                    'publisher' => ['@id' => $seoHome.'#to-chuc'],
                ],
            ],
        ];
    @endphp
    <script type="application/ld+json">
        {!! json_encode($seoJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}
    </script>
@endif
