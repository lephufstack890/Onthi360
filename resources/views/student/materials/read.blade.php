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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.min.js"></script>
    <script>
        (function () {
            var container = document.getElementById('material-pdf-viewer');
            if (!container || typeof pdfjsLib === 'undefined') {
                if (container) {
                    container.innerHTML = '<p class="text-center text-sm text-rose-500 py-10">Không tải được bộ đọc nội dung. Vui lòng tải lại trang.</p>';
                }
                return;
            }

            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.worker.min.js';

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

            function renderAllPages(pdf) {
                container.innerHTML = '';

                var renderNext = function (pageNum) {
                    if (pageNum > pdf.numPages) {
                        return;
                    }

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
                        });
                    });
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
                    return pdfjsLib.getDocument({ data: buf }).promise;
                })
                .then(renderAllPages)
                .catch(function () {
                    container.innerHTML = '<p class="text-center text-sm text-rose-500 py-10">Không tải được nội dung bài học. Vui lòng thử lại sau.</p>';
                });
        })();
    </script>
@endpush
