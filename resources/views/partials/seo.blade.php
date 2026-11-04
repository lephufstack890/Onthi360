@php
    $seoPrivate = $seoPrivate ?? false;
    $seoSite = 'Ôn Thi 360';
    // Số hiệu bộ icon = thời điểm sửa file favicon.svg. Trình duyệt cache favicon RẤT dai
    // (Ctrl/Cmd+Shift+R thường không ăn thua), nên gắn số này vào sau đường dẫn icon: cứ thay
    // file icon là đường dẫn tự đổi, trình duyệt buộc phải tải bản mới — khỏi phải nhớ sửa tay.
    $seoIconVersion = @filemtime(public_path('favicon.svg')) ?: '3';

    /*
     * SỬA 12/9 — sửa lỗi tiêu đề tab hiện "Cuộc thi &amp; Đấu trường 360" thay vì "Cuộc thi &".
     *
     * Nguyên nhân: dạng @section('title', 'chuỗi') được Laravel escape SẴN ở tầng view
     * (ManagesLayouts::startSection() gọi e($content) khi truyền chuỗi), nên yieldContent()
     * trả về "Cuộc thi &amp; ...". Xuống dưới lại in bằng {{ }} nên bị escape lần thứ hai
     * thành "&amp;amp;" — trình duyệt hiển thị đúng chữ "&amp;".
     *
     * Cách xử lý: giải mã đúng MỘT lần ngay tại đây, rồi vẫn để {{ }} escape lại bình thường
     * khi in ra. Làm ở đây (chỗ duy nhất) nên mọi trang cùng được sửa, không phải đi bỏ dấu
     * "&" khỏi từng tiêu đề. Dấu nháy, dấu < > trong tiêu đề vẫn được escape an toàn.
     */
    $seoDecode = fn (string $value) => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $seoPageTitle = $seoDecode(trim((string) $__env->yieldContent('title')));
    // SỬA 27/10 — tiêu đề đã có sẵn tên thương hiệu "Onthi360" (trang chủ) thì giữ nguyên, không gắn thêm đuôi.
    // SỬA 27/10 — tên hiển thị khi chia sẻ link (Zalo / Facebook) và tên tổ chức trong dữ liệu Google.
    $seoBrand = 'Tin học Onthi360';
    // SỬA 1/11 (SEO: khách tìm "onthi360" trên Google không thấy) — đuôi tiêu đề các trang dùng đúng tên thương hiệu
    // "Tin học Onthi360" (trước là "Ôn Thi 360" — không chứa chữ "onthi360" người ta gõ tìm).
    // Trang tự ghi đuôi cũ "— Ôn Thi 360" (vd Lộ trình học) → bỏ đuôi đó để khỏi thành 2 tên thương hiệu.
    $seoPageTitle = trim((string) preg_replace('/\s*[—|-]\s*Ôn Thi 360\s*$/u', '', $seoPageTitle));
    $seoTitle = ($seoPageTitle !== '' && $seoPageTitle !== $seoSite)
        ? (str_contains(mb_strtolower($seoPageTitle), 'onthi360') ? $seoPageTitle : $seoPageTitle.' | '.$seoBrand)
        : $seoBrand.' — Học và luyện thi Tin học trực tuyến';
    $seoIsHome = request()->routeIs('home');

    $seoDescription = $seoDecode(trim((string) $__env->yieldContent('meta-description')));
    if ($seoDescription === '') {
        $seoDescription = 'Ôn Thi 360 — nền tảng học và luyện thi trực tuyến: khoá học theo môn và khối lớp, kho tài liệu, luyện tập theo từng câu hỏi biết đúng/sai ngay, cuộc thi và bảng xếp hạng.';
    }
    // SỬA 27/10 — trang chủ giữ đủ mô tả khách duyệt (Google / Zalo tự cắt phần dư khi hiển thị); trang khác giữ giới hạn 165.
    if (! $seoIsHome && mb_strlen($seoDescription) > 165) {
        $seoDescription = rtrim(mb_substr($seoDescription, 0, 162), " \t\n\r\0\x0B.,;:-").'…';
    }

    /*
     * SỬA 1/11 (SEO) — địa chỉ chuẩn (canonical) LUÔN theo tên miền trong APP_URL (https://tinhoc.onthi360.vn), không theo
     * người gọi. Trước đây lấy url()->current(): bot vào bằng IP máy chủ (https://36.50.177.27/...) thì trang tự khai
     * canonical là IP → Google thấy 2 bản trùng (IP và tên miền) và có thể chọn nhầm bản IP / bỏ qua tên miền.
     * Ai vào bằng địa chỉ khác tên miền chính (IP, tên miền phụ) → noindex, để Google chỉ giữ bản tên miền.
     */
    $seoRoot = rtrim((string) config('app.url'), '/');
    $seoRootHost = (string) parse_url($seoRoot, PHP_URL_HOST);
    if (! preg_match('#^https?://#i', $seoRoot) || $seoRootHost === '' || filter_var($seoRootHost, FILTER_VALIDATE_IP) || $seoRootHost === 'localhost') {
        $seoRoot = rtrim(url('/'), '/');
        $seoRootHost = '';
    }
    $seoPath = trim(request()->path(), '/');
    $seoUrl = $seoRoot.($seoPath === '' ? '/' : '/'.$seoPath);
    $seoWrongHost = $seoRootHost !== '' && strcasecmp(request()->getHost(), $seoRootHost) !== 0;

    $seoRobots = trim((string) $__env->yieldContent('meta-robots'));
    if ($seoPrivate) {
        $seoRobots = 'noindex, nofollow';
    } elseif ($seoRobots === '') {
        $seoRobots = 'index, follow, max-image-preview:large, max-snippet:-1';
    }
    if (! $seoPrivate && $seoWrongHost) {
        $seoRobots = 'noindex, follow';
    }

    // SỬA 27/10 — gắn ?v= theo thời điểm sửa ảnh: Zalo / Facebook nhớ ảnh xem trước theo đường dẫn, đổi ảnh là đổi đường dẫn.
    // SỬA 27/10 (lần 2) — Zalo vẫn hiện ảnh cũ dù đã thêm ?v= (Zalo nhớ ảnh theo tên file, bỏ qua phần ?v=) → dùng TÊN FILE MỚI.
    // Sau này đổi ảnh chia sẻ: đặt file với tên mới khác (vd og-tinhoc-onthi360-2.png) rồi sửa tên ở dòng dưới.
    $seoOgFile = 'og-tinhoc-onthi360-v2.png'; // v2: logo + màu thương hiệu (xanh #0057F5, cam 360)
    $seoImage = trim((string) $__env->yieldContent('meta-image')) ?: asset($seoOgFile).'?v='.(@filemtime(public_path($seoOgFile)) ?: '1');

    // SỬA 4/11 (SEO) — từ khoá (Cốc Cốc còn đọc, Google bỏ qua — không hại gì) + mã xác minh Search Console / Bing.
    $seoKeywords = $seoDecode(trim((string) $__env->yieldContent('meta-keywords')));
    $seoGoogleVerify = trim((string) config('app.google_site_verification'));
    $seoBingVerify = trim((string) config('app.bing_site_verification'));
