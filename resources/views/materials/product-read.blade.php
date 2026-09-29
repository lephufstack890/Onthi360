{{--
  SỬA 29/9 (khách chốt: "bỏ file pdf sách đi, chỗ chương mỗi chương thêm từng file pdf...
  khi học sinh hoặc giáo viên mua xong hoặc giáo viên gắn vào lớp học thì từng file pdf sẽ
  ghép dài để lướt lên lướt xuống để đọc") — TRANG ĐỌC LIỀN MẠCH CẢ SẢN PHẨM.

  Dùng CHUNG cho student.products.read và teacher.products.read ($layoutView/$libraryRoute do
  App\Services\ProductReadService::buildReadData() truyền vào theo vai trò đang gọi) — cùng
  cách student/materials/read.blade.php đang dùng chung cho 2 vai trò.

  Phần vỏ (thanh đầu trang, khung đọc có thanh cuộn riêng, thanh tiến độ, nút thu/phóng, chế
  độ Tập trung) CHÉP ĐÚNG lớp CSS/Tailwind của trang đọc 1 bài đang chạy — KHÔNG đặt thêm lớp
  Tailwind mới, vì VPS không chạy được vite nên lớp chưa có trong public/build/assets/app-*.css
  sẽ không có tác dụng. Mấy thứ mới (vạch ngăn chương, mục lục) viết bằng CSS thường ở dưới.

  Bộ đọc: pdf.js tải LẦN LƯỢT từng tệp PDF của từng chương rồi vẽ nối tiếp vào cùng một dải
  cuộn — tải lười (chỉ tải chương sau khi người đọc cuộn gần hết phần đã tải) để sách 20 chương
  không phải tải vài chục MB ngay khi mở trang.
--}}
@extends($layoutView ?? 'layouts.student')

@section('title', $product->title)
@section('page-title', 'Đọc tài liệu')

@section('content')
    @php
        $parts = $parts ?? [];
        $watermarkText = $watermarkText ?? '';
        $chapterWord = $chapterWord ?? 'Phần';
        $libraryRoute = $libraryRoute ?? 'student.library.index';
        $typeLabels = ['book' => 'Sách', 'topic' => 'Chuyên đề', 'exam' => 'Bộ đề', 'course' => 'Khóa học'];
    @endphp

    <style>
        @media print {
            body * { visibility: hidden !important; }
        }

        .reader-progress-track { height: 3px; background: #eef2f7; }
        .reader-progress-bar {
            height: 100%; width: 0%;
            background: linear-gradient(90deg, #fb7185, #e11d48);
            transition: width .12s linear;
        }

        .reader-page-wrap {
            position: relative;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 16px 32px -12px rgba(15, 23, 42, .16);
            margin: 0 auto;
            overflow: hidden;
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
            max-width: 640px;
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
            transition: background .15s, color .15s;
        }
        .reader-toolbar-btn:hover { background: #fff1f2; color: #e11d48; }
        .reader-page-indicator {
            font-size: .75rem;
            font-weight: 500;
            color: #475569;
            min-width: 92px;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }
        .reader-zoom-label {
            font-size: .7rem;
            color: #94a3b8;
            min-width: 40px;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }

        /* ── Mới cho bản đọc liền mạch ── */
        .oi-part-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 720px;
            margin: 6px auto 18px auto;
            padding: 0 4px;
        }
        .oi-part-divider:first-child { margin-top: 0; }
        .oi-part-divider .oi-part-line { flex: 1; height: 1px; background: #cfe0ea; }
        .oi-part-divider .oi-part-name {
            font-size: .72rem;
            font-weight: 700;
            color: #126F91;
            background: #eaf5f8;
            border: 1px solid #d5e8ed;
            border-radius: 999px;
            padding: 4px 12px;
            text-align: center;
            max-width: 70%;
        }
        .oi-part-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: .78rem;
            color: #61798B;
            padding: 18px 0 26px 0;
        }
        .oi-spin {
            display: inline-block;
            width: 16px; height: 16px;
            border: 2px solid #cfe3ee;
            border-top-color: #2D7FA3;
            border-radius: 999px;
            animation: oi-spin-kf .7s linear infinite;
        }
        @keyframes oi-spin-kf { to { transform: rotate(360deg); } }

        .oi-toc { max-height: min(720px, calc(100dvh - 230px)); overflow-y: auto; }
        .oi-toc-item {
            display: block;
            width: 100%;
            text-align: left;
            border: 1px solid #e3eef3;
            background: #fff;
            border-radius: 12px;
            padding: 9px 11px;
            margin-bottom: 7px;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }
        .oi-toc-item:hover { border-color: #9DC8D7; background: #F3FAFC; }
        .oi-toc-item.is-current { border-color: #2D7FA3; background: #EAF5F8; }
        .oi-toc-item.is-sub { margin-left: 14px; }
        .oi-toc-label {
            display: block;
            font-size: .62rem;
            font-weight: 800;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: #7FA5B8;
            margin-bottom: 2px;
        }
        .oi-toc-title { display: block; font-size: .8rem; font-weight: 600; color: #123B68; line-height: 1.35; }
        .oi-toc-state { display: block; font-size: .65rem; color: #93A9B7; margin-top: 2px; }
    </style>

    <div x-data="{ focus: false }" class="reader-ui bg-[#F7F9FB] text-[#466278]">

        {{-- ══════ THANH ĐẦU TRANG ══════ --}}
        <header x-show="! focus"
                class="sticky top-0 z-30 border-b border-[#DFEBF0] bg-white/95 shadow-[0_3px_14px_rgba(45,96,145,0.07)] backdrop-blur-xl">
            <div class="h-[3px] bg-gradient-to-r from-[#123B68] via-[#2D7FA3] to-[#E6B44A]" aria-hidden="true"></div>
            <div class="flex flex-wrap items-center gap-3 py-2.5 sm:py-3">
                <a href="{{ route($libraryRoute) }}" aria-label="Quay lại kho tài liệu"
                   class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-[#DDEAF0] bg-white text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#123B68]">
                    <x-lucide name="arrow-left" class="h-4 w-4" />
                </a>
                <div class="hidden h-6 w-px bg-slate-200 sm:block"></div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-[#EAF5F8] px-2.5 py-1 text-[10px] font-extrabold text-[#126F91]">
                            <x-lucide name="book-open" class="h-3 w-3" />Đang đọc tài liệu
                        </span>
                        <span class="hidden rounded-lg bg-[#F8FBFE] px-2 py-1 font-mono text-[10px] font-bold text-[#61798B] lg:inline-flex">
                            {{ $typeLabels[$product->type->value] ?? '' }}
                        </span>
                    </div>
                    <h1 class="mt-1 truncate text-[14px] font-semibold text-[#123B68] sm:text-base">{{ $product->title }}</h1>
                </div>

                <span class="inline-flex items-center gap-1.5 rounded-full border border-[#B7DDCD] bg-[#DFF2E9] px-2.5 py-1.5 text-[11px] font-semibold text-[#287B5F]">
                    <x-lucide name="check-circle-2" class="h-3 w-3" /><span>{{ count($parts) }} tệp nội dung</span>
                </span>

                <a href="{{ route($libraryRoute) }}" aria-label="Đóng trình đọc"
                   class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-slate-400 transition hover:bg-[#FFF1F1] hover:text-[#B42318]">
                    <x-lucide name="x" class="h-4 w-4" />
                </a>
            </div>
        </header>

        <main class="py-4 lg:py-6">
            @if (empty($parts))
                <div class="rounded-[28px] border border-[#D5E8ED] bg-white p-8 text-center">
                    <x-ws.empty-state title="Tài liệu này chưa có nội dung đọc"
                                      description="Các chương/phần chưa được tải file PDF lên. Vui lòng liên hệ bộ phận hỗ trợ." />
                </div>
            @else
                <div class="grid items-stretch gap-3 lg:grid-cols-[minmax(0,1.52fr)_minmax(320px,.74fr)]">

                    {{-- ══════ CỘT TRÁI: DẢI PDF LIỀN MẠCH ══════ --}}
                    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[28px] border border-[#D5E8ED] bg-[#F2F8FA] shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                        <div class="flex items-center justify-between gap-2 border-b border-[#E5EEF3] px-3 py-2 sm:px-4">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#E9F7F8] text-[#23869B]"><x-lucide name="file-text" class="h-3.5 w-3.5" /></span>
                                <p class="shrink-0 text-[13px] font-semibold text-[#123B68]">PDF</p>
                                <span class="reader-page-indicator" id="reader-page-indicator">…</span>
                                <span class="hidden truncate text-[11px] font-semibold text-[#61798B] sm:block" id="reader-part-label"></span>
                            </div>

                            <div class="flex shrink-0 items-center gap-1.5">
                                <button type="button" class="reader-toolbar-btn" id="reader-zoom-out" title="Thu nhỏ">−</button>
                                <span class="reader-zoom-label" id="reader-zoom-label">100%</span>
                                <button type="button" class="reader-toolbar-btn" id="reader-zoom-in" title="Phóng to">+</button>

                                <button type="button" @click="focus = ! focus"
                                        class="ml-1 inline-flex items-center gap-1.5 rounded-lg border border-[#DDEAF0] bg-white px-2.5 py-1.5 text-[11px] font-semibold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                    <x-lucide name="maximize-2" class="h-3.5 w-3.5" />
                                    <span x-text="focus ? 'Thoát tập trung' : 'Tập trung'">Tập trung</span>
                                </button>
                            </div>
                        </div>

                        <div class="reader-progress-track">
                            <div class="reader-progress-bar" id="reader-progress-bar"></div>
                        </div>

                        <div class="p-3 sm:p-5">
                            <p class="mb-3 select-none text-center text-xs text-slate-400">
                                <x-lucide name="lock" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> Nội dung chỉ xem trên web — không hỗ trợ tải về hoặc in trực tiếp.
                            </p>

                            <div id="reader-scroll-box" role="region" tabindex="0" aria-label="Vùng đọc PDF"
                                 class="material-pdf-scroll h-[min(760px,calc(100dvh-185px))] min-h-[520px] overflow-y-auto rounded-2xl border border-[#D6E2EA] bg-[#EAF4F8] p-2 sm:min-h-[680px] sm:p-3">
                                <div id="product-pdf-viewer"
                                     data-parts="{{ json_encode($parts, JSON_UNESCAPED_UNICODE) }}"
                                     data-watermark="{{ $watermarkText }}"
                                     class="select-none">
                                    <div class="oi-part-loading"><span class="oi-spin"></span> Đang tải nội dung…</div>
                                </div>
                                <div id="reader-tail"></div>
                            </div>
                        </div>
                    </section>

                    {{-- ══════ CỘT PHẢI: MỤC LỤC ══════ --}}
                    <aside class="flex min-h-0 flex-col overflow-hidden rounded-[28px] border border-[#D5E8ED] bg-white shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                        <div class="flex items-center gap-2 border-b border-[#E5EEF3] px-4 py-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#FFF7E3] text-[#AF7C32]"><x-lucide name="list" class="h-3.5 w-3.5" /></span>
                            <div class="min-w-0">
                                <p class="text-[13px] font-semibold text-[#123B68]">Mục lục</p>
                                <p class="text-[11px] text-[#7FA5B8]">Bấm để nhảy tới {{ mb_strtolower($chapterWord) }}</p>
                            </div>
                        </div>
                        <div class="oi-toc p-3" id="reader-toc">
                            @foreach ($parts as $i => $part)
                                <button type="button" class="oi-toc-item{{ $part['sub'] ? ' is-sub' : '' }}" data-part-index="{{ $i }}">
                                    @if ($part['label'] && ! $part['sub'])
                                        <span class="oi-toc-label">{{ $part['label'] }}</span>
                                    @endif
                                    <span class="oi-toc-title">{{ $part['title'] }}</span>
                                    <span class="oi-toc-state" data-state-for="{{ $i }}">Chưa tải</span>
                                </button>
                            @endforeach
                        </div>
                    </aside>
                </div>
            @endif
        </main>
    </div>
@endsection

@push('scripts')
    {{--
      Bộ đọc nhiều tệp. Giữ đúng cách nhúng pdf.js đã kiểm chứng ở trang đọc 1 bài (SỬA 25/8
      (5)): pdfjs-dist 4.0.379 chỉ còn bản ES module (.mjs) nên phải <script type="module"> +
      import() động, không dùng được đuôi .js.

      Khác trang đọc 1 bài:
        · nhiều tệp -> mảng parts, tải LẦN LƯỢT (tải lười theo cuộn), vẽ nối tiếp 1 dải;
        · số trang đếm DỒN cả quyển (trang 41 là trang 41 của cả sách, không phải trang 3 của
          chương 4), nhãn chương hiện cạnh số trang;
        · bấm mục lục: nếu chương đó chưa tải thì tải tuần tự tới đó rồi tự cuộn đến.
    --}}
    <script type="module">
        (async function () {
            var container = document.getElementById('product-pdf-viewer');
            if (!container) {
                return;
            }

            var parts;
            try {
                parts = JSON.parse(container.dataset.parts || '[]');
            } catch (err) {
                parts = [];
            }
            if (!parts.length) {
                return;
            }

            var pdfjsLib;
            try {
                pdfjsLib = await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/legacy/build/pdf.min.mjs');
            } catch (err) {
                console.error('Khong tai duoc thu vien pdf.js:', err);
                container.innerHTML = '<p class="reader-page-error">Không tải được thư viện đọc PDF — kiểm tra kết nối mạng rồi tải lại trang.</p>';
                return;
            }
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/legacy/build/pdf.worker.min.mjs';

            var watermarkText = container.dataset.watermark || '';
            var scrollBox = document.getElementById('reader-scroll-box');
            var progressBarEl = document.getElementById('reader-progress-bar');
            var pageIndicatorEl = document.getElementById('reader-page-indicator');
            var partLabelEl = document.getElementById('reader-part-label');
            var zoomInBtn = document.getElementById('reader-zoom-in');
            var zoomOutBtn = document.getElementById('reader-zoom-out');
            var zoomLabelEl = document.getElementById('reader-zoom-label');
            var tocEl = document.getElementById('reader-toc');

            var SCALE_DEFAULT = 1.4, SCALE_MIN = 0.8, SCALE_MAX = 2.4, SCALE_STEP = 0.2;
            var scale = SCALE_DEFAULT;

            var docs = new Array(parts.length); // tài liệu pdf.js của từng phần (undefined = chưa tải)
            var entries = [];                   // mỗi TRANG của cả quyển: { partIndex, page, wrapper, label, canvas, rendering, failed }
            var nextToLoad = 0;
            var loadedCount = 0;
            var busy = false;
            var failedPart = {};
            var syncTimer = null; // hẹn quét lại vùng nhìn (xem syncVisible())

            function sleep(ms) {
                return new Promise(function (r) { setTimeout(r, ms); });
            }
            async function waitIdle() {
                var guard = 0;
                while (busy && guard < 400) {
                    guard++;
                    await sleep(100);
                }
            }

            // Chặn menu chuột phải + phím tắt lưu/in (hàng rào cho người dùng thường, KHÔNG
            // chặn được chụp màn hình — đã báo trước với khách).
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
                for (var r = 0; r < 6; r++) {
                    for (var c = 0; c < 3; c++) {
                        var span = document.createElement('span');
                        span.textContent = watermarkText;
                        span.style.position = 'absolute';
                        span.style.left = ((c + 0.5) * (width / 3)) + 'px';
                        span.style.top = ((r + 0.5) * (height / 6)) + 'px';
                        span.style.transform = 'translate(-50%, -50%) rotate(-30deg)';
                        span.style.color = 'rgba(120,120,120,0.24)';
                        span.style.fontSize = '12px';
                        span.style.whiteSpace = 'nowrap';
                        overlay.appendChild(span);
                    }
                }
                return overlay;
            }

            function setTocState(index, text, markCurrent) {
                if (!tocEl) {
                    return;
                }
                if (text !== null) {
                    var stateEl = tocEl.querySelector('[data-state-for="' + index + '"]');
                    if (stateEl) {
                        stateEl.textContent = text;
                    }
                }
                if (markCurrent === true) {
                    var items = tocEl.querySelectorAll('.oi-toc-item');
                    for (var i = 0; i < items.length; i++) {
                        items[i].classList.toggle('is-current', String(i) === String(index));
                    }
                }
            }

            function setZoomLabel() {
                if (zoomLabelEl) {
                    zoomLabelEl.textContent = Math.round((scale / SCALE_DEFAULT) * 100) + '%';
                }
            }

            function setIndicator(current, partIndex) {
                if (pageIndicatorEl) {
                    var suffix = nextToLoad < parts.length ? '+' : '';
                    pageIndicatorEl.textContent = 'Trang ' + current + ' / ' + entries.length + suffix;
                }
                if (partLabelEl && parts[partIndex]) {
                    var p = parts[partIndex];
                    partLabelEl.textContent = '· ' + (p.label ? p.label + ' — ' : '') + p.title;
                }
            }

            /*
             * Vẽ 1 trang. CHỈ gọi cho trang đang ở gần vùng nhìn — xem syncVisible(): đọc liền
             * mạch cả quyển sách có thể là hàng trăm trang, vẽ hết ra canvas cùng lúc thì mỗi
             * trang tốn vài MB bộ nhớ, đủ làm treo/đóng tab trình duyệt. Trang ở xa chỉ giữ lại
             * cái khung rỗng đúng kích thước (giữ nguyên chiều dài thanh cuộn), vẽ lại khi cuộn tới.
             */
            async function renderEntry(entry) {
                if (entry.canvas || entry.rendering || entry.failed) {
                    return;
                }
                entry.rendering = true;
                try {
                    var vp = entry.page.getViewport({ scale: scale });
                    var canvas = document.createElement('canvas');
                    canvas.width = vp.width;
                    canvas.height = vp.height;
                    entry.wrapper.appendChild(canvas);
                    entry.wrapper.appendChild(buildWatermarkOverlay(vp.width, vp.height));
                    await entry.page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                    entry.canvas = canvas;
                } catch (err) {
                    // Lỗi 1 trang (font CID thiếu CMap, ảnh không giải mã được, trang quá khổ...)
                    // KHÔNG được làm sập cả dải: đánh dấu trang đó rồi đi tiếp.
                    console.error('Loi hien thi trang:', err);
                    entry.failed = true;
                    entry.wrapper.innerHTML = '';
                } finally {
                    entry.rendering = false;
                }
            }

            function releaseEntry(entry) {
                if (!entry.canvas) {
                    return;
                }
                entry.wrapper.innerHTML = '';
                entry.canvas = null;
            }

            /* Đánh số trang DỒN cả quyển (trang 41 là trang 41 của cả sách, không phải trang 3 của chương 4). */
            function renumber() {
                for (var i = 0; i < entries.length; i++) {
                    entries[i].label.textContent = 'Trang ' + (i + 1);
                }
            }

            /* Thêm 1 phần vào dải: vạch ngăn + khung rỗng cho từng trang (chưa vẽ). */
            async function appendPart(index) {
                var part = parts[index];
                var doc = docs[index];

                var divider = document.createElement('div');
                divider.className = 'oi-part-divider';
                divider.dataset.partAnchor = String(index);
                var lineL = document.createElement('span');
                lineL.className = 'oi-part-line';
                var name = document.createElement('span');
                name.className = 'oi-part-name';
                name.textContent = (part.label ? part.label + ' · ' : '') + part.title;
                var lineR = document.createElement('span');
                lineR.className = 'oi-part-line';
                divider.appendChild(lineL);
                divider.appendChild(name);
                divider.appendChild(lineR);
                container.appendChild(divider);

                for (var i = 1; i <= doc.numPages; i++) {
                    var page;
                    try {
                        page = await doc.getPage(i);
                    } catch (err) {
                        console.error('Khong doc duoc trang', i, part.title, err);
                        continue;
                    }
                    var vp = page.getViewport({ scale: scale });

                    var wrapper = document.createElement('div');
                    wrapper.className = 'reader-page-wrap';
                    wrapper.style.width = vp.width + 'px';
                    wrapper.style.height = vp.height + 'px';
                    container.appendChild(wrapper);

                    var label = document.createElement('p');
                    label.className = 'reader-page-number';
                    label.textContent = 'Trang';
                    container.appendChild(label);

                    entries.push({ partIndex: index, page: page, wrapper: wrapper, label: label, canvas: null, rendering: false, failed: false });
                }

                renumber();
                setTocState(index, doc.numPages + ' trang', false);
            }

            /* Tải 1 phần (fetch + pdf.js) rồi nối vào dải. */
            async function loadNextPart() {
                if (busy || nextToLoad >= parts.length) {
                    return false;
                }
                busy = true;
                var index = nextToLoad;
                var part = parts[index];

                var tail = document.createElement('div');
                tail.className = 'oi-part-loading';
                var spin = document.createElement('span');
                spin.className = 'oi-spin';
                tail.appendChild(spin);
                tail.appendChild(document.createTextNode(' Đang tải ' + (part.label ? part.label + ' · ' : '') + part.title + '…'));
                container.appendChild(tail);
                setTocState(index, 'Đang tải…', false);

                try {
                    var res = await fetch(part.url, { credentials: 'same-origin' });
                    if (!res.ok) {
                        throw new Error('http-' + res.status);
                    }
                    var buf = await res.arrayBuffer();
                    docs[index] = await pdfjsLib.getDocument({
                        data: buf,
                        cMapUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/cmaps/',
                        cMapPacked: true,
                        standardFontDataUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/standard_fonts/',
                    }).promise;
                    tail.remove();
                    await appendPart(index);
                    loadedCount++;
                } catch (err) {
                    console.error('Khong tai duoc tep cua phan:', part.title, err);
                    tail.remove();
                    failedPart[index] = true;
                    var errBox = document.createElement('p');
                    errBox.className = 'reader-page-error';
                    errBox.textContent = 'Không tải được "' + part.title + '" — bỏ qua phần này, các phần sau vẫn đọc bình thường.';
                    container.appendChild(errBox);
                    setTocState(index, 'Lỗi tải', false);
                }

                nextToLoad = index + 1;
                busy = false;
                syncVisible();
                updateCurrentFromScroll();
                return true;
            }

            /* Tải tuần tự cho tới phần $target (dùng khi bấm mục lục vào chương chưa tải). */
            async function loadUntil(target) {
                while (nextToLoad <= target && nextToLoad < parts.length) {
                    if (busy) {
                        await sleep(120);
                        continue;
                    }
                    await loadNextPart();
                }
            }

            /* Nội dung ngắn hơn khung đọc thì tải tiếp cho đỡ trống (tối đa 3 phần). */
            async function fillViewport() {
                var guard = 0;
                while (scrollBox && nextToLoad < parts.length && guard < 3
                       && scrollBox.scrollHeight <= scrollBox.clientHeight + 200) {
                    guard++;
                    await loadNextPart();
                }
            }

            /* Vẽ các trang gần vùng nhìn, thả canvas của trang đã đi xa. */
            function syncVisible() {
                if (!scrollBox || !entries.length) {
                    return;
                }
                var boxRect = scrollBox.getBoundingClientRect();
                var pad = boxRect.height * 1.5;
                var started = 0;
                var pending = false;
                for (var i = 0; i < entries.length; i++) {
                    var entry = entries[i];
                    var r = entry.wrapper.getBoundingClientRect();
                    var near = r.bottom > boxRect.top - pad && r.top < boxRect.bottom + pad;
                    if (near) {
                        // Mỗi lượt chỉ khởi động vài trang để không dồn hàng chục lệnh vẽ cùng lúc.
                        if (!entry.canvas && !entry.rendering && !entry.failed) {
                            if (started < 4) {
                                started++;
                                renderEntry(entry);
                            } else {
                                pending = true;
                            }
                        }
                    } else {
                        releaseEntry(entry);
                    }
                }

                // Còn trang gần vùng nhìn chưa vẽ -> hẹn quét lại, không đợi lần cuộn tiếp theo.
                if (pending && !syncTimer) {
                    syncTimer = setTimeout(function () {
                        syncTimer = null;
                        syncVisible();
                    }, 260);
                }
            }

            function updateProgress() {
                if (!progressBarEl || !scrollBox) {
                    return;
                }
                var scrollable = scrollBox.scrollHeight - scrollBox.clientHeight;
                var pct = scrollable > 0 ? Math.min(100, Math.max(0, (scrollBox.scrollTop / scrollable) * 100)) : 0;
                // Tiến độ tính trên phần ĐÃ TẢI nên nhân thêm tỉ lệ phần đã tải của cả quyển —
                // cuộn hết phần đã tải mà mới tải 1/3 quyển thì không thể báo 100%.
                var share = parts.length ? (nextToLoad / parts.length) : 1;
                progressBarEl.style.width = (pct * share) + '%';
            }

            function updateCurrentFromScroll() {
                if (!entries.length || !scrollBox) {
                    return;
                }
                // Trang "đang đọc" = trang ĐẦU TIÊN còn phủ qua mốc (mép trên khung + 24px).
                // KHÔNG dùng "trang cuối cùng nằm trên mốc": ở chỗ giáp ranh 2 chương, vạch ngăn
                // chương sau nằm ngay mốc thì trang cuối của chương TRƯỚC vẫn thoả điều kiện đó
                // -> thanh trên ghi sai tên chương ngay sau khi bấm mục lục (đã gặp khi kiểm thử).
                var anchor = scrollBox.getBoundingClientRect().top + 24;
                var current = entries.length, partIndex = entries[entries.length - 1].partIndex;
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].wrapper.getBoundingClientRect().bottom > anchor) {
                        current = i + 1;
                        partIndex = entries[i].partIndex;
                        break;
                    }
                }
                setIndicator(current, partIndex);
                setTocState(partIndex, null, true);
            }

            var ticking = false;
            var lastHeavy = 0;
            if (scrollBox) {
                scrollBox.addEventListener('scroll', function () {
                    if (ticking) {
                        return;
                    }
                    ticking = true;
                    requestAnimationFrame(function () {
                        updateProgress();
                        ticking = false;

                        // Việc nặng (quét toàn bộ trang để vẽ/thả canvas) giới hạn ~7 lần/giây,
                        // không chạy mỗi khung hình — cả quyển có thể hàng trăm trang.
                        var now = Date.now();
                        if (now - lastHeavy < 140) {
                            return;
                        }
                        lastHeavy = now;
                        updateCurrentFromScroll();
                        syncVisible();

                        if (!busy && nextToLoad < parts.length
                            && scrollBox.scrollTop + scrollBox.clientHeight >= scrollBox.scrollHeight - 1200) {
                            loadNextPart().then(updateProgress);
                        }
                    });
                });
            }

            if (tocEl) {
                tocEl.addEventListener('click', async function (e) {
                    var btn = e.target.closest ? e.target.closest('.oi-toc-item') : null;
                    if (!btn) {
                        return;
                    }
                    var index = parseInt(btn.dataset.partIndex, 10);
                    if (isNaN(index)) {
                        return;
                    }
                    await loadUntil(index);
                    if (failedPart[index]) {
                        return;
                    }
                    var anchor = container.querySelector('[data-part-anchor="' + index + '"]');
                    if (anchor) {
                        anchor.scrollIntoView({ block: 'start' });
                        updateCurrentFromScroll();
                        syncVisible();
                    }
                });
            }

            /* Đổi tỉ lệ: tính lại kích thước mọi khung rồi vẽ lại phần đang xem — KHÔNG tải lại mạng. */
            async function applyScale() {
                await waitIdle();
                busy = true;

                var keepIndex = 0;
                var anchorTop = scrollBox ? scrollBox.getBoundingClientRect().top + 24 : 0;
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].wrapper.getBoundingClientRect().top <= anchorTop) {
                        keepIndex = i;
                    }
                }

                for (var j = 0; j < entries.length; j++) {
                    var entry = entries[j];
                    entry.wrapper.innerHTML = '';
                    entry.canvas = null;
                    entry.failed = false;
                    var vp = entry.page.getViewport({ scale: scale });
                    entry.wrapper.style.width = vp.width + 'px';
                    entry.wrapper.style.height = vp.height + 'px';
                }

                if (entries[keepIndex]) {
                    entries[keepIndex].wrapper.scrollIntoView({ block: 'start' });
                }

                busy = false;
                syncVisible();
                updateProgress();
                updateCurrentFromScroll();
            }

            if (zoomInBtn) {
                zoomInBtn.addEventListener('click', function () {
                    if (scale >= SCALE_MAX) {
                        return;
                    }
                    scale = Math.min(SCALE_MAX, Math.round((scale + SCALE_STEP) * 100) / 100);
                    setZoomLabel();
                    applyScale();
                });
            }
            if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', function () {
                    if (scale <= SCALE_MIN) {
                        return;
                    }
                    scale = Math.max(SCALE_MIN, Math.round((scale - SCALE_STEP) * 100) / 100);
                    setZoomLabel();
                    applyScale();
                });
            }

            container.innerHTML = '';
            setZoomLabel();
            await loadNextPart();
            await fillViewport();
            syncVisible();
            updateProgress();
            updateCurrentFromScroll();
        })();
    </script>
@endpush
