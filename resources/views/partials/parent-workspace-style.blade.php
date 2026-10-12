{{-- SỬA 12/10 — CSS riêng (tiền tố .pw-) cho khu Phụ huynh dựng theo source mới
     (education-main RoleWorkspace.jsx: ParentOverview / ParentContent).
     Viết tay, không dựa vào lớp Tailwind nào chưa được build (máy chủ không có Vite). --}}
<style>
    .pw { display: grid; gap: 1rem; min-width: 0; color: #1e293b; }
    .pw * { box-sizing: border-box; }
    .pw svg { flex: none; }

    /* Hero (Hero() của RoleWorkspace.jsx) */
    .pw-hero { position: relative; overflow: hidden; border-radius: 24px; min-height: 160px; border: 1px solid #e0f2fe; background: #0d5faf; box-shadow: 0 7px 20px rgba(0,95,180,.09); }
    .pw-hero > img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .pw-hero::before { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(7,89,168,.95), rgba(9,118,201,.72) 55%, rgba(9,118,201,.1)); z-index: 1; }
    .pw-hero-in { position: relative; z-index: 2; display: flex; flex-direction: column; gap: 1rem; padding: 1.25rem; color: #fff; }
    .pw-hero-in small { display: block; font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #e0f2fe; }
    .pw-hero-in h1 { margin: .5rem 0 0; font-size: 1.25rem; line-height: 1.3; font-weight: 700; letter-spacing: -.01em; color: #fff; overflow-wrap: anywhere; }
    .pw-hero-in p { margin: .5rem 0 0; max-width: 36rem; font-size: 12px; line-height: 1.6; color: #f0f9ff; }
    .pw-hero-btn { display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 8px 14px; border-radius: 12px; background: #fff; color: #1d4ed8; font-size: 12px; font-weight: 700; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,.1); transition: background .15s; }
    .pw-hero-btn:hover { background: #f0f9ff; }
    .pw-hero-btn svg { width: 14px; height: 14px; }
    @media (min-width: 640px) { .pw-hero { min-height: 188px; } .pw-hero-in { flex-direction: row; align-items: flex-end; justify-content: space-between; padding: 1.5rem 1.75rem; } .pw-hero-in h1 { font-size: 1.5rem; } }

    /* Thẻ + tiêu đề thẻ (CardTitle) */
    .pw-card { min-width: 0; border: 1px solid #e0f2fe; border-radius: 24px; background: #fff; padding: 1rem; box-shadow: 0 2px 8px rgba(0,90,180,.04); }
    .pw-card--soft { background: #f7fbfd; }
    @media (min-width: 640px) { .pw-card { padding: 1.25rem; } }
    .pw-ct { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .75rem; }
    .pw-ct > div { display: flex; align-items: center; gap: .5rem; min-width: 0; }
    .pw-ct-ic { display: grid; place-items: center; padding: 6px; border-radius: 8px; background: #eff6ff; color: #2563eb; }
    .pw-ct-ic svg { width: 14px; height: 14px; }
    .pw-ct h2 { margin: 0; font-size: 14px; font-weight: 700; color: #1e293b; }
    .pw-ct a { display: inline-flex; align-items: center; gap: 2px; font-size: 12px; font-weight: 700; color: #2563eb; text-decoration: none; white-space: nowrap; }
    .pw-ct a:hover { text-decoration: underline; }
    .pw-ct a svg { width: 12px; height: 12px; }

    /* Dòng tóm tắt + chọn con */
    .pw-sum-top { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .pw-eyebrow { margin: 0; font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #2563eb; }
    .pw-sum h2 { margin: .25rem 0 0; font-size: 16px; font-weight: 700; color: #1e293b; }
    .pw-sub { margin: .25rem 0 0; font-size: 12px; color: #64748b; }
    .pw-pick { display: flex; flex-direction: column; gap: 4px; text-align: right; }
    .pw-pick span { font-size: 11px; font-weight: 700; color: #94a3b8; }
    .pw-pick select { min-height: 40px; max-width: 100%; border: 1px solid #bae6fd; border-radius: 12px; background: #fff; padding: 8px 12px; font: inherit; font-size: 12px; font-weight: 700; color: #334155; }
    .pw-pick select:focus-visible { outline: 2px solid #93c5fd; outline-offset: 1px; }
    .pw-who { display: flex; align-items: center; gap: .75rem; margin-top: .75rem; }
    .pw-av { display: grid; place-items: center; width: 40px; height: 40px; flex: none; overflow: hidden; border-radius: 12px; background: linear-gradient(135deg,#bae6fd,#dbeafe); color: #1e3a8a; font-size: 15px; font-weight: 800; }
    .pw-av img { width: 100%; height: 100%; object-fit: cover; }
    .pw-av--lg { width: 72px; height: 72px; border-radius: 18px; border: 4px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,.1); font-size: 26px; }
    .pw-who-body { min-width: 0; flex: 1; }
    .pw-who-row { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; font-size: 11px; font-weight: 500; color: #64748b; }
    .pw-who-row b { font-weight: 900; color: #1d4ed8; }
    .pw-bar { height: 8px; overflow: hidden; margin-top: 8px; border-radius: 999px; background: #fff; }
    .pw-card:not(.pw-card--soft) .pw-bar, .pw-bar--slim { background: #f1f5f9; }
    .pw-bar--slim { height: 6px; margin-top: 0; }
    .pw-bar i { display: block; height: 100%; border-radius: 999px; background: #2563eb; }
    .pw-bar i.g { background: #10b981; } .pw-bar i.a { background: #f59e0b; }
    .pw-note { margin: .5rem 0 0; font-size: 11px; font-weight: 500; color: #64748b; }

    /* Stat() */
    .pw-grid3 { display: grid; gap: .75rem; grid-template-columns: 1fr; }
    @media (min-width: 640px) { .pw-grid3 { grid-template-columns: repeat(3, 1fr); } .pw-grid4 { grid-template-columns: repeat(4, 1fr) !important; } }
    .pw-grid4 { display: grid; gap: .75rem; grid-template-columns: 1fr 1fr; }
    .pw-stat { min-width: 0; border: 1px solid #e0f2fe; border-radius: 16px; background: #fff; padding: .875rem; box-shadow: 0 2px 8px rgba(0,90,180,.04); }
    .pw-stat-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
    .pw-stat-lb { margin: 0; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #94a3b8; }
    .pw-stat-v { margin: 4px 0 0; font-size: 20px; font-weight: 900; color: #1e293b; line-height: 1.2; }
    .pw-stat-ic { display: grid; place-items: center; padding: 8px; border-radius: 12px; border: 1px solid; }
    .pw-stat-ic svg { width: 16px; height: 16px; }
    .pw-stat-nt { margin: 8px 0 0; font-size: 11px; font-weight: 500; color: #64748b; }
    .pw-t-blue { background: #eff6ff; color: #2563eb; border-color: #dbeafe; }
    .pw-t-emerald { background: #ecfdf5; color: #059669; border-color: #d1fae5; }
    .pw-t-amber { background: #fffbeb; color: #d97706; border-color: #fef3c7; }
    .pw-t-violet { background: #f5f3ff; color: #7c3aed; border-color: #ede9fe; }
    .pw-t-rose { background: #fff1f2; color: #e11d48; border-color: #ffe4e6; }

    /* Bố cục 2 cột */
    .pw-cols { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1280px) { .pw-cols { grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); } }
    .pw-cols2 { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 768px) { .pw-cols2 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .pw-cols-r { grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr); } }

    /* Gợi ý đồng hành */
    .pw-tip { display: flex; flex-direction: column; gap: 1rem; border: 1px solid #e0f2fe; border-radius: 16px; background: #f6fafc; padding: 1rem; }
    @media (min-width: 640px) { .pw-tip { flex-direction: row; align-items: center; } }
    .pw-tip-ic { display: grid; place-items: center; width: 64px; height: 64px; flex: none; border-radius: 16px; background: linear-gradient(135deg,#dbeafe,#bae6fd); color: #2563eb; }
    .pw-tip-ic svg { width: 28px; height: 28px; }
    .pw-tip h3 { margin: 0; font-size: 14px; font-weight: 700; color: #1e293b; }
    .pw-tip p { margin: 4px 0 0; font-size: 12px; line-height: 1.6; color: #64748b; }
    .pw-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 40px; margin-top: 12px; padding: 8px 14px; border: 0; border-radius: 12px; background: #2563eb; color: #fff; font: inherit; font-size: 12px; font-weight: 700; text-decoration: none; box-shadow: 0 1px 3px rgba(37,99,235,.3); cursor: pointer; transition: background .15s; }
    .pw-btn:hover { background: #1d4ed8; }
    .pw-btn--ghost { margin-top: 0; border: 1px solid #bfdbfe; background: #fff; color: #2563eb; box-shadow: none; }
    .pw-btn--ghost:hover { background: #eff6ff; }
    .pw-btn svg { width: 14px; height: 14px; }

    /* Danh sách dòng (lịch, hoạt động, thông báo...) */
    .pw-list { margin: 0; padding: 0; list-style: none; }
    .pw-list > li { display: flex; align-items: center; gap: .75rem; padding: 12px 0; border-top: 1px solid #f1f5f9; }
    .pw-list > li:first-child { border-top: 0; padding-top: 4px; }
    .pw-list > li:last-child { padding-bottom: 4px; }
    .pw-date { flex: none; width: 56px; border-radius: 12px; padding: 6px; text-align: center; background: #f0f9ff; color: #1d4ed8; }
    .pw-date.today { background: #ecfdf5; color: #047857; }
    .pw-date b { display: block; font-size: 10px; font-weight: 700; }
    .pw-date span { display: block; margin-top: 2px; font-size: 12px; font-weight: 900; }
    .pw-li-t { margin: 0; font-size: 12px; font-weight: 700; color: #1e293b; overflow-wrap: anywhere; }
    .pw-li-s { margin: 4px 0 0; font-size: 11px; color: #64748b; }
    .pw-li-s.ok { color: #059669; }
    .pw-empty { padding: 1.25rem .5rem; text-align: center; font-size: 12px; color: #94a3b8; }

    /* Thông báo dạng thẻ nhỏ */
    .pw-notes { display: grid; gap: .5rem; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .pw-notes { grid-template-columns: repeat(3, 1fr); } }
    .pw-note-card { display: flex; align-items: center; gap: .75rem; border: 1px solid #f1f5f9; border-radius: 16px; background: #f8fafb; padding: 12px; text-decoration: none; transition: border-color .15s; }
    .pw-note-card:hover { border-color: #bae6fd; }
    .pw-note-ic { display: grid; place-items: center; width: 36px; height: 36px; flex: none; border-radius: 12px; }
    .pw-note-ic svg { width: 16px; height: 16px; }
    .pw-note-card div b { display: block; font-size: 12px; font-weight: 700; color: #1e293b; }
    .pw-note-card div em { display: block; margin-top: 4px; font-size: 11px; font-style: normal; color: #64748b; }

    /* Hồ sơ con (Con của tôi) */
    .pw-kid { overflow: hidden; padding: 0; }
    .pw-kid-top { display: flex; flex-direction: column; gap: 1rem; padding: 1.25rem; background: linear-gradient(90deg,#eff6ff,#f0f9ff,#fff); }
    @media (min-width: 640px) { .pw-kid-top { flex-direction: row; align-items: center; } }
    .pw-kid-top h2 { display: inline; margin: 0; font-size: 18px; font-weight: 900; color: #1e293b; }
    .pw-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; }
    .pw-chip.ok { background: #d1fae5; color: #047857; } .pw-chip.wait { background: #fef3c7; color: #b45309; } .pw-chip.off { background: #f1f5f9; color: #64748b; }
    .pw-chip.blue { background: #dbeafe; color: #1d4ed8; } .pw-chip.rose { background: #ffe4e6; color: #be123c; }
    .pw-kid-stats { display: grid; grid-template-columns: 1fr; border-top: 1px solid #f1f5f9; }
    @media (min-width: 640px) { .pw-kid-stats { grid-template-columns: repeat(3, 1fr); } .pw-kid-stats > div + div { border-left: 1px solid #f1f5f9; border-top: 0 !important; } }
    .pw-kid-stats > div { padding: 1rem; } .pw-kid-stats > div + div { border-top: 1px solid #f1f5f9; }
    .pw-kid-stats small { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #94a3b8; }
    .pw-kid-stats strong { display: block; margin-top: 4px; font-size: 24px; font-weight: 900; line-height: 1.2; }
    .pw-kid-stats strong em { font-size: 13px; font-style: normal; }
    .pw-kid-stats p { margin: 4px 0 0; font-size: 10px; color: #64748b; }
    .pw-prog { display: grid; gap: 1rem; }
    .pw-prog-h { display: flex; justify-content: space-between; gap: .5rem; font-size: 12px; }
    .pw-prog-h b { font-weight: 700; color: #334155; } .pw-prog-h span { font-weight: 900; color: #64748b; }
    .pw-prog .pw-bar { margin-top: 8px; }
    .pw-dot { width: 8px; height: 8px; margin-top: 5px; flex: none; border-radius: 999px; background: #3b82f6; }
    .pw-dot.emerald { background: #10b981; } .pw-dot.amber { background: #f59e0b; } .pw-dot.rose { background: #f43f5e; } .pw-dot.blue { background: #3b82f6; }
    .pw-li-top { margin: 0; font-size: 10px; font-weight: 700; color: #2563eb; }
    .pw-li-top.emerald { color: #059669; } .pw-li-top.amber { color: #d97706; } .pw-li-top.rose { color: #e11d48; }
    .pw-list.align-start > li { align-items: flex-start; }

    /* Lịch / điểm danh */
    .pw-cards { display: grid; gap: .75rem; grid-template-columns: 1fr; }
    @media (min-width: 640px) { .pw-cards { grid-template-columns: repeat(2, 1fr); } } @media (min-width: 1024px) { .pw-cards { grid-template-columns: repeat(3, 1fr); } }
    .pw-sess { border-radius: 16px; background: #f0f9ff; padding: 1rem; }
    .pw-sess.today { background: #ecfdf5; }
    .pw-sess b { display: block; font-size: 12px; font-weight: 900; color: #1e293b; }
    .pw-sess p { margin: 4px 0 0; font-size: 11px; color: #64748b; }
    .pw-sess .t { margin-top: 10px; font-size: 12px; font-weight: 700; color: #1e293b; }
    .pw-tag { display: inline-block; margin-top: 10px; font-size: 10px; font-weight: 700; }
    .pw-tag.emerald { color: #059669; } .pw-tag.blue { color: #2563eb; } .pw-tag.amber { color: #d97706; } .pw-tag.rose { color: #e11d48; }

    /* Bảng */
    .pw-table-wrap { overflow-x: auto; }
    .pw-table { width: 100%; border-collapse: collapse; font-size: 12px; text-align: left; }
    .pw-table th { padding: 0 8px 12px 0; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #94a3b8; white-space: nowrap; }
    .pw-table td { padding: 12px 8px 12px 0; border-top: 1px solid #f1f5f9; vertical-align: top; color: #64748b; }
    .pw-table td.k { font-weight: 700; color: #334155; }
    .pw-table .r { text-align: right; }
    .pw-sc { font-weight: 900; color: #1e293b; }

    /* Số lớn */
    .pw-big { margin: 0; font-size: 36px; font-weight: 900; line-height: 1.1; }
    .pw-big em { font-size: 13px; font-style: normal; font-weight: 500; color: #94a3b8; }
    .pw-big.b { color: #2563eb; } .pw-big.g { color: #059669; }
    .pw-big-t { margin: 8px 0 0; font-size: 12px; font-weight: 700; color: #334155; }

    /* Lọc bài được giao */
    .pw-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
    .pw-tabs button { min-height: 34px; padding: 6px 12px; border: 1px solid #e0f2fe; border-radius: 999px; background: #fff; font: inherit; font-size: 11px; font-weight: 700; color: #64748b; cursor: pointer; }
    .pw-tabs button.on { border-color: #2563eb; background: #2563eb; color: #fff; }
    .pw-task { display: grid; gap: 8px; padding: 14px 0; border-top: 1px solid #f1f5f9; }
    .pw-task:first-child { border-top: 0; padding-top: 0; }
    @media (min-width: 768px) { .pw-task { grid-template-columns: minmax(0, 1.6fr) minmax(0, .8fr) minmax(0, 1fr) minmax(0, .7fr); align-items: center; gap: 14px; } }
    .pw-task h3 { margin: 0; font-size: 13px; font-weight: 700; color: #1e293b; overflow-wrap: anywhere; }
    .pw-task small { display: block; margin-top: 3px; font-size: 11px; color: #64748b; }
    .pw-task .rt { font-size: 12px; font-weight: 900; color: #1e293b; }
    @media (min-width: 768px) { .pw-task .rt { text-align: right; } }

    /* Liên kết con / đánh giá */
    .pw-link { display: flex; align-items: center; gap: .75rem; padding: 12px; border-radius: 16px; background: #f0f9ff; }
    .pw-link + .pw-link { margin-top: 8px; }
    .pw-link .nm { flex: 1; min-width: 0; } .pw-link .nm b { display: block; font-size: 12px; font-weight: 700; color: #1e293b; } .pw-link .nm span { display: block; margin-top: 2px; font-size: 10px; color: #64748b; }
    .pw-form { display: flex; gap: 8px; margin-top: 8px; }
    .pw-form input { flex: 1; min-width: 0; min-height: 40px; border: 1px solid #bae6fd; border-radius: 12px; padding: 8px 12px; font: inherit; font-size: 13px; }
    .pw-form input:focus-visible { outline: 2px solid #93c5fd; }
    .pw-ok { border: 1px solid #d1fae5; border-radius: 16px; background: #ecfdf5; padding: 1rem; }
    .pw-ok b { display: block; font-size: 12px; font-weight: 900; color: #047857; } .pw-ok p { margin: 4px 0 0; font-size: 11px; color: #475569; line-height: 1.6; }
    .pw-warn { border: 1px solid #fef3c7; border-radius: 16px; background: #fffbeb; padding: 1rem; }
    .pw-warn b { display: block; font-size: 12px; font-weight: 900; color: #b45309; } .pw-warn p { margin: 4px 0 0; font-size: 11px; color: #475569; line-height: 1.6; }
    .pw-stars { color: #f59e0b; letter-spacing: 2px; font-size: 14px; }
    .pw-rules { display: grid; gap: 12px; margin: 0; padding: 0; list-style: none; font-size: 11px; line-height: 1.6; color: #475569; }
    .pw-empty-card { text-align: center; padding: 2.5rem 1rem; }
    .pw-empty-card b { display: block; font-size: 14px; color: #334155; } .pw-empty-card p { margin: 6px auto 0; max-width: 28rem; font-size: 12px; color: #64748b; line-height: 1.6; }
    .pw-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

    /* Phân trang (danh sách dài) */
    .pw-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 14px; padding-top: 14px; border-top: 1px solid #f1f5f9; }
    .pw-pager p { margin: 0; font-size: 11px; color: #64748b; }
    .pw-pager nav { display: flex; flex-wrap: wrap; gap: 4px; }
    .pw-pager button { display: inline-grid; place-items: center; min-width: 34px; height: 34px; padding: 0 8px; border: 1px solid #e0f2fe; border-radius: 10px; background: #fff; font: inherit; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; }
    .pw-pager button:hover:not(:disabled) { border-color: #93c5fd; color: #1d4ed8; }
    .pw-pager button.on { border-color: #2563eb; background: #2563eb; color: #fff; }
    .pw-pager button:disabled { opacity: .4; cursor: default; }
    .pw-pager button svg { width: 14px; height: 14px; }
</style>
<script>
    /* Phân trang phía trình duyệt cho khu Phụ huynh: dữ liệu đã được máy chủ giới hạn số dòng. */
    window.pwPager = window.pwPager || function (total, size, extra) {
        var base = {
            page: 1, size: size || 10, total: total || 0,
            get pages() { return Math.max(1, Math.ceil(this.total / this.size)); },
            get from() { return this.total === 0 ? 0 : (this.page - 1) * this.size + 1; },
            get to() { return Math.min(this.total, this.page * this.size); },
            get nums() {
                var out = [], p = this.pages, c = this.page, i;
                for (i = 1; i <= p; i++) { if (i === 1 || i === p || Math.abs(i - c) <= 1) { out.push(i); } else if (out[out.length - 1] !== 0) { out.push(0); } }
                return out;
            },
            show(i) { return i >= (this.page - 1) * this.size && i < this.page * this.size; },
            go(n) { this.page = Math.min(Math.max(1, n), this.pages); },
        };
        /* defineProperties (không dùng Object.assign) để giữ nguyên các getter của extra. */
        return extra ? Object.defineProperties(base, Object.getOwnPropertyDescriptors(extra)) : base;
    };
</script>
