{{-- KIỂU CHUNG CHO MÀN "KHO BÀI TẬP / CÂU HỎI VÀ ĐỀ" CỦA ADMIN (danh sách + trang chi tiết).

     SỬA 8/10 (khách: "cập nhật lại UI màn Kho bài tập / câu hỏi và đề theo source mới, logic giữ nguyên")
     — chuyển phong cách của education-main/src/components/AdminContentWorkspace.jsx + adminContent.css
     (dải tab nhóm, thanh tìm/lọc, bảng 5 cột "Nội dung/Nguồn · Phân loại · Độ khó · Trạng thái · Thao tác",
     phân trang) sang Blade. Dùng lại khối .acx-/.apx- của các màn Khóa & Lớp / Tài liệu, chỉ thêm
     phần riêng, tiền tố .akx-. Không route, không tên field, không truy vấn nào đổi. --}}
@include('partials.admin-products-ui')
<style>
    a.apx-tab{text-decoration:none}
    [data-pg-item][hidden]{display:none!important}

    /* ===== Bộ lọc câu hỏi ===== */
    .akx-pad{padding:16px 20px}
    .akx-pad+.akx-pad{border-top:1px solid #e8eef5}
    .akx-chips{display:flex;flex-wrap:wrap;gap:8px}
    .akx-chip{display:inline-flex;align-items:center;gap:5px;min-height:32px;padding:0 12px;border:1px solid #d7e3f0;border-radius:999px;background:#fff;color:#4b627a;font-size:12px;font-weight:600;text-decoration:none;transition:background .15s,border-color .15s}
    .akx-chip:hover{background:#f0f7ff;border-color:#93c5fd}
    .akx-chip.is-on{background:#2563eb;border-color:#2563eb;color:#fff}
    .akx-chip small{opacity:.7;font-weight:600}
    .akx-chip--warn{border-color:#f5dca0;background:#fffaec;color:#9a6a12}
    .akx-chip--warn.is-on{background:#d97706;border-color:#d97706;color:#fff}
    .akx-chip--type{min-height:36px;border-radius:12px;font-weight:700;font-size:13px}
    .akx-form{display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px}
    .akx-form label.akx-lbl{display:block;margin-bottom:4px;font-size:12px;font-weight:600;color:#587089}
    .akx-form .acx-search{flex:none;width:100%}
    .akx-form .admin-input{border:1px solid #bdcbdc;border-radius:10px}
    .akx-grow{flex:1 1 220px;min-width:200px}

    /* ===== Dòng đếm kết quả ===== */
    .akx-sub{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:6px 16px;padding:14px 20px 12px;font-size:12px;color:#587089}
    .akx-sub b{color:#1e3a5f}
    .akx-hint{margin:0;padding:0 20px 12px;font-size:12px;line-height:1.55;color:#64748b}
    .akx-hint strong{color:#334155}

    /* ===== Bảng ===== */
    .akx-table{min-width:760px}
    .acx-table.akx-table tr>:nth-child(2){display:table-cell}
    .acx-table.akx-table thead th:first-child{width:auto}
    .akx-title{display:block;font-size:14px;font-weight:700;line-height:1.5;color:#1e3a5f;text-decoration:none;overflow-wrap:anywhere}
    .akx-title:hover{color:#2563eb;text-decoration:underline}
    .akx-meta{display:flex;flex-wrap:wrap;align-items:center;gap:2px 8px;margin-top:3px;font-size:12px;color:#637b92}
    .akx-meta code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px;color:#587089}
    .akx-src{margin-top:3px;font-size:12px;color:#637b92}
    .akx-pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:#eef4fc;color:#3b5f8a;border:1px solid #d3e1f3}
    .akx-pill--warn{background:#fffaec;color:#9a6a12;border-color:#f5dca0}
    .akx-k{font-size:13px;font-weight:600;color:#365673}
    .akx-k2{margin-top:3px;font-size:12px;color:#637b92}
    .akx-mute{color:#8aa0b6}
    .akx-act{display:flex;flex-direction:column;align-items:flex-end;gap:8px}
    .akx-act__links{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:6px 14px}
    .akx-act form{display:inline;margin:0}
    .akx-lnk{display:inline-flex;align-items:center;gap:4px;padding:0;border:0;background:none;font:inherit;font-size:13px;font-weight:600;color:#2563eb;text-decoration:none;cursor:pointer}
    .akx-lnk:hover{text-decoration:underline}
    .akx-lnk--mute{color:#345674}
    .akx-lnk--ok{color:#059669}
    .akx-lnk--del{color:#be123c}
    .akx-lnk svg{width:14px;height:14px}
    .akx-empty{padding:44px 16px 52px;text-align:center;color:#64748b}
    .akx-empty strong{display:block;margin-bottom:4px;font-size:15px;color:#1e3a5f}

    /* ===== Phân trang ===== */
    .akx-pg{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;padding:14px 20px;border-top:1px solid #e8eef5;font-size:12px;color:#587089}
    .akx-pg__btns{display:flex;flex-wrap:wrap;align-items:center;gap:6px}
    .akx-pgb{display:inline-grid;place-items:center;min-width:34px;height:34px;padding:0 8px;border:1px solid #cbdcec;border-radius:9px;background:#fff;color:#345b80;font:inherit;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;transition:background .15s,border-color .15s}
    .akx-pgb:hover:not(.is-off):not(.is-on){background:#eff6ff;border-color:#93c5fd}
    .akx-pgb.is-on{background:#2563eb;border-color:#2563eb;color:#fff;cursor:default}
    .akx-pgb.is-off{opacity:.4;cursor:not-allowed}
    .akx-pgb svg{width:16px;height:16px}
    .akx-dots{min-width:20px;text-align:center;color:#8aa0b6}
    @media (max-width:767px){
        .akx-table{min-width:0!important}
        .akx-table thead{display:none}
        .akx-table tbody tr{display:flex;flex-wrap:wrap;align-items:flex-start;gap:6px 16px;padding:14px;border-bottom:1px solid #e8eef5}
        .akx-table tbody td{display:block!important;padding:0!important;border:0!important;text-align:left!important}
        .akx-table tbody td:first-child,.akx-table tbody td:last-child{flex:1 1 100%}
        .akx-table.is-q tbody td:nth-child(3)::before{content:"Độ khó: ";color:#8aa0b6;font-size:12px}
        .akx-act{align-items:flex-start;margin-top:4px}
        .akx-act__links{justify-content:flex-start}
    }
    @media (max-width:767px){.akx-pad,.akx-sub,.akx-hint,.akx-pg{padding-left:14px;padding-right:14px}}
</style>
<script>
    /* Phân trang phía trình duyệt (10 mục/trang) cho các tab không có phân trang máy chủ: Đề thi, Tag/Chuyên đề, Chờ rà soát.
       Khung: [data-pg="tên"] chứa các [data-pg-item]; thanh chuyển trang: [data-pg-nav="tên"]. Không gọi máy chủ. */
    document.addEventListener('DOMContentLoaded', function () {
        var SIZE = 10;
        var CHEV = {
            l: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>',
            r: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>'
        };
        Array.prototype.forEach.call(document.querySelectorAll('[data-pg]'), function (box) {
            var nav = document.querySelector('[data-pg-nav="' + box.dataset.pg + '"]');
            var items = Array.prototype.slice.call(box.querySelectorAll('[data-pg-item]'));
            var unit = box.dataset.unit || 'kết quả';
            var page = 1;
            if (!nav) return;
            function btn(html, cls, fn, label) {
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'akx-pgb ' + (cls || ''); b.innerHTML = html;
                if (label) b.setAttribute('aria-label', label);
                if (cls && cls.indexOf('is-off') !== -1) b.disabled = true; else if (fn) b.addEventListener('click', fn);
                return b;
            }
            function render() {
                var pages = Math.max(1, Math.ceil(items.length / SIZE));
                page = Math.min(Math.max(1, page), pages);
                var from = (page - 1) * SIZE;
                items.forEach(function (el, i) { el.hidden = !(i >= from && i < from + SIZE); });
                nav.innerHTML = '';
                var info = document.createElement('span');
                info.textContent = (items.length ? (from + 1) + '–' + Math.min(from + SIZE, items.length) : '0–0') + ' / ' + items.length + ' ' + unit + (pages > 1 ? ' · Trang ' + page + '/' + pages : '');
                var wrap = document.createElement('div'); wrap.className = 'akx-pg__btns';
                wrap.appendChild(btn(CHEV.l, page === 1 ? 'is-off' : '', function () { page--; render(); }, 'Trang trước'));
                var last = 0;
                for (var p = 1; p <= pages; p++) {
                    if (p === 1 || p === pages || Math.abs(p - page) <= 1) {
                        if (last && p - last > 1) { var d = document.createElement('span'); d.className = 'akx-dots'; d.textContent = '…'; wrap.appendChild(d); }
                        (function (n) { wrap.appendChild(btn(String(n), n === page ? 'is-on' : '', function () { page = n; render(); }, 'Trang ' + n)); })(p);
                        last = p;
                    }
                }
                wrap.appendChild(btn(CHEV.r, page === pages ? 'is-off' : '', function () { page++; render(); }, 'Trang sau'));
                nav.appendChild(info); nav.appendChild(wrap);
                nav.hidden = items.length === 0;
            }
            render();
        });
    });
</script>
