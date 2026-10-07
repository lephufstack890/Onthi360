{{--
    SỬA 1/10 — PHẦN JS của đồng hồ + nhật ký làm bài, tách ra partial để màn Luyện tập
    (exercise-play) và màn Phòng thi (assessment/take) dùng CHUNG một bản. Trước đây đoạn này
    nằm thẳng trong exercise-play nên chỉ màn Luyện tập có nhật ký.

    Đi kèm partials/work-activity-panel (phần khung). Thiếu một trong hai là hỏng.

    Thuần JS, KHÔNG dùng Alpine — lý do gốc ghi ngay dưới đây.
--}}
    {{-- ══════ SỬA 30/9 — ĐỒNG HỒ + NHẬT KÝ LÀM BÀI (bản mẫu mới) ══════
         Hai thứ của bản mẫu mới, cả hai đều CHỈ CHẠY Ở TRÌNH DUYỆT — không thêm một lời gọi
         máy chủ nào, không đụng vào luồng chấm bài:
           · đồng hồ đếm lên từ lúc mở trang (thanh trên cùng);
           · nhật ký: mở bài, chuyển tab, dán/sao chép, cửa sổ mất tiêu điểm rồi quay lại, phím
             Print Screen, rời trang, nộp bài. Lưu ở sessionStorage theo mã bài, đóng trình
             duyệt là hết — giống hệt cách bản mẫu làm (assessmentLogKey).

         Dùng thuần JS (không Alpine) vì phần lớn sự kiện đến từ #practice-container — khối bị
         thay mới sau mỗi lần chấm, mà Alpine 3 không khởi tạo DOM do JS chèn vào. --}}
    <script>
        (function () {
            // ── Đồng hồ ──
            var timerEl = document.querySelector('[data-work-timer]');
            if (timerEl) {
                var startedAt = Date.now();
                var pad2 = function (n) { return n < 10 ? '0' + n : String(n); };
                setInterval(function () {
                    var s = Math.max(0, Math.round((Date.now() - startedAt) / 1000));
                    timerEl.textContent = pad2(Math.floor(s / 3600)) + ':' + pad2(Math.floor(s / 60) % 60) + ':' + pad2(s % 60);
                }, 1000);
            }

            // ── Nhật ký ──
            var listEl = document.querySelector('[data-activity-list]');
            var countEl = document.querySelector('[data-activity-count]');
            var signalEl = document.querySelector('[data-activity-signal-count]');
            var shell = document.querySelector('.assessment-modal-shell');
            if (!listEl) return;

            var KEY = 'onthi360:practice-activity:' + (document.querySelector('[data-activity-key]')?.getAttribute('data-activity-key') || 'exercise');
            var filter = 'all';
            var entries = [];
            try {
                var saved = JSON.parse(window.sessionStorage.getItem(KEY) || '[]');
                if (Array.isArray(saved)) entries = saved.filter(function (e) { return e && typeof e.title === 'string'; }).slice(0, 100);
            } catch (e) { entries = []; }

            var fmt = new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });

            function persist() {
                try { window.sessionStorage.setItem(KEY, JSON.stringify(entries.slice(0, 100))); } catch (e) {}
            }

            function render() {
                var signals = entries.filter(function (e) { return e.kind === 'signal'; }).length;
                if (countEl) countEl.textContent = entries.length + ' sự kiện · ' + signals + ' dấu hiệu cần xem xét';
                if (signalEl) signalEl.textContent = String(signals);

                var visible = filter === 'signals' ? entries.filter(function (e) { return e.kind === 'signal'; }) : entries;
                listEl.innerHTML = '';

                if (visible.length === 0) {
                    var empty = document.createElement('li');
                    empty.className = 'py-3 text-[11px] text-[#607A90]';
                    empty.textContent = filter === 'signals' ? 'Chưa có dấu hiệu nào cần xem xét.' : 'Chưa có sự kiện nào.';
                    listEl.appendChild(empty);
                    return;
                }

                visible.forEach(function (entry) {
                    var li = document.createElement('li');
                    li.className = 'flex flex-wrap gap-x-2 gap-y-1 py-2 text-[11px] leading-5';
                    var time = document.createElement('time');
                    time.className = 'shrink-0 text-[10px] text-[#7A92A3]';
                    time.setAttribute('datetime', entry.occurredAt || '');
                    time.textContent = entry.time || '';
                    var title = document.createElement('span');
                    title.className = entry.kind === 'signal' ? 'font-semibold text-amber-700' : 'font-semibold text-[#123B68]';
                    title.textContent = (entry.kind === 'signal' ? '• ' : '') + entry.title;
                    var detail = document.createElement('span');
                    detail.className = 'text-[#607A90]';
                    detail.textContent = entry.detail || '';
                    li.appendChild(time); li.appendChild(title); li.appendChild(detail);
                    listEl.appendChild(li);
                });
            }

            // SỬA 7/10 — eventType (tham số thứ 4) để máy chủ phân nhóm dấu hiệu cho cột "Hoạt động"
            // ở trang Nhật ký nộp bài của admin (screenshot_key, tab_visibility_lost,
            // solution_guide_opened, sample_opened, screen_capture_requested).
            function add(kind, title, detail, eventType) {
                var now = new Date();
                entries.unshift({
                    id: now.getTime() + '-' + Math.random().toString(36).slice(2, 7),
                    kind: kind, title: title, detail: detail, eventType: eventType || null,
                    occurredAt: now.toISOString(), time: fmt.format(now),
                });
                entries = entries.slice(0, 100);
                persist();
                render();
            }

            /*
             * SỬA 1/10 (khách: "chỉ muốn lưu nhật ký nếu chuyển tab giữa Hướng dẫn và Bài mẫu,
             * chụp màn hình, bật tab mới, quay chụp ảnh màn hình — chỉ cần vậy thôi").
             *
             * ĐÃ BỎ HẲN: mở bài làm, nộp bài, trở lại bài làm, rời trang, sao chép, dán, và
             * chuyển sang các tab Đề bài / Làm bài / Nhật ký. Mấy thứ đó đẻ ra hàng chục dòng
             * mỗi phiên, lấp mất đúng những dấu hiệu khách cần nhìn.
             *
             * CÒN LẠI ĐÚNG 4 VIỆC:
             *   1. mở tab Hướng dẫn hoặc Bài mẫu;
             *   2. bấm phím chụp màn hình;
             *   3. trang làm bài bị ẩn (mở tab/cửa sổ khác);
             *   4. có yêu cầu quay/chụp màn hình từ trình duyệt.
             */
            window.oiWorkLog = {
                add: add,
                /*
                 * SỬA 7/10 — bản chụp nhật ký (cũ → mới) để gửi kèm lúc Nộp bài; máy chủ lưu theo
                 * lượt nộp và chỉ admin xem được. Gọn: chỉ các trường máy chủ cần.
                 */
                snapshot: function () {
                    return entries.slice().reverse().map(function (e) {
                        return {
                            id: e.id, kind: e.kind, eventType: e.eventType || null,
                            title: e.title, detail: e.detail || '', occurredAt: e.occurredAt || null,
                        };
                    });
                },
                tabChanged: function (tab) {
                    // CHỈ hai tab này. Đề bài / Làm bài / Nhật ký là chỗ phải qua lại liên tục
                    // trong lúc làm, ghi vào thì nhật ký thành một dải vô nghĩa.
                    var label = { guide: 'Hướng dẫn', sample: 'Bài mẫu' }[tab];
                    if (!label) return;
                    add('event', 'Mở tab ' + label, 'Chuyển sang xem "' + label + '".',
                        tab === 'guide' ? 'solution_guide_opened' : 'sample_opened');
                },
            };

            // ── 3. Mở tab/cửa sổ khác ──────────────────────────────────────────────────
            // Trình duyệt KHÔNG cho biết người ta mở trang nào, chỉ cho biết trang này bị ẩn.
            // Nên câu chữ phải nói đúng chừng đó, không được đoán thêm.
            //
            // Lúc quay lại thì KHÔNG thêm dòng mới (khách không xin) mà ghi thẳng khoảng thời
            // gian vắng mặt vào chính dòng cũ — vẫn biết đi bao lâu mà nhật ký không phình.
            var away = null;

            function elapsed(startedAt) {
                var s = Math.max(0, Math.round((Date.now() - startedAt) / 1000));
                return s < 60 ? s + ' giây' : Math.floor(s / 60) + ' phút ' + (s % 60) + ' giây';
            }

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    if (away) return;
                    // add() chèn vào ĐẦU mảng, nên gọi xong thì entries[0] chính là dòng vừa thêm.
                    add('signal', 'Mở tab hoặc cửa sổ khác', 'Trang làm bài bị ẩn đi.', 'tab_visibility_lost');
                    away = { at: Date.now(), entry: entries[0] };
                    return;
                }

                if (!away) return;
                var gone = elapsed(away.at);
                if (away.entry) {
                    away.entry.detail = 'Trang làm bài bị ẩn đi ' + gone + '.';
                    persist();
                    render();
                }
                away = null;
            });

            // ── 2 + 4. Chụp màn hình / quay màn hình ───────────────────────────────────
            document.addEventListener('keydown', function (event) {
                if (event.repeat) return;

                if (event.key === 'PrintScreen' || event.code === 'PrintScreen') {
                    add('signal', 'Bấm phím chụp màn hình', 'Trang nhận được phím Print Screen.', 'screenshot_key');
                    return;
                }

                /*
                 * Tổ hợp chụp/quay màn hình của hệ điều hành:
                 *   · macOS   Cmd+Shift+3 / 4 / 5  (5 là quay màn hình)
                 *   · Windows Win+Shift+S          (Snipping Tool)
                 *
                 * LƯU Ý THẬT LÒNG: hệ điều hành thường nuốt mấy tổ hợp này trước khi tới trang,
                 * nên bắt được là may chứ KHÔNG chắc chắn. Không bắt được cũng không có nghĩa
                 * là người ta không chụp — câu chữ trong nhật ký nói đúng mức đó.
                 */
                if (event.shiftKey && (event.metaKey || event.ctrlKey)
                    && ['3', '4', '5', 'S', 's'].indexOf(event.key) >= 0) {
                    add('signal', 'Tổ hợp phím chụp/quay màn hình', 'Trang nhận được tổ hợp phím chụp hoặc quay màn hình.', 'screenshot_key');
                }
            }, true);

            /*
             * Quay/chụp màn hình bằng chính trình duyệt (chia sẻ màn hình): chỉ bắt được khi
             * lời gọi xuất phát TỪ TRANG NÀY. Phần mềm quay màn hình cài ngoài thì không trang
             * web nào biết được — đó là giới hạn của trình duyệt, không phải thiếu sót ở đây.
             */
            try {
                var media = navigator.mediaDevices;
                if (media && typeof media.getDisplayMedia === 'function') {
                    var original = media.getDisplayMedia.bind(media);
                    media.getDisplayMedia = function () {
                        add('signal', 'Yêu cầu quay/chụp màn hình', 'Trang nhận được yêu cầu chia sẻ hoặc quay màn hình.', 'screen_capture_requested');
                        return original.apply(null, arguments);
                    };
                }
            } catch (e) {}

            // ── Bộ lọc + tải nhật ký ──
            document.querySelectorAll('[data-activity-filter]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    filter = btn.getAttribute('data-activity-filter');
                    document.querySelectorAll('[data-activity-filter]').forEach(function (other) {
                        var on = other === btn;
                        var signals = other.getAttribute('data-activity-filter') === 'signals';
                        other.className = on
                            ? (signals ? 'font-bold text-amber-700 underline underline-offset-2' : 'font-bold text-[#126F91] underline underline-offset-2')
                            : 'text-[#607A90]';
                    });
                    render();
                });
            });

            var downloadBtn = document.querySelector('[data-activity-download]');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function () {
                    var lines = entries.map(function (e) {
                        return [e.time, e.kind === 'signal' ? 'DẤU HIỆU' : 'Sự kiện', e.title, e.detail].join(' | ');
                    });
                    var blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'nhat-ky-lam-bai.txt';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                });
            }

            render();
        })();
    </script>