@endphp
    <title>{{ $seoTitle }}</title>
    <meta name="robots" content="{{ $seoRobots }}">
@unless ($seoPrivate)
    <meta name="description" content="{{ $seoDescription }}">
    @if ($seoKeywords !== '')
    <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    <link rel="canonical" href="{{ $seoUrl }}">
    {{-- SỬA 4/11 (SEO) — khai rõ trang tiếng Việt cho người dùng Việt Nam. --}}
    <link rel="alternate" hreflang="vi-VN" href="{{ $seoUrl }}">
    <link rel="alternate" hreflang="x-default" href="{{ $seoUrl }}">
    <meta name="application-name" content="{{ $seoBrand }}">
    <meta name="apple-mobile-web-app-title" content="{{ $seoBrand }}">
    @if ($seoGoogleVerify !== '')
    <meta name="google-site-verification" content="{{ $seoGoogleVerify }}">
    @endif
    @if ($seoBingVerify !== '')
    <meta name="msvalidate.01" content="{{ $seoBingVerify }}">
    @endif

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $seoBrand }}">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoUrl }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:secure_url" content="{{ $seoImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $seoIsHome ? $seoTitle : $seoBrand }}">
    @if (str_contains($seoImage, '.png'))
    <meta property="og:image:type" content="image/png">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
    <meta name="twitter:image:alt" content="{{ $seoIsHome ? $seoTitle : $seoBrand }}">
