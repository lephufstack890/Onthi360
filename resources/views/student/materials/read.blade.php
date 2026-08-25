@extends('layouts.student')

@section('title', $material->title)
@section('page-title', 'Đọc bài')

@section('content')
    @php
        $prev = $prev ?? null;
        $next = $next ?? null;
        $watermarkText = $watermarkText ?? '';
    @endphp

    {{--
      SỬA 25/8 (6) — "logic ok hết rồi nhưng UI xấu quá": làm lại giao diện trang đọc cho
      giống 1 bộ đọc tài liệu thật (kiểu Google Docs/Kindle) thay vì 1 khối trắng trơn nằm
      giữa nền xám. Toàn bộ phần "chrome" mới (thanh trên dính top, thanh công cụ nổi dưới,
      thanh tiến độ đọc, bóng đổ từng trang, hiệu ứng mờ-dần khi trang hiện ra) viết bằng CSS
      thuần trong <style> này — KHÔNG dùng class Tailwind tự chế (vd min-h-[...], -m-[...])
      vì CSS đã build sẵn (public/build/assets/app-*.css) không thể build lại trên máy chủ
      hiện tại (npm run build lỗi EPERM) nên mọi class Tailwind mới đều phải là class ĐÃ CÓ
      SẴN trong file build đó — viết CSS tay ở đây tránh rủi ro đó hoàn toàn. Logic đọc
      quyền/đọc PDF/chặn tải-in ở SỬA 25/8 (3)-(5) giữ nguyên, không đổi gì.
    --}}
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
        .reader-progress-track { height: 3px; background: #eef2f7; }
        .reader-progress-bar {
            height: 100%; width: 0%;
            background: linear-gradient(90deg, #fb7185, #e11d48);
            transition: width .12s linear;
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
        .reader-zoom-label {
            font-size: .7rem;
            color: #94a3b8;
            min-width: 40px;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }
    </style>

    <div class="reader-shell">
        <div class="reader-topbar">
            <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between gap-4 flex-wrap">
                <div class="min-w-0">
                    <a href="{{ route('materials.show', $material->product_id) }}" class="text-xs text-slate-400 hover:text-rose-600 inline-flex items-center gap-1 transition">‹ {{ $material->product->title ?? 'tài liệu' }}</a>
                    <div class="flex items-center gap-2 mt-0.5">
                        <h1 class="font-semibold text-slate-800 truncate text-base">{{ $material->title }}</h1>
                    </div>
                </div>
                @if ($material->code)
                    <span class="reader-code-badge shrink-0">{{ $material->code }}</span>
                @endif
            </div>
            <div class="reader-progress-track">
                <div class="reader-progress-bar" id="reader-progress-bar"></div>
            </div>
        </div>

        <div class="reader-scroll-area">
            <p class="text-xs text-slate-400 text-center mb-5 select-none">🔒 Nội dung chỉ xem trên web — không hỗ trợ tải về hoặc in trực tiếp.</p>

            <div
                id="material-pdf-viewer"
                data-pdf-url="{{ $pdfUrl }}"
                data-watermark="{{ $watermarkText }}"
                class="select-none"
            >
                <div class="flex items-center justify-center py-16 text-sm text-slate-400">
                    <span class="inline-block w-5 h-5 border-2 border-rose-200 border-t-rose-600 rounded-full animate-spin mr-2"></span>
                    Đang tải nội dung…
                </div>
            </div>
        </div>

        <div class="reader-toolbar-dock">
            <div class="reader-toolbar">
                @if ($prev)
                    <a href="{{ route('student.materials.read', $prev->id) }}" class="reader-toolbar-btn" title="Bài trước">‹</a>
                @else
                    <span class="reader-toolbar-btn" aria-disabled="true">‹</span>
                @endif

                <span class="reader-page-indicator" id="reader-page-indicator">…</span>

                @if ($next)
                    <a href="{{ route('student.materials.read', $next->id) }}" class="reader-toolbar-btn" title="Bài sau">›</a>
                @else
                    <span class="reader-toolbar-btn" aria-disabled="true">›</span>
                @endif

                <span class="reader-toolbar-sep"></span>

                <button type="button" class="reader-toolbar-btn" id="reader-zoom-out" title="Thu nhỏ">−</button>
                <span class="reader-zoom-label" id="reader-zoom-label">100%</span>
                <button type="button" class="reader-toolbar-btn" id="reader-zoom-in" title="Phóng to">+</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
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
                container.innerHTML = '<p class="text-center text-sm text-rose-500 py-10">Không tải được thư viện đọc PDF — vui lòng kiểm tra kết nối mạng rồi tải lại trang.</p>';
                return;
            }

            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/legacy/build/pdf.worker.min.mjs';

            var pdfUrl = container.dataset.pdfUrl;
            var watermarkText = container.dataset.watermark || '';

            var progressBarEl = document.getElementById('reader-progress-bar');
            var pageIndicatorEl = document.getElementById('reader-page-indicator');
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

            function setPageIndicator(current) {
                if (pageIndicatorEl && totalPages) {
                    pageIndicatorEl.textContent = 'Trang ' + current + ' / ' + totalPages;
                }
            }

            function updateReadingProgress() {
                if (!progressBarEl) {
                    return;
                }
                var doc = document.documentElement;
                var scrollable = doc.scrollHeight - window.innerHeight;
                var pct = scrollable > 0 ? Math.min(100, Math.max(0, (window.scrollY / scrollable) * 100)) : 0;
                progressBarEl.style.width = pct + '%';
            }

            function updateCurrentPageFromScroll() {
                if (!pageWrappers.length) {
                    return;
                }
                var current = 1;
                for (var i = 0; i < pageWrappers.length; i++) {
                    if (pageWrappers[i].getBoundingClientRect().top <= 160) {
                        current = i + 1;
                    }
                }
                currentPage = current;
                setPageIndicator(current);
            }

            var scrollTicking = false;
            window.addEventListener('scroll', function () {
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
                    container.innerHTML = '<p class="text-center text-sm text-rose-500 py-10">Không tải được nội dung bài học. Vui lòng thử lại sau.</p>';
                });
        })();
    </script>
@endpush
