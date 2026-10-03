{{--
  SỬA 27/8 ("giáo viên đọc tài liệu bị 403" / "xem học liệu đã gắn lớp như nào"): trang này
  giờ dùng CHUNG cho cả student.materials.read và teacher.materials.read (xem
  App\Http\Controllers\Teacher\MaterialController — cùng gọi MaterialReadService::
  buildReadData(), chỉ khác $routePrefix) — $layoutView/$readRoute do service đó truyền vào
  theo đúng vai trò đang gọi, mặc định giữ nguyên 'layouts.student'/'student.materials.read'
  như cũ nếu thiếu (không đổi gì cho luồng học sinh hiện có).
--}}
@extends($layoutView ?? 'layouts.student')

@section('title', $material->title)
@section('page-title', 'Đọc bài')

@section('content')
    @php
        $prev = $prev ?? null;
        $next = $next ?? null;
        $watermarkText = $watermarkText ?? '';
        $readRoute = $readRoute ?? 'student.materials.read';
    @endphp

    {{-- ═══════════════ MÀN ĐỌC TÀI LIỆU ═══════════════
         SỬA 18/9 (khách yêu cầu: "trang đọc tài liệu thì nó sẽ hiển thị UI đó cũng dựa vào
         source mới copy lại UI đó nha") — dựng lại phần vỏ theo
         education-main/src/components/MaterialReaderPage.jsx: thanh đầu trang dính, hai cột
         (PDF bên trái · Bài tập bên phải), thanh công cụ PDF, chế độ Tập trung.

         GIỮ NGUYÊN BỘ MÁY ĐỌC: toàn bộ <script type="module"> pdf.js ở dưới không đổi cách
         chạy. SỬA 3/10 — theo ảnh khách gửi, thanh công cụ đã BỎ 3 thẻ thu/phóng và dải tiến
         độ đọc; script vẫn an toàn vì mọi chỗ bám vào các id đó đều kiểm tra null trước khi
         dùng (zoomInBtn / zoomOutBtn / zoomLabelEl / progressBarEl / pageIndicatorEl). Thẻ
         BẮT BUỘC phải còn là #material-pdf-viewer và #reader-scroll-box.

         KHÁC bản mẫu, có chủ ý:
           · bản mẫu dựng trang tài liệu bằng chữ viết sẵn trong mã nguồn; ở đây là PDF THẬT
             của tài liệu, đọc qua pdf.js — nên phần ruột giữ nguyên bộ đọc cũ;
           · bản mẫu in "320 trang" ở thanh đầu; hệ thống không lưu số trang, pdf.js chỉ biết
             sau khi tải xong nên chỗ đó dùng chính ô chỉ số trang của bộ đọc. --}}
    <style>
        @media print {
            body * { visibility: hidden !important; }
        }

        .reader-shell {
            margin: -1rem;
            min-height: calc(100vh - 4rem);
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 55%, #f1f5f9 100%);
            display: flex;
            flex-direction: column;
        }
        @media (min-width: 1024px) {
            .reader-shell { margin: -1.5rem; }
        }

        .reader-topbar {
            position: sticky;
            top: 0;
            z-index: 30;
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-bottom: 1px solid #e2e8f0;
        }
        .reader-code-badge {
            display: inline-block;
            font-size: .65rem;
            font-weight: 600;
            letter-spacing: .02em;
            color: #e11d48;
            background: #fff1f2;
            border: 1px solid #ffe4e6;
            border-radius: 999px;
            padding: 2px 9px;
            margin-bottom: 4px;
        }

        .reader-scroll-area { flex: 1; padding: 28px 16px 120px 16px; }

        .reader-page-wrap {
            position: relative;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 16px 32px -12px rgba(15, 23, 42, .16);
            margin: 0 auto;
            overflow: hidden;
            opacity: 0;
            transform: translateY(10px);
            animation: reader-page-in .4s ease forwards;
        }
        @keyframes reader-page-in {
            to { opacity: 1; transform: translateY(0); }
        }
        .reader-page-number {
            text-align: center;
            font-size: .7rem;
            color: #94a3b8;
            margin: 10px auto 26px auto;
        }
        .reader-page-error {
            text-align: center;
            font-size: .75rem;
            color: #e11d48;
            background: #fff1f2;
            border: 1px solid #ffe4e6;
            border-radius: 12px;
            padding: 14px;
            margin: 0 auto 26px auto;
        }

        .reader-toolbar-dock {
            position: sticky;
            bottom: 18px;
            z-index: 30;
            display: flex;
            justify-content: center;
            pointer-events: none;
        }
        .reader-toolbar {
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            gap: 2px;
            background: rgba(255, 255, 255, .97);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            box-shadow: 0 10px 30px -8px rgba(15, 23, 42, .22);
            padding: 6px;
        }
        .reader-toolbar-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 999px;
            border: none;
            background: transparent;
            color: #475569;
            font-size: 1rem;
            line-height: 1;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s, color .15s;
        }
        .reader-toolbar-btn:hover { background: #fff1f2; color: #e11d48; }
        .reader-toolbar-btn[aria-disabled="true"] { opacity: .3; pointer-events: none; }
        .reader-toolbar-sep { width: 1px; height: 20px; background: #e2e8f0; margin: 0 5px; }
        .reader-page-indicator {
            font-size: .75rem;
            font-weight: 500;
            color: #475569;
            min-width: 78px;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }

        /* SỬA 3/10 (2) — MÀU NỀN/VIỀN CỦA THẺ BÀI TẬP.
           Đây là lỗi tôi để sót ở lượt trước: 10 class dưới đây không có trong bản CSS đã
           build, nên trên máy chủ mọi thẻ bài tập sẽ KHÔNG có nền màu nào — đúng cái việc mà
           "nền đổi theo trạng thái" sinh ra để làm.

           BÀI HỌC: TUYỆT ĐỐI không viết tên thẻ đóng của style ngay trong khối CSS này, kể cả
           bên trong dấu ghi chú — trình duyệt kết thúc khối style ở lần gặp ĐẦU TIÊN, không
           quan tâm nó nằm trong ghi chú hay không. Viết vào là nửa dưới của khối CSS bị đổ ra
           màn hình thành chữ. */
        .bg-\[\#F0F8F2\] { background-color: #F0F8F2; }
        .bg-\[\#FFF7E7\] { background-color: #FFF7E7; }
        .bg-\[\#EFF8FA\] { background-color: #EFF8FA; }
        .border-\[\#CBE7D5\] { border-color: #CBE7D5; }
        .border-\[\#F0D9A9\] { border-color: #F0D9A9; }
        .border-\[\#C8E2EA\] { border-color: #C8E2EA; }
        .text-\[\#946A28\] { color: #946A28; }
        .hover\:bg-\[\#E8F4EC\]:hover { background-color: #E8F4EC; }
        .hover\:bg-\[\#FFF1D5\]:hover { background-color: #FFF1D5; }
        .hover\:bg-\[\#E6F3F6\]:hover { background-color: #E6F3F6; }

        /* Viền thẻ ĐANG ĐƯỢC CHỌN. Phải viết bằng 2 lớp (.oi-ex-card.oi-ex-selected) chứ không
           dùng class màu của Tailwind: mấy quy tắc màu viền theo trạng thái ngay trên nằm TRONG
           tệp này, tức là đứng SAU bản CSS đã build, nên chúng sẽ đè mất màu viền xanh dù thẻ
           có class đó. Hai lớp thì trọng số cao hơn, thắng chắc. */
        .oi-ex-card.oi-ex-selected { border-color: #2D7FA3; }
        .max-h-\[420px\] { max-height: 420px; }

        /* SỬA 3/10 — thêm cho thanh đầu trang + thẻ bài tập dựng lại theo bản mẫu. */
        .isolate { isolation: isolate; }
        .min-h-\[74px\] { min-height: 74px; }
        .border-\[\#CFE5E5\] { border-color: #CFE5E5; }
        .ring-\[\#B9DDE8\] { --tw-ring-color: #B9DDE8; }

        /* BẪY: đổ bóng phải ghi qua biến --tw-shadow, KHÔNG ghi thẳng box-shadow. Tailwind v4
           gộp viền-quầng (ring) và bóng vào CÙNG một thuộc tính box-shadow bằng các biến CSS;
           ghi thẳng box-shadow là xoá luôn quầng ring của chính thẻ đó — thẻ bài tập đang chọn
           sẽ mất viền sáng mà không ai hiểu vì sao. */
        .grid-cols-\[minmax\(0\,1fr\)_auto\] { grid-template-columns: minmax(0, 1fr) auto; }
        .shadow-\[0_3px_10px_rgba\(45\,96\,145\,0\.08\)\] { --tw-shadow: 0 3px 10px rgba(45, 96, 145, .08); box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow); }

        /* ── Class Tailwind của bản mẫu mới CHƯA có trong CSS đã build ──────────────────
           SỬA 2/10 — máy chủ KHÔNG chạy được vite nên mọi class phải có sẵn trong
           public/build/assets/app-*.css (bản build 19/9). Mấy class dưới đây là màu/kích
           thước mới của bản mẫu MaterialReaderPage.jsx, không nằm trong bản build đó nên
           trên máy chủ sẽ không ra style nào — sao độ khó mất màu, nút Làm bài mất nền,
           hàng 3 cột của thẻ bài tập vỡ. Viết đúng quy tắc mà Tailwind sẽ sinh ra (tên chọn
           lọc y hệt, dấu [ ] # ( ) , . phải thoát bằng \), nên mã HTML không phải sửa và
           khi nào build lại được CSS thì đây chỉ còn là bản trùng vô hại.
           ĐÃ ĐỐI CHIẾU từng tên với app-BHdXR6Bc.css — chỉ liệt kê class THẬT SỰ thiếu. */
        .text-\[\#436F6B\] { color: #436F6B; }
        .text-\[\#526B7D\] { color: #526B7D; }
        .text-\[\#C7D2D9\] { color: #C7D2D9; }
        .text-\[\#D29A18\] { color: #D29A18; }
        .bg-\[\#368F72\] { background-color: #368F72; }
        .bg-\[\#E2EEEC\] { background-color: #E2EEEC; }
        .border-\[\#DDE7EA\] { border-color: #DDE7EA; }
        .pr-1 { padding-right: .25rem; }
        .z-40 { z-index: 40; }
        .w-\[260px\] { width: 260px; }
        .grid-cols-\[minmax\(0\,1\.1fr\)_minmax\(0\,1fr\)_minmax\(0\,\.8fr\)\] {
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr) minmax(0, .8fr);
        }
        .shadow-\[0_2px_7px_rgba\(67\,111\,107\,0\.12\)\] { --tw-shadow: 0 2px 7px rgba(67, 111, 107, .12); box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow); }
        .shadow-\[0_6px_18px_rgba\(45\,96\,145\,0\.12\)\] { --tw-shadow: 0 6px 18px rgba(45, 96, 145, .12); box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow); }
        .hover\:bg-\[\#2F8066\]:hover { background-color: #2F8066; }
        .hover\:bg-\[\#DFF2E9\]:hover { background-color: #DFF2E9; }
        .hover\:bg-\[\#EAF3F2\]:hover { background-color: #EAF3F2; }
        .hover\:border-\[\#B8D7E1\]:hover { border-color: #B8D7E1; }
        .hover\:text-\[\#436F6B\]:hover { color: #436F6B; }
    </style>
    @php
        $access = $access ?? ['owned' => false, 'remainingLabel' => null];
        $exercises = $exercises ?? [];
        $doneExercises = collect($exercises)->where('status', 'done')->count();
        $exercisePercent = count($exercises) > 0 ? (int) round($doneExercises / count($exercises) * 100) : 0;

        // View này dùng CHUNG cho student.materials.read và teacher.materials.read (xem
        // MaterialReadService::buildReadData()). Nút Quay lại/Đóng phải về đúng kho tài liệu của
        // vai trò đang xem, nếu không giáo viên bấm là văng sang route của học sinh -> 403.
        $libraryRoute = str_starts_with($readRoute, 'teacher.') ? 'teacher.library.index' : 'student.library.index';

        $sections = $sections ?? [];
    $attachments = $attachments ?? [];

    // Ảnh thu nhỏ ở thanh đầu: bìa sản phẩm chứa tài liệu; chưa có thì dùng ảnh nền chung của
    // khu Tài liệu chứ KHÔNG mượn ảnh của tài liệu khác.
    $readerCoverUrl = $material->product?->cover_image_path
        ? asset('storage/'.$material->product->cover_image_path)
        : asset('assets/hero-materials.jpg');

        /*
         * SỬA 2/10 (khách: "update lại cho tôi UI khi click vào đọc tài liệu dựa vào source mới")
         * — thẻ bài tập dựng lại theo education-main/src/components/MaterialReaderPage.jsx
         * (hàm ExerciseItem): NỀN ĐỔI THEO TRẠNG THÁI, 5 sao độ khó, nút "Làm bài" chiếm trọn
         * bề ngang. Bản cũ mọi thẻ đều một màu vàng kem nên nhìn lướt không biết bài nào xong.
         *
         * Bản mẫu có thêm trạng thái "locked" (bài bị khoá). Hệ thống này KHÔNG khoá bài tập
         * theo tiến độ — vào được trang đọc là làm được mọi bài — nên bỏ hẳn nhánh đó thay vì
         * vẽ ổ khoá không bao giờ sáng.
         */
        $statusMeta = [
            'done' => ['Đã làm', 'text-[#287B5F]', 'check-circle-2', 'border-[#CBE7D5] bg-[#F0F8F2] hover:bg-[#E8F4EC]'],
            'progress' => ['Đang làm', 'text-[#946A28]', 'play-circle', 'border-[#F0D9A9] bg-[#FFF7E7] hover:bg-[#FFF1D5]'],
            'open' => ['Chưa làm', 'text-[#126F91]', 'play-circle', 'border-[#C8E2EA] bg-[#EFF8FA] hover:bg-[#E6F3F6]'],
        ];

        // Hàng dữ liệu cho Alpine: lọc theo ô tìm + 3 chip, rồi chia 3 bài/trang đúng bản mẫu.
        $exerciseRows = [];
        foreach ($exercises as $ex) {
            $exerciseRows[] = [
                'id' => $ex['id'],
                'done' => $ex['status'] === 'done',
                'search' => mb_strtolower($ex['title'].' '.implode(' ', $ex['tags'])),
            ];
        }
    @endphp

    <div x-data="onthiMaterialReader({{ Js::from(['exercises' => $exerciseRows, 'perPage' => 3]) }})"
         class="bg-[#F7F9FB] text-[#466278]">

        {{-- ══════ THANH ĐẦU TRANG ══════ --}}
        {{-- SỬA 3/10 (khách: "UI chỗ đọc tài liệu nó không giống, check source mới kỹ") — thanh
             đầu trang dựng lại đúng bản mẫu: nền trang trí (ảnh hero mờ + 2 quầng màu), ảnh thu
             nhỏ của tài liệu, và dòng chip có SỐ TRANG PDF. --}}
        <header x-show="! focus"
                class="sticky top-0 z-30 isolate relative min-h-[74px] overflow-hidden border-b border-[#CFE5E5] bg-white/95 shadow-[0_3px_14px_rgba(45,96,145,0.07)] backdrop-blur-xl">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 z-0 overflow-hidden">
                <img src="{{ asset('assets/hero-materials.jpg') }}" alt="" loading="eager" decoding="async"
                     class="absolute inset-0 h-full w-full object-cover object-right" style="opacity: .48">
                <div class="absolute inset-0" style="background-image: linear-gradient(to right, rgba(255,255,255,.98), rgba(255,255,255,.86), rgba(255,255,255,.16))"></div>
                <div class="absolute -right-20 -top-28 h-64 w-64 rounded-full blur-3xl" style="background-color: rgba(189,234,222,.58)"></div>
                <div class="absolute -bottom-24 h-48 w-48 rounded-full blur-3xl" style="right: 24%; background-color: rgba(255,228,167,.38)"></div>
            </div>
            <div class="relative z-10 h-[3px] bg-gradient-to-r from-[#123B68] via-[#2D7FA3] to-[#E6B44A]" aria-hidden="true"></div>
            <div class="relative z-10 mx-auto flex w-full max-w-[1780px] flex-wrap items-center gap-3 px-3 py-2.5 sm:px-5 sm:py-3 lg:px-6 2xl:px-10">
                <a href="{{ route($libraryRoute) }}" aria-label="Quay lại kho tài liệu"
                   class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-[#DDEAF0] bg-white text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#123B68]">
                    <x-lucide name="arrow-left" class="h-4 w-4" />
                </a>
                <div class="hidden h-6 w-px bg-slate-200 sm:block"></div>

                <img src="{{ $readerCoverUrl }}" alt="" decoding="async"
                     class="h-9 w-9 shrink-0 rounded-lg border border-[#DDEAF0] bg-white object-cover">

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-[#EAF5F8] px-2.5 py-1 text-[10px] font-extrabold text-[#126F91]">
                            <x-lucide name="book-open" class="h-3 w-3" />Đang đọc tài liệu
                        </span>
                        {{-- Số trang do pdf.js đếm được sau khi tải xong, script điền vào đây. --}}
                        <span id="reader-page-count" class="hidden text-[11px] text-[#61798B] sm:inline"></span>
                        <span class="hidden rounded-lg bg-[#F8FBFE] px-2 py-1 font-mono text-[10px] font-bold text-[#61798B] lg:inline-flex">{{ $material->code ?: '#'.$material->id }}</span>
                    </div>
                    <h1 class="mt-1 truncate text-[14px] font-semibold text-[#123B68] sm:text-base">{{ $material->title }}</h1>
                </div>

                @if ($access['owned'])
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-[#B7DDCD] bg-[#DFF2E9] px-2.5 py-1.5 text-[11px] font-semibold text-[#287B5F]" title="Đã sở hữu">
                        <x-lucide name="check-circle-2" class="h-3 w-3" /><span>Đã sở hữu</span>
                    </span>
                    @if ($access['remainingLabel'])
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#F2E1B6] bg-[#FFF7E3] px-2.5 py-1.5 text-[11px] font-semibold text-[#8E6B2E]" title="Thời hạn còn lại">
                            <x-lucide name="clock" class="h-3 w-3" /><span>{{ $access['remainingLabel'] }}</span>
                        </span>
                    @endif
                @endif

                <a href="{{ route($libraryRoute) }}" aria-label="Đóng trình đọc"
                   class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-slate-400 transition hover:bg-[#FFF1F1] hover:text-[#B42318]">
                    <x-lucide name="x" class="h-4 w-4" />
                </a>
            </div>
        </header>

        <button type="button" @click="focus = false" x-show="focus" x-cloak
                class="fixed right-4 top-4 z-40 inline-flex items-center gap-1.5 rounded-xl border border-[#B7DDCD] bg-white/95 px-3 py-2 text-[12px] font-semibold text-[#287B5F] shadow-[0_6px_18px_rgba(45,96,145,0.12)] backdrop-blur transition hover:bg-[#DFF2E9]">
            <x-lucide name="minimize-2" class="h-3.5 w-3.5" /><span>Khôi phục</span>
        </button>

        <main class="mx-auto w-full max-w-[1780px] px-3 py-4 sm:px-5 lg:px-6 lg:py-6 2xl:px-10">
            <div class="grid items-stretch gap-3 lg:grid-cols-[minmax(0,1fr)_340px]">

                {{-- ══════ CỘT TRÁI: PDF ══════ --}}
                <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[28px] border border-[#D5E8ED] bg-[#F2F8FA] shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                    {{-- ══ THANH CÔNG CỤ ══
                         SỬA 2/10 — dựng theo thanh công cụ của bản mẫu mới: ô nhảy tới trang
                         ("Trang [n] / N" + nút mũi tên) và ô chọn Chương/phần, thay cho dòng chữ
                         "Trang X / Y" chỉ để đọc. Vẫn giữ nguyên 3 thẻ zoom mà script pdf.js bám
                         vào, bản mẫu không có nhưng đây là thứ người đọc dùng thật. --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#E5EEF3] px-3 py-2 sm:px-4">
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#E9F7F8] text-[#23869B]"><x-lucide name="file-text" class="h-3.5 w-3.5" /></span>

                            <form id="reader-page-form" class="flex items-center gap-1 text-[10px]" aria-label="Chuyển đến trang">
                                <label for="reader-page-input" class="text-[#61798B]">Trang</label>
                                <input id="reader-page-input" type="number" min="1" inputmode="numeric" value="1" aria-label="Số trang"
                                       class="h-6 w-12 rounded-md border border-[#DDEAF0] bg-[#F8FBFE] text-center text-[10px] font-bold text-[#123B68] outline-none transition focus:border-[#2D7FA3] focus:ring-2 focus:ring-[#DDF1F6]">
                                <span class="text-[#61798B]">/ <span id="reader-page-total">…</span></span>
                                <button type="submit" aria-label="Đi đến trang" title="Đi đến trang"
                                        class="grid h-6 w-6 place-items-center rounded-md bg-[#2F9E72] text-white transition hover:bg-[#278761]">
                                    <x-lucide name="arrow-right" class="h-3 w-3" />
                                </button>
                            </form>

                            @if (count($sections) > 0)
                                {{-- Mỗi chương/phần ở đây là MỘT tài liệu có PDF riêng (khác bản
                                     mẫu: bên đó là các trang trong cùng một tệp), nên chọn xong
                                     là chuyển sang trang đọc của chương đó.

                                     SỬA 3/10 (khách: "thiếu chương/phần và chỗ chọn chương") —
                                     trước đây chỉ hiện khi tài liệu có TỪ 2 CHƯƠNG TRỞ LÊN, nên
                                     tài liệu một chương là mất hẳn ô này. Bản mẫu luôn có ô đó,
                                     và người đọc cũng cần biết mình đang ở chương nào. --}}
                                <label for="material-section-select" class="shrink-0 text-[12px] font-semibold text-[#466278]">Chương/phần</label>
                                <select id="material-section-select" aria-label="Chọn chương hoặc phần để đọc"
                                        onchange="if (this.value) window.location.href = this.value;"
                                        class="h-9 w-[260px] max-w-full rounded-lg border border-[#DDEAF0] bg-[#F8FBFE] px-3 text-[13px] font-semibold text-[#123B68] outline-none transition focus:border-[#2D7FA3] focus:ring-2 focus:ring-[#DDF1F6]">
                                    @foreach ($sections as $sec)
                                        <option value="{{ $sec['href'] }}" @selected($sec['current'])>{{ $sec['no'] }}. {{ $sec['title'] }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        {{-- SỬA 3/10 — bên phải CHỈ còn nút Tập trung, đúng ảnh khách gửi. Đã BỎ
                             3 thẻ thu/phóng và dải tiến độ đọc: ảnh không có chúng. Mã JS vẫn an
                             toàn vì các chỗ bám vào đều kiểm tra null trước (xem cuối tệp). --}}
                        <button type="button" @click="focus = true" x-show="! focus"
                                class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-xl border border-[#DDEAF0] bg-[#F8FBFE] px-3 text-[11px] font-semibold text-[#2D7FA3] transition hover:border-[#B8D7E1] hover:bg-[#EAF5F8] hover:text-[#126F91]">
                            <x-lucide name="maximize-2" class="h-3.5 w-3.5" /><span>Tập trung</span>
                        </button>
                    </div>

                    {{-- SỬA 3/10 — nền vùng đọc màu #EAF4F8 và bỏ dòng nhắc "chỉ xem trên web" ở
                         ĐẦU (bản mẫu không có, nó đẩy tờ đề tụt xuống). Dòng đó chuyển xuống
                         chân khung — vẫn nói, nhưng không chắn chỗ đọc. --}}
                    <div class="bg-[#EAF4F8] p-3 sm:p-5">
                        {{-- SỬA 18/9 (khách: "chỗ hiển thị pdf dài quá thì cho scroll") — vùng đọc có
                             THANH CUỘN RIÊNG, chép đúng lớp của bản mẫu (MaterialReaderPage.jsx:
                             material-pdf-scroll + h-[min(760px,calc(100dvh-185px))]). Trước đây cả
                             trang cuộn nên cột Bài tập bên phải bị kéo đi mất. --}}
                        <div id="reader-scroll-box" role="region" tabindex="0" aria-label="Vùng đọc PDF"
                             class="material-pdf-scroll h-[min(760px,calc(100dvh-185px))] min-h-[520px] overflow-y-auto rounded-2xl border border-[#D6E2EA] bg-[#EAF4F8] p-2 sm:min-h-[680px] sm:p-3">
                        <div id="material-pdf-viewer" data-pdf-url="{{ $pdfUrl }}" data-watermark="{{ $watermarkText }}" class="select-none">
                            <div class="flex items-center justify-center py-16 text-[13px] text-slate-400">
                                <span class="mr-2 inline-block h-5 w-5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600"></span>
                                Đang tải nội dung…
                            </div>
                        </div>
                        </div>

                        <p class="mt-2.5 select-none text-center text-[11px] text-slate-400">
                            <x-lucide name="lock" class="inline h-3 w-3 shrink-0 align-[-2px]" /> Nội dung chỉ xem trên web — không hỗ trợ tải về hoặc in trực tiếp.
                        </p>
                    </div>

                    {{-- Điều hướng BÀI trước/sau trong cùng sản phẩm — giữ nguyên đường đi cũ. --}}
                    @if ($prev || $next)
                        <div class="flex items-center justify-between gap-2 border-t border-[#E5EEF3] bg-white px-3 py-2.5 sm:px-4">
                            @if ($prev)
                                <a href="{{ route($readRoute, $prev->id) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-[#DDEAF0] bg-white px-3 py-1.5 text-[12px] font-semibold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                    <x-lucide name="chevron-left" class="h-3.5 w-3.5" />Bài trước
                                </a>
                            @else
                                <span></span>
                            @endif
                            @if ($next)
                                <a href="{{ route($readRoute, $next->id) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-[#DDEAF0] bg-white px-3 py-1.5 text-[12px] font-semibold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                    Bài sau<x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                                </a>
                            @endif
                        </div>
                    @endif
                </section>

                {{-- ══════ CỘT PHẢI: BÀI TẬP ══════ --}}
                <aside class="flex h-full flex-col lg:sticky" :class="focus ? 'lg:top-4' : 'lg:top-[76px]'">
                    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[28px] border border-[#D5EAD9] bg-[#F1FAF5] shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                        <div class="border-b border-[#E5EEF3] px-3 py-3 sm:px-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 text-[#126F91]" title="Bài tập gắn với tài liệu">
                                    <x-lucide name="target" class="h-4 w-4" /><span class="text-xs font-semibold">Bài tập</span>
                                </div>
                                <span class="rounded-xl bg-[#EAF5F8] px-2.5 py-1.5 text-[10px] font-semibold text-[#126F91]" title="Tiến độ hoàn thành">{{ $doneExercises }}/{{ count($exercises) }}</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[#EAF0F5]">
                                <div class="h-full rounded-full bg-gradient-to-r from-[#2F9E72] to-[#68C69A]" style="width: {{ $exercisePercent }}%"></div>
                            </div>
                        </div>

                        <div class="flex min-h-0 flex-1 flex-col space-y-2.5 p-3.5 sm:p-4">
                            <div class="relative">
                                <x-lucide name="search" class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#9AAEBC]" />
                                <input x-model="q" placeholder="Tìm bài tập, hashtag..." aria-label="Tìm bài tập"
                                       class="w-full rounded-xl border border-[#DDEAF0] bg-[#F8FAFB] py-2.5 pl-9 pr-3 text-[11px] text-[#183D5E] outline-none transition placeholder:text-[#9AAEBC] focus:border-[#2D7FA3] focus:ring-2 focus:ring-[#DDF1F6]">
                            </div>

                            <div class="flex gap-1 rounded-xl border border-[#DCE9EE] bg-[#F2F6F8] p-1">
                                @foreach ([['all', 'Tất cả', 'filter'], ['todo', 'Chưa xong', 'play-circle'], ['done', 'Đã xong', 'check-circle-2']] as [$fKey, $fLabel, $fIcon])
                                    <button type="button" @click="filter = '{{ $fKey }}'"
                                            class="flex-1 rounded-lg px-2 py-2 text-[10px] font-bold transition"
                                            :class="filter === '{{ $fKey }}' ? 'bg-[#E2EEEC] text-[#436F6B] shadow-[0_2px_7px_rgba(67,111,107,0.12)]' : 'text-[#61798B] hover:bg-white hover:text-[#436F6B]'">
                                        <x-lucide :name="$fIcon" class="mx-auto h-3.5 w-3.5 sm:mr-1.5 sm:inline" /><span class="hidden sm:inline">{{ $fLabel }}</span>
                                    </button>
                                @endforeach
                            </div>

                            {{-- flex-col chứ không phải space-y: thuộc tính order (dùng để xếp
                                 lại thứ tự sau khi lọc/phân trang) chỉ có tác dụng với con của
                                 flex/grid. --}}
                            <div class="flex min-h-0 flex-1 flex-col gap-2.5 overflow-y-auto pr-0.5">
                                @forelse ($exercises as $ex)
                                    @php
                                        [$exStatusLabel, $exStatusClass, $exStatusIcon, $exSurface] = $statusMeta[$ex['status']] ?? $statusMeta['open'];
                                    @endphp
                                    {{-- SỬA 3/10 — thẻ bài tập soát lại theo ĐÚNG hàm ExerciseItem của
                                         bản mẫu, 3 chỗ trước đây làm thiếu:
                                           · chiều cao CỐ ĐỊNH 104px, các thẻ đều nhau tăm tắp;
                                           · bài ĐANG LÀM thì KHÔNG hiện viên trạng thái, hàng giữa
                                             rút còn 2 cột (sao + điểm) — xem ảnh khách gửi, thẻ
                                             "Luồng cực đại với Dinic" không có viên nào;
                                           · bấm vào tên bài thì thẻ được CHỌN (viền xanh + quầng). --}}
                                    <div x-show="visibleIds.includes({{ $ex['id'] }})" x-cloak
                                         :style="{ order: visibleIds.indexOf({{ $ex['id'] }}) }"
                                         :class="selectedId === {{ $ex['id'] }} ? 'oi-ex-selected ring-2 ring-[#B9DDE8] shadow-[0_3px_10px_rgba(45,96,145,0.08)]' : ''"
                                         class="oi-ex-card flex h-[104px] w-full shrink-0 flex-col justify-between gap-1.5 rounded-2xl border px-3 py-2.5 text-left transition {{ $exSurface }}">
                                        <button type="button" @click="selectedId = {{ $ex['id'] }}"
                                                class="block w-full min-w-0 truncate text-left text-[13px] font-semibold leading-5 text-[#123B68]" title="{{ $ex['title'] }}">{{ $ex['title'] }}</button>

                                        <div class="grid w-full items-center gap-2 {{ $ex['status'] === 'progress' ? 'grid-cols-[minmax(0,1fr)_auto]' : 'grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_minmax(0,.8fr)]' }}">
                                            @if ($ex['status'] !== 'progress')
                                                <span class="inline-flex min-w-0 items-center justify-center gap-1 rounded-md px-1.5 py-1 text-[9px] font-bold leading-none {{ $exStatusClass }}" title="{{ $exStatusLabel }}">
                                                    <x-lucide :name="$exStatusIcon" class="h-2.5 w-2.5 shrink-0" /><span class="truncate">{{ $exStatusLabel }}</span>
                                                </span>
                                            @endif

                                            <span class="inline-flex items-center justify-center gap-0.5" role="img"
                                                  aria-label="Độ khó {{ $ex['difficultyStars'] }} trên 5 sao" title="{{ $ex['difficultyLabel'] }}">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    {{-- Tô đặc bằng style chứ không bằng thuộc tính fill: thẻ <svg>
                                                         của x-lucide đã có sẵn fill="none" đứng TRƯỚC, mà HTML lấy
                                                         thuộc tính trùng tên ĐẦU TIÊN — truyền fill vào là mất tác
                                                         dụng, sao nào cũng rỗng. CSS fill thì đè được. --}}
                                                    <x-lucide name="star" class="h-3.5 w-3.5 {{ $i <= $ex['difficultyStars'] ? 'text-[#D29A18]' : 'text-[#C7D2D9]' }}"
                                                              style="{{ $i <= $ex['difficultyStars'] ? 'fill: currentColor' : '' }}" />
                                                @endfor
                                            </span>

                                            <span class="justify-self-end whitespace-nowrap pr-1 text-right text-[10px] font-semibold text-[#526B7D]">{{ $ex['points'] }} điểm</span>
                                        </div>

                                        {{-- Giữ NGUYÊN đường đi cũ của nút Làm bài ở "Tài liệu của tôi":
                                             POST kèm return_url tương đối để làm xong quay lại đúng trang này. --}}
                                        <form method="POST" action="{{ route('student.practiceByQuestion.startExercise', $ex['id']) }}" class="w-full">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->getRequestUri() }}">
                                            <button type="submit" title="Làm bài" aria-label="Làm bài {{ $ex['title'] }}"
                                                    class="inline-flex h-8 w-full items-center justify-center gap-1.5 rounded-lg bg-[#368F72] px-3 text-[11px] font-bold text-white transition hover:bg-[#2F8066] active:scale-[0.98]">
                                                <x-lucide name="play-circle" class="h-3.5 w-3.5" /><span>Làm bài</span>
                                            </button>
                                        </form>
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-[#C9DFE8] bg-[#F8FBFE] p-6 text-center text-[11px] text-[#61798B]">
                                        Tài liệu này chưa gắn bài tập nào.
                                    </div>
                                @endforelse

                                @if (count($exercises) > 0)
                                    <div x-show="filtered.length === 0" x-cloak
                                         class="rounded-2xl border border-dashed border-[#C9DFE8] bg-[#F8FBFE] p-6 text-center text-[11px] text-[#61798B]">
                                        Không tìm thấy bài tập phù hợp.
                                    </div>
                                @endif
                            </div>

                            {{-- Phân trang 3 bài/trang đúng bản mẫu mới. Trước đây danh sách chỉ
                                 cuộn, sản phẩm có vài chục bài là cuộn mỏi tay. --}}
                            <div x-show="pageCount > 1" x-cloak class="flex shrink-0 items-center justify-between gap-2 border-t border-[#DCE9EE] pt-2">
                                <button type="button" @click="page = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" aria-label="Trang trước"
                                        class="grid h-8 w-8 place-items-center rounded-lg border border-[#DDE7EA] bg-white text-[#526B7D] transition hover:bg-[#EAF3F2] disabled:cursor-not-allowed disabled:opacity-40">
                                    <x-lucide name="arrow-left" class="h-3.5 w-3.5" />
                                </button>
                                <span class="text-[10px] font-semibold text-[#61798B]">Trang <span x-text="currentPage"></span> / <span x-text="pageCount"></span></span>
                                <button type="button" @click="page = Math.min(pageCount, currentPage + 1)" :disabled="currentPage === pageCount" aria-label="Trang tiếp theo"
                                        class="grid h-8 w-8 place-items-center rounded-lg border border-[#DDE7EA] bg-white text-[#526B7D] transition hover:bg-[#EAF3F2] disabled:cursor-not-allowed disabled:opacity-40">
                                    <x-lucide name="arrow-right" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </section>

                    @if (count($attachments) > 0)
                        {{-- ══════ HỌC LIỆU ══════
                             SỬA 3/10 (khách: "2 cái phải đồng bộ") — khu học sinh trước đây mở
                             màn đọc sản phẩm riêng, ở đó có tab "Học liệu" (audio/ảnh của từng
                             chương + tệp đính kèm). Giờ hai khu dùng CHUNG màn này, nên mang
                             mục đó sang — gộp màn mà không ai mất thứ đang dùng.

                             Để dạng gấp/mở và CHỈ hiện khi tài liệu thật sự có học liệu, nhờ
                             vậy tài liệu không có gì thì bố cục vẫn đúng bản mẫu. --}}
                        <section x-data="{ open: false }"
                                 class="mt-3 overflow-hidden rounded-[28px] border border-[#DDEAF0] bg-white shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                            <button type="button" @click="open = ! open" :aria-expanded="open"
                                    class="flex w-full items-center justify-between gap-3 px-3 py-3 text-left sm:px-4">
                                <span class="flex items-center gap-2 text-[#126F91]">
                                    <x-lucide name="library" class="h-4 w-4" /><span class="text-xs font-semibold">Học liệu</span>
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="rounded-xl bg-[#EAF5F8] px-2.5 py-1.5 text-[10px] font-semibold text-[#126F91]">{{ count($attachments) }}</span>
                                    <x-lucide name="chevron-down" class="h-4 w-4 text-[#9AAEBC] transition" ::class="open ? 'rotate-180' : ''" />
                                </span>
                            </button>

                            <div x-show="open" x-cloak class="max-h-[420px] overflow-y-auto border-t border-[#E5EEF3] p-3.5 sm:p-4">
                                @foreach ($attachments as $item)
                                    <div class="mb-2.5 rounded-2xl border border-[#DDEAF0] bg-[#F8FBFC] p-3">
                                        <div class="flex items-start gap-2">
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#E9F7F8] text-[#23869B]">
                                                <x-lucide :name="$item['kind'] === 'audio' ? 'volume-2' : ($item['kind'] === 'image' ? 'image' : 'paperclip')" class="h-3.5 w-3.5" />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-[12.5px] font-semibold text-[#123B68]">{{ $item['title'] }}</p>
                                                @if ($item['chapterTitle'])
                                                    <p class="truncate text-[10.5px] text-[#7FA5B8]">{{ $item['chapterTitle'] }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        @if ($item['kind'] === 'audio')
                                            {{-- Nghe ngay trong trang (bài nghe-hiểu), không phải tải về. --}}
                                            <audio controls preload="none" src="{{ $item['url'] }}" class="mt-2 w-full"></audio>
                                        @elseif ($item['kind'] === 'image')
                                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="mt-2 block overflow-hidden rounded-xl border border-[#DDEAF0]">
                                                <img src="{{ $item['url'] }}" alt="{{ $item['title'] }}" loading="lazy" class="w-full">
                                            </a>
                                        @else
                                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener"
                                               class="mt-2 inline-flex items-center gap-1.5 rounded-xl border border-[#DDEAF0] bg-white px-3 py-1.5 text-[11.5px] font-semibold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                                <x-lucide name="download" class="h-3.5 w-3.5" />Mở tệp
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </aside>
            </div>
        </main>
    </div>
@endsection

@push('scripts')
    {{-- SỬA 2/10 — bộ lọc + PHÂN TRANG danh sách bài tập (3 bài/trang) theo bản mẫu mới
         education-main/src/components/MaterialReaderPage.jsx. Trước đây danh sách chỉ lọc rồi
         cuộn; sản phẩm có vài chục bài là cuộn mỏi tay.

         Thẻ bài tập vẫn do máy chủ dựng sẵn (giữ nguyên nút Làm bài dạng form POST có CSRF);
         phần việc ở đây chỉ là quyết định thẻ nào HIỆN và xếp theo thứ tự nào — cùng cách
         partials/practice-page-script đang làm cho kho bài tập. --}}
    <script>
        function onthiMaterialReader(config) {
            return {
                exercises: config.exercises || [],
                perPage: config.perPage || 3,

                focus: false,
                q: '',
                filter: 'all',
                page: 1,
                // SỬA 3/10 — thẻ bài tập đang được chọn (bản mẫu: bấm tên bài thì thẻ sáng viền).
                selectedId: null,

                get filtered() {
                    const needle = this.q.trim().toLowerCase();

                    return this.exercises.filter((item) => {
                        const matchFilter = this.filter === 'all'
                            || (this.filter === 'done' ? item.done : ! item.done);

                        return matchFilter && (! needle || item.search.includes(needle));
                    });
                },

                get pageCount() {
                    return Math.max(1, Math.ceil(this.filtered.length / this.perPage));
                },

                // Trang hiện tại không bao giờ vượt quá tổng số trang sau khi lọc.
                get currentPage() {
                    return Math.min(this.page, this.pageCount);
                },

                get visibleIds() {
                    const start = (this.currentPage - 1) * this.perPage;

                    return this.filtered.slice(start, start + this.perPage).map((item) => item.id);
                },

                init() {
                    // Đổi bộ lọc hay gõ tìm kiếm thì về trang 1, nếu không người dùng đang ở
                    // trang 3 mà lọc còn 2 bài sẽ thấy danh sách trống không hiểu vì sao.
                    this.$watch('q', () => { this.page = 1; });
                    this.$watch('filter', () => { this.page = 1; });
                },
            };
        }
    </script>

    {{--
      SỬA 25/8 (5) — "không tải được bộ đọc nội dung" (KHÔNG PHẢI lỗi trang lẻ đã sửa ở SỬA
      25/8 (3), mà pdf.js CHƯA HỀ tải được — đã xác minh trực tiếp bằng `npm pack
      pdfjs-dist@4.0.379`: bản 4.0.379 KHÔNG CÒN file .js (UMD/gán window.pdfjsLib) nào nữa —
      kể cả thư mục legacy/build/ cũng chỉ có .mjs (ES module): legacy/build/pdf.min.mjs,
      legacy/build/pdf.worker.min.mjs. SỬA 25/8 (4) trước đó đổi đúng CDN (jsdelivr) nhưng vẫn
      trỏ nhầm đuôi .js (không tồn tại) nên vẫn 404 — trình duyệt nhận về trang lỗi 404 với
      Content-Type không phải JS, bị "X-Content-Type-Options: nosniff" chặn thực thi.
      Fix ĐÚNG: dùng `<script type="module">` + `import()` động trỏ thẳng vào file .mjs thật —
      cách dùng CHÍNH THỨC cho pdf.js từ v4.x khi nhúng qua CDN không qua bundler.

      SỬA 25/8 (6) — thêm cho giao diện mới: theo dõi cuộn trang để cập nhật thanh tiến độ
      đọc + "Trang X / Y" ở thanh công cụ nổi, và 2 nút phóng to/thu nhỏ (render lại đúng
      trang đang xem ở tỉ lệ mới, không nhảy về đầu tài liệu).
    --}}
    <script type="module">
        (async function () {
            var container = document.getElementById('material-pdf-viewer');
            if (!container) {
                return;
            }

            var pdfjsLib;
            try {
                pdfjsLib = await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/legacy/build/pdf.min.mjs');
            } catch (err) {
                console.error('Không tải được thư viện pdf.js:', err);
                container.innerHTML = '<p class="text-center text-[13px] text-blue-500 py-10">Không tải được thư viện đọc PDF — vui lòng kiểm tra kết nối mạng rồi tải lại trang.</p>';
                return;
            }

            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/legacy/build/pdf.worker.min.mjs';

            var pdfUrl = container.dataset.pdfUrl;
            var watermarkText = container.dataset.watermark || '';

            var progressBarEl = document.getElementById('reader-progress-bar');
            var pageIndicatorEl = document.getElementById('reader-page-indicator');
            var pageInputEl = document.getElementById('reader-page-input');
            var pageTotalEl = document.getElementById('reader-page-total');
            var pageFormEl = document.getElementById('reader-page-form');
            var pageCountEl = document.getElementById('reader-page-count');
            var zoomInBtn = document.getElementById('reader-zoom-in');
            var zoomOutBtn = document.getElementById('reader-zoom-out');
            var zoomLabelEl = document.getElementById('reader-zoom-label');

            var SCALE_DEFAULT = 1.4, SCALE_MIN = 0.8, SCALE_MAX = 2.4, SCALE_STEP = 0.2;
            var scale = SCALE_DEFAULT;
            var pdfDoc = null;
            var totalPages = 0;
            var currentPage = 1;
            var pageWrappers = [];

            // Chặn menu chuột phải (Lưu ảnh/Save as...) và các phím tắt tải/in phổ biến. Đây
            // là hàng rào cho người dùng thông thường — KHÔNG chặn được người biết dùng công cụ
            // lập trình viên của trình duyệt, và KHÔNG chặn được chụp màn hình dưới mọi hình
            // thức (giới hạn chung của mọi trình duyệt/hệ điều hành, đã báo trước với khách).
            container.addEventListener('contextmenu', function (e) { e.preventDefault(); });
            document.addEventListener('keydown', function (e) {
                var key = (e.key || '').toLowerCase();
                if ((e.ctrlKey || e.metaKey) && (key === 'p' || key === 's')) {
                    e.preventDefault();
                }
            });

            function buildWatermarkOverlay(width, height) {
                var overlay = document.createElement('div');
                overlay.style.position = 'absolute';
                overlay.style.inset = '0';
                overlay.style.overflow = 'hidden';
                overlay.style.pointerEvents = 'none';

                if (!watermarkText) {
                    return overlay;
                }

                var cols = 3;
                var rows = 6;
                for (var r = 0; r < rows; r++) {
                    for (var c = 0; c < cols; c++) {
                        var span = document.createElement('span');
                        span.textContent = watermarkText;
                        span.style.position = 'absolute';
                        span.style.left = ((c + 0.5) * (width / cols)) + 'px';
                        span.style.top = ((r + 0.5) * (height / rows)) + 'px';
                        span.style.transform = 'translate(-50%, -50%) rotate(-30deg)';
                        span.style.color = 'rgba(120,120,120,0.24)';
                        span.style.fontSize = '12px';
                        span.style.whiteSpace = 'nowrap';
                        overlay.appendChild(span);
                    }
                }

                return overlay;
            }

            function setZoomLabel() {
                if (zoomLabelEl) {
                    zoomLabelEl.textContent = Math.round((scale / SCALE_DEFAULT) * 100) + '%';
                }
            }

            /*
             * SỬA 2/10 — thanh công cụ mới có Ô NHẬP số trang (bản mẫu MaterialReaderPage.jsx)
             * thay cho dòng chữ chỉ để đọc. Hàm này giờ ghi vào ô đó; không ghi đè trong lúc
             * người dùng đang gõ dở (ô đang được chọn) kẻo số nhảy lung tung dưới tay họ.
             */
            function setPageIndicator(current) {
                if (pageTotalEl && totalPages) {
                    pageTotalEl.textContent = String(totalPages);
                }
                // SỬA 3/10 — dòng "N trang PDF" ở thanh đầu trang (bản mẫu có, bản cũ thiếu).
                // Số này chỉ biết được sau khi pdf.js đọc xong tệp, nên điền từ đây.
                if (pageCountEl && totalPages) {
                    pageCountEl.textContent = totalPages + ' trang PDF';
                    pageCountEl.classList.remove('hidden');
                }
                if (pageInputEl && totalPages) {
                    pageInputEl.max = String(totalPages);
                    if (document.activeElement !== pageInputEl) {
                        pageInputEl.value = String(current);
                    }
                }
                if (pageIndicatorEl && totalPages) {
                    pageIndicatorEl.textContent = 'Trang ' + current + ' / ' + totalPages;
                }
            }

            // Nhảy tới một trang: cuộn khung chứa trang đó vào tầm nhìn. pageWrappers do
            // renderAllPages() dựng, nên trang chưa vẽ xong thì bỏ qua lặng lẽ.
            function goToPage(requested) {
                var target = Math.min(totalPages || 1, Math.max(1, Math.trunc(Number(requested) || 1)));
                var wrapper = pageWrappers[target - 1];
                if (wrapper) {
                    wrapper.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
                setPageIndicator(target);
            }

            if (pageFormEl) {
                pageFormEl.addEventListener('submit', function (event) {
                    event.preventDefault();
                    goToPage(pageInputEl ? pageInputEl.value : 1);
                });
            }

            // SỬA 18/9 — vùng đọc PDF giờ có thanh cuộn RIÊNG (#reader-scroll-box) thay vì cuộn
            // cả trang, nên thanh tiến độ và số trang phải bám theo khung đó. Không có khung
            // (bố cục cũ) thì vẫn rơi về cuộn cả trang như trước.
            var scrollBox = document.getElementById('reader-scroll-box');

            function updateReadingProgress() {
                if (!progressBarEl) {
                    return;
                }
                var scrollTop, scrollable;
                if (scrollBox) {
                    scrollTop = scrollBox.scrollTop;
                    scrollable = scrollBox.scrollHeight - scrollBox.clientHeight;
                } else {
                    var doc = document.documentElement;
                    scrollTop = window.scrollY;
                    scrollable = doc.scrollHeight - window.innerHeight;
                }
                var pct = scrollable > 0 ? Math.min(100, Math.max(0, (scrollTop / scrollable) * 100)) : 0;
                progressBarEl.style.width = pct + '%';
            }

            function updateCurrentPageFromScroll() {
                if (!pageWrappers.length) {
                    return;
                }
                var current = 1;
                // Mốc so sánh là MÉP TRÊN của khung cuộn (không còn là mép trên cửa sổ).
                var anchor = scrollBox ? scrollBox.getBoundingClientRect().top + 24 : 160;
                for (var i = 0; i < pageWrappers.length; i++) {
                    if (pageWrappers[i].getBoundingClientRect().top <= anchor) {
                        current = i + 1;
                    }
                }
                currentPage = current;
                setPageIndicator(current);
            }

            var scrollTicking = false;
            (scrollBox || window).addEventListener('scroll', function () {
                if (scrollTicking) {
                    return;
                }
                scrollTicking = true;
                requestAnimationFrame(function () {
                    updateReadingProgress();
                    updateCurrentPageFromScroll();
                    scrollTicking = false;
                });
            });

            // SỬA 25/8 (3) — "không tải được toàn bộ nội dung đọc": trước đây renderNext()
            // KHÔNG có .catch() ở cả pdf.getPage() lẫn page.render() — hễ 1 TRANG BẤT KỲ lỗi
            // (vd font nhúng dạng CID cần dữ liệu CMap ngoài mà trước đây chưa cấu hình, ảnh
            // trong trang không giải mã được, kích cỡ trang vượt giới hạn canvas của trình
            // duyệt...) thì promise bị reject ÂM THẦM, renderNext(pageNum + 1) KHÔNG BAO GIỜ
            // được gọi tiếp — toàn bộ các trang SAU trang lỗi biến mất trắng, không có thông
            // báo gì. Fix: bắt lỗi ở TỪNG trang, hiện placeholder cho đúng trang đó rồi VẪN
            // tiếp tục renderNext() sang trang kế.
            function renderAllPages(pdf, scrollToPage) {
                container.innerHTML = '';
                pageWrappers = [];
                totalPages = pdf.numPages;
                setPageIndicator(scrollToPage || currentPage);

                var renderNext = function (pageNum) {
                    if (pageNum > pdf.numPages) {
                        return;
                    }

                    var renderPageError = function (err) {
                        console.error('Lỗi hiển thị trang ' + pageNum + ':', err);

                        var errBox = document.createElement('p');
                        errBox.className = 'reader-page-error';
                        errBox.style.maxWidth = '640px';
                        errBox.textContent = 'Không hiển thị được trang ' + pageNum + ' — đã bỏ qua, tiếp tục các trang khác.';
                        container.appendChild(errBox);

                        renderNext(pageNum + 1);
                    };

                    pdf.getPage(pageNum).then(function (page) {
                        var viewport = page.getViewport({ scale: scale });

                        var wrapper = document.createElement('div');
                        wrapper.className = 'reader-page-wrap';
                        wrapper.style.width = viewport.width + 'px';
                        wrapper.style.animationDelay = (Math.min(pageNum, 6) * 0.03) + 's';

                        var canvas = document.createElement('canvas');
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;
                        wrapper.appendChild(canvas);
                        wrapper.appendChild(buildWatermarkOverlay(viewport.width, viewport.height));
                        container.appendChild(wrapper);
                        pageWrappers.push(wrapper);

                        var pageLabel = document.createElement('p');
                        pageLabel.className = 'reader-page-number';
                        pageLabel.textContent = pageNum + ' / ' + pdf.numPages;
                        container.appendChild(pageLabel);

                        var ctx = canvas.getContext('2d');
                        page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function () {
                            if (scrollToPage && pageNum === scrollToPage) {
                                wrapper.scrollIntoView({ block: 'start' });
                            }
                            renderNext(pageNum + 1);
                        }, renderPageError);
                    }, renderPageError);
                };

                renderNext(1);
            }

            if (zoomInBtn) {
                zoomInBtn.addEventListener('click', function () {
                    if (!pdfDoc || scale >= SCALE_MAX) {
                        return;
                    }
                    scale = Math.min(SCALE_MAX, Math.round((scale + SCALE_STEP) * 100) / 100);
                    setZoomLabel();
                    renderAllPages(pdfDoc, currentPage);
                });
            }
            if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', function () {
                    if (!pdfDoc || scale <= SCALE_MIN) {
                        return;
                    }
                    scale = Math.max(SCALE_MIN, Math.round((scale - SCALE_STEP) * 100) / 100);
                    setZoomLabel();
                    renderAllPages(pdfDoc, currentPage);
                });
            }

            fetch(pdfUrl, { credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('fetch-failed');
                    }
                    return res.arrayBuffer();
                })
                .then(function (buf) {
                    return pdfjsLib.getDocument({
                        data: buf,
                        cMapUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/cmaps/',
                        cMapPacked: true,
                        standardFontDataUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/standard_fonts/',
                    }).promise;
                })
                .then(function (pdf) {
                    pdfDoc = pdf;
                    renderAllPages(pdf);
                })
                .catch(function (err) {
                    console.error('Không tải được tài liệu PDF:', err);
                    container.innerHTML = '<p class="text-center text-[13px] text-blue-500 py-10">Không tải được nội dung bài học. Vui lòng thử lại sau.</p>';
                });
        })();
    </script>
@endpush