@endunless

    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $seoIconVersion }}" sizes="16x16 24x24 32x32 48x48">
    {{-- 3 dòng PNG này là DÀNH CHO SAFARI: Safari không dùng được favicon SVG, mà thẻ .ico thì
         nó nhận rất thất thường -> đưa sẵn PNG khai rõ kích thước để Safari luôn có bản chắc
         chắn hiển thị được. Trình duyệt khác vẫn ưu tiên SVG ở dòng dưới (nét ở mọi cỡ). --}}
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}?v={{ $seoIconVersion }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}?v={{ $seoIconVersion }}">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon-48.png') }}?v={{ $seoIconVersion }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('favicon-96.png') }}?v={{ $seoIconVersion }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ $seoIconVersion }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $seoIconVersion }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v={{ $seoIconVersion }}">
    {{-- SỬA 11/9 — đổi theo đúng source giao diện khách gửi (education-main/index.html). --}}
    <meta name="theme-color" content="#126F91">

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
        $seoHome = $seoRoot.'/';
        $seoJsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'EducationalOrganization',
                    '@id' => $seoHome.'#to-chuc',
                    'name' => $seoBrand,
                    'alternateName' => ['Onthi360', 'Tin học 360', $seoSite, 'onthi360.vn'],
                    'url' => $seoHome,
                    'logo' => asset('icon-512.png'),
                    'description' => $seoDescription,
                    // SỬA 4/11 (SEO) — lĩnh vực của tổ chức, giúp Google gắn trang với các cụm tìm kiếm này.
                    'knowsAbout' => ['Tin học', 'Lập trình', 'Chấm code online', 'Lập trình thi đấu', 'Thuật toán', 'C++', 'Python',
                        'HSG Tin học lớp 9', 'HSG Tin học lớp 12', 'Thi tốt nghiệp THPT môn Tin học'],
                ],
                // SỬA 4/11 (khách: "SEO thêm chấm code online") — khai báo hệ thống chấm code online là một ứng dụng web giáo dục.
                [
                    '@type' => 'WebApplication',
                    '@id' => $seoHome.'#cham-code-online',
                    'name' => 'Chấm code online - '.$seoBrand,
                    'alternateName' => ['Chấm bài lập trình online', 'Online Judge Tin học Onthi360'],
                    'url' => route('practice.index'),
                    'applicationCategory' => 'EducationalApplication',
                    'operatingSystem' => 'Web',
                    'inLanguage' => 'vi-VN',
                    'description' => 'Chấm code online tự động bằng C++ và Python, có kết quả ngay theo từng test: bài tập thuật toán theo chuyên đề, đề luyện thi HSG Tin học lớp 9, lớp 12 và thi tốt nghiệp THPT môn Tin học.',
                    'featureList' => ['Chấm code C++ và Python tự động', 'Kết quả chấm theo từng test', 'Bài tập thuật toán theo chuyên đề', 'Đề thi luyện tập mô phỏng', 'Bảng xếp hạng'],
                    'provider' => ['@id' => $seoHome.'#to-chuc'],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $seoHome.'#website',
                    'url' => $seoHome,
                    'name' => $seoBrand,
                    'alternateName' => ['Onthi360', 'Tin học 360', $seoSite],
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
