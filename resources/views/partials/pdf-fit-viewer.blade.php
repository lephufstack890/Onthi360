{{-- ═══════════ KHUNG XEM PDF VỪA CHIỀU NGANG (dùng chung) ═══════════
     SỬA 18/9 (khách: "tab đề pdf mặc định như này không ổn").

     LÝ DO BỎ <iframe>: trước đây tab này nhúng thẳng PDF vào iframe và nhờ trình xem PDF
     của trình duyệt tự chọn mức phóng. Trình xem đó dùng kiểu "vừa CẢ TRANG", nên tệp đề
     nào có khổ giấy lớn là hiện ra bé tí giữa vùng xám mênh mông. Đã thử ép bằng tham số mở
     tệp (#view=FitH&zoom=page-width) — Chrome KHÔNG áp dụng, trang vẫn bé y nguyên.

     Cách chắc chắn: tự vẽ bằng pdf.js với tỉ lệ TÍNH TỪ CHIỀU NGANG khung, đúng thư viện và
     đúng phiên bản mà trang "Đọc tài liệu" (student/materials/read.blade.php) đang dùng ổn
     định. Đổi kích thước cửa sổ hay mở tab lần đầu đều vẽ lại cho vừa.

     Tiện thêm: pdf.js vẽ ra canvas nên KHÔNG có thanh công cụ kèm nút tải về — đúng yêu cầu
     "đề bài chỉ xem trên web", chặt hơn cả mẹo toolbar=0 của iframe.

     Dùng: <div data-pdf-fit data-pdf-url="{{ $url }}" class="..."></div> --}}
<script type="module">
    (function () {
        var LIB = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/legacy/build/pdf.min.mjs';
        var WORKER = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/legacy/build/pdf.worker.min.mjs';
        var CMAPS = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/cmaps/';
        var FONTS = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/standard_fonts/';

        // Nạp thư viện ĐÚNG MỘT LẦN dù trên trang có nhiều khung PDF (phòng thi nhiều câu).
        var libPromise = null;
        function loadLib() {
            if (libPromise === null) {
                libPromise = import(LIB).then(function (m) {
                    m.GlobalWorkerOptions.workerSrc = WORKER;
                    return m;
                });
            }
            return libPromise;
        }

        function message(box, text) {
            box.innerHTML = '';
            var p = document.createElement('p');
            p.className = 'py-10 text-center text-[13px] text-[#607A90]';
            p.textContent = text;
            box.appendChild(p);
        }

        function setup(box) {
            if (box.dataset.pdfFitReady === '1') return;
            box.dataset.pdfFitReady = '1';

            var url = box.dataset.pdfUrl;
            if (!url) return;

            var doc = null;
            var renderToken = 0;
            var lastWidth = 0;

            function draw() {
                var width = box.clientWidth;
                // Tab đang ẩn (x-show) -> chiều ngang 0: chưa vẽ, đợi ResizeObserver gọi lại
                // khi tab được mở. Lệch dưới 24px thì bỏ qua để khỏi vẽ lại liên tục lúc kéo
                // cửa sổ (mỗi lần vẽ lại là render toàn bộ trang, rất tốn).
                if (width < 160 || Math.abs(width - lastWidth) < 24 || doc === null) return;
                lastWidth = width;

                var token = ++renderToken;
                var frag = document.createDocumentFragment();
                var pageNum = 1;

                (function next() {
                    if (pageNum > doc.numPages) {
                        if (token !== renderToken) return;
                        box.innerHTML = '';
                        box.appendChild(frag);
                        return;
                    }

                    doc.getPage(pageNum).then(function (page) {
                        if (token !== renderToken) return;

                        var base = page.getViewport({ scale: 1 });
                        // -16 chừa khoảng đệm hai bên. CHẶN Ở 1600px: màn siêu rộng mà vẽ tràn
                        // hết bề ngang thì mỗi trang là một canvas ~2000x2800 ≈ 22MB bộ nhớ —
                        // đề chục trang là treo máy. 1600px đã quá đủ nét, trang canh giữa
                        // giống mọi trình đọc PDF. Chặn trên 4 lần để trang khổ nhỏ không vỡ nét.
                        var targetWidth = Math.min(width - 16, 1600);
                        var scale = Math.min(4, Math.max(0.05, targetWidth / base.width));
                        var viewport = page.getViewport({ scale: scale });

                        var canvas = document.createElement('canvas');
                        canvas.width = Math.floor(viewport.width);
                        canvas.height = Math.floor(viewport.height);
                        canvas.className = 'mx-auto mb-3 block max-w-full rounded-lg shadow-[0_6px_18px_rgba(15,40,60,.18)]';
                        // Nền trang đặt bằng style TRỰC TIẾP, KHÔNG dùng lớp bg-white: chế độ tối
                        // có luật lật mọi .bg-white sang #1b2d38 — dính vào là nền trang đề hoá
                        // đen trong khi chữ trong PDF vẫn màu đen, không đọc được gì.
                        canvas.style.backgroundColor = '#ffffff';

                        page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise.then(function () {
                            if (token !== renderToken) return;
                            frag.appendChild(canvas);
                            pageNum++;
                            next();
                        }, function (err) {
                            // Một trang lỗi KHÔNG được làm chết các trang sau — cùng bài học đã
                            // rút ra ở trang "Đọc tài liệu" (SỬA 25/8).
                            console.error('Không vẽ được trang ' + pageNum + ':', err);
                            pageNum++;
                            next();
                        });
                    }, function (err) {
                        console.error('Không đọc được trang ' + pageNum + ':', err);
                        pageNum++;
                        next();
                    });
                })();
            }

            message(box, 'Đang tải đề bài…');

            loadLib()
                .then(function (pdfjsLib) {
                    return fetch(url, { credentials: 'same-origin' })
                        .then(function (res) {
                            if (!res.ok) throw new Error('HTTP ' + res.status);
                            return res.arrayBuffer();
                        })
                        .then(function (buf) {
                            return pdfjsLib.getDocument({
                                data: buf,
                                cMapUrl: CMAPS,
                                cMapPacked: true,
                                standardFontDataUrl: FONTS,
                            }).promise;
                        });
                })
                .then(function (pdf) {
                    doc = pdf;
                    lastWidth = 0;
                    box.innerHTML = '';
                    draw();
                })
                .catch(function (err) {
                    console.error('Không tải được đề bài PDF:', err);
                    message(box, 'Không tải được đề bài — kiểm tra kết nối mạng rồi tải lại trang.');
                });

            new ResizeObserver(draw).observe(box);
        }

        function scan() {
            document.querySelectorAll('[data-pdf-fit]').forEach(setup);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', scan);
        } else {
            scan();
        }
    })();
</script>
