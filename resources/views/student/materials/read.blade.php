@extends('layouts.student')

@section('title', $material->title)
@section('page-title', 'Đọc bài')

@section('content')
    @php
        $prev = $prev ?? null;
        $next = $next ?? null;
        $watermarkText = $watermarkText ?? '';
    @endphp

    <style>
        /* SỬA 25/8 ("đọc bài" — khách yêu cầu không cho tải/in): khi người dùng bấm in (Ctrl+P
           hoặc menu trình duyệt), ẩn TOÀN BỘ trang thay vì in ra nội dung PDF đã hiển thị. Đây
           chỉ chặn được lối in "bình thường" qua trình duyệt — không có cách nào chặn tuyệt
           đối việc chụp màn hình/chụp ảnh, đã báo trước với khách. */
        @media print {
            body * { visibility: hidden !important; }
        }
    </style>

    <div class="max-w-3xl mx-auto">
        <a href="{{ route('materials.show', $material->product_id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại {{ $material->product->title ?? 'tài liệu' }}</a>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-4 flex items-center justify-between gap-4 flex-wrap">
            <div class="min-w-0">
                <p class="text-xs text-slate-400">{{ $material->code ? 'Mã bài: '.$material->code : '' }}</p>
                <h1 class="font-medium text-slate-800 truncate">{{ $material->title }}</h1>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if ($prev)
                    <a href="{{ route('student.materials.read', $prev->id) }}" class="px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-rose-200 hover:text-rose-600 transition">‹ Bài trước</a>
                @endif
                @if ($next)
                    <a href="{{ route('student.materials.read', $next->id) }}" class="px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-rose-200 hover:text-rose-600 transition">Bài sau ›</a>
                @endif
            </div>
        </div>

        <p class="text-xs text-slate-400 text-center mb-3">Nội dung chỉ xem trên web — không hỗ trợ tải về hoặc in trực tiếp.</p>

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

        <div class="flex items-center justify-between gap-4 flex-wrap mt-4 mb-10">
            @if ($prev)
                <a href="{{ route('student.materials.read', $prev->id) }}" class="px-4 py-2.5 rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-rose-200 hover:text-rose-600 transition">‹ Bài trước</a>
            @else
                <span></span>
            @endif
            @if ($next)
                <a href="{{ route('student.materials.read', $next->id) }}" class="px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition">Bài sau ›</a>
            @endif
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

            // SỬA 25/8 (3) — "không tải được toàn bộ nội dung đọc": trước đây renderNext()
            // KHÔNG có .catch() ở cả pdf.getPage() lẫn page.render() — hễ 1 TRANG BẤT KỲ lỗi
            // (vd font nhúng dạng CID cần dữ liệu CMap ngoài mà trước đây chưa cấu hình, ảnh
            // trong trang không giải mã được, kích cỡ trang vượt giới hạn canvas của trình
            // duyệt...) thì promise bị reject ÂM THẦM, renderNext(pageNum + 1) KHÔNG BAO GIỜ
            // được gọi tiếp — toàn bộ các trang SAU trang lỗi biến mất trắng, không có thông
            // báo gì (dù quyền/đăng nhập vẫn hoàn toàn đúng — đây là lỗi ở bước RENDER, không
            // phải lỗi cấp quyền). Fix: bắt lỗi ở TỪNG trang, hiện placeholder cho đúng trang
            // đó rồi VẪN tiếp tục renderNext() sang trang kế — 1 trang lỗi không còn chặn cả
            // các trang phía sau. Đồng thời thêm cMapUrl/cMapPacked + standardFontDataUrl cho
            // getDocument() — nguyên nhân phổ biến nhất khiến 1 số trang PDF tiếng Việt (font
            // nhúng dạng Identity-H/CID) lỗi render nếu thiếu dữ liệu CMap.
            function renderAllPages(pdf) {
                container.innerHTML = '';

                var renderNext = function (pageNum) {
                    if (pageNum > pdf.numPages) {
                        return;
                    }

                    var renderPageError = function (err) {
                        console.error('Lỗi hiển thị trang ' + pageNum + ':', err);

                        var errBox = document.createElement('p');
                        errBox.className = 'text-center text-xs text-rose-500 py-6 max-w-3xl mx-auto';
                        errBox.textContent = 'Không hiển thị được trang ' + pageNum + ' — đã bỏ qua, tiếp tục các trang khác.';
                        container.appendChild(errBox);

                        renderNext(pageNum + 1);
                    };

                    pdf.getPage(pageNum).then(function (page) {
                        var viewport = page.getViewport({ scale: 1.4 });

                        var wrapper = document.createElement('div');
                        wrapper.style.position = 'relative';
                        wrapper.style.width = viewport.width + 'px';
                        wrapper.style.margin = '0 auto 16px auto';
                        wrapper.className = 'shadow-sm rounded-lg overflow-hidden bg-white';

                        var canvas = document.createElement('canvas');
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;
                        wrapper.appendChild(canvas);
                        wrapper.appendChild(buildWatermarkOverlay(viewport.width, viewport.height));
                        container.appendChild(wrapper);

                        var ctx = canvas.getContext('2d');
                        page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function () {
                            renderNext(pageNum + 1);
                        }, renderPageError);
                    }, renderPageError);
                };

                renderNext(1);
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
                .then(renderAllPages)
                .catch(function (err) {
                    console.error('Không tải được tài liệu PDF:', err);
                    container.innerHTML = '<p class="text-center text-sm text-rose-500 py-10">Không tải được nội dung bài học. Vui lòng thử lại sau.</p>';
                });
        })();
    </script>
@endpush
