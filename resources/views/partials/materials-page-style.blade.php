{{-- ═══════════ CSS TRANG TÀI LIỆU (/tai-lieu) ═══════════
     SỬA 9/10 — chép từ education-main/src/components/materials.css (bản mẫu mới) và đổi tiền tố
     material-* -> mp-* để không đụng class của nơi khác.

     VÌ SAO viết CSS thường chứ không dùng class Tailwind tuỳ ý: máy chủ KHÔNG chạy được vite, mọi class
     Tailwind phải có sẵn trong bản CSS đã build — bộ màu/kích thước mới thì không có. --}}
<style>
    .mp-page *, .mp-modal-back * { box-sizing: border-box; }
    .mp-page button, .mp-page input, .mp-page select, .mp-modal-back button { font-family: inherit; }
    .mp-page a.mp-primary, .mp-page a.mp-primary:hover, .mp-hero-chips > a, .mp-modal a.mp-primary { border: 0; text-decoration: none; }
    .mp-page { display: flex; flex-direction: column; gap: 16px; color: #365b7a; min-width: 0; }
    .mp-panel { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .mp-hero { position: relative; overflow: hidden; border-radius: 24px; padding: 20px; color: #fff; background: linear-gradient(115deg, #123b68, #126f91 68%, #1596ae); box-shadow: 0 8px 24px #123b6814; }
    @media (min-width: 640px) { .mp-hero { padding: 28px; } }
    .mp-hero h1 { color: #fff; margin: 12px 0 0; font-size: 24px; line-height: 32px; font-weight: 900; letter-spacing: -.01em; }
    .mp-hero-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .2; mix-blend-mode: screen; pointer-events: none; }
    .mp-hero-inner { position: relative; display: flex; flex-direction: column; justify-content: space-between; gap: 20px; }
    @media (min-width: 1024px) { .mp-hero-inner { flex-direction: row; align-items: center; } }
    .mp-hero-badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; background: rgba(255,255,255,.15); padding: 4px 12px; font-size: 11px; font-weight: 600; }
    .mp-hero-badge svg { width: 13px; height: 13px; }
    .mp-hero p.mp-lead { margin: 8px 0 0; max-width: 42rem; font-size: 12px; line-height: 24px; color: #e0f2fe; }
    @media (min-width: 640px) { .mp-hero p.mp-lead { font-size: 14px; } }
    .mp-hero-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; font-size: 11px; }
    .mp-hero-chips > span, .mp-hero-chips > a { border: 1px solid rgba(255,255,255,.2); border-radius: 8px; background: rgba(255,255,255,.1); padding: 8px 12px; color: #fff; }
    .mp-hero-chips > a { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; background: #f5b83b; border-color: #f5b83b; color: #4a3200; }
    .mp-hero-chips > a svg { width: 14px; height: 14px; }
    .mp-hero-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; flex-shrink: 0; border: 1px solid rgba(255,255,255,.2); border-radius: 16px; background: rgba(255,255,255,.1); padding: 16px; }
    @media (min-width: 1024px) { .mp-hero-stats { width: 256px; } }
    .mp-hero-stats b { display: block; font-size: 24px; line-height: 32px; font-weight: 800; }
    .mp-hero-stats b.is-gold { color: #fcd34d; }
    .mp-hero-stats small { display: block; margin-top: 4px; font-size: 11px; color: #e0f2fe; }

    .mp-filters { border: 1px solid #ddeaf0; border-radius: 16px; background: #fff; padding: 12px; }
    @media (min-width: 640px) { .mp-filters { padding: 16px; } }
    .mp-filters-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
    .mp-filters-head h2 { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 12px; font-weight: 700; color: #365b7a; }
    .mp-filters-head svg { width: 15px; height: 15px; }
    .mp-scopes { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e7eff3; }
    .mp-tab { border: 0; display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 42px; padding: 10px 14px; border-radius: 10px; background: #f4f8fb; color: #45657d; font-size: 12px; font-weight: 700; transition: background .15s; cursor: pointer; }
    .mp-tab svg { width: 15px; height: 15px; }
    .mp-tab:hover { background: #eaf5f8; }
    .mp-tab.is-active { background: #126f91; color: #fff; box-shadow: 0 3px 8px #126f9124; }
    .mp-tab .mp-count { min-width: 21px; border-radius: 6px; padding: 1px 5px; font-size: 10px; text-align: center; background: #607a9012; }
    .mp-tab.is-active .mp-count { background: #ffffff24; }
    .mp-tab .mp-ico { display: inline-flex; }
    .mp-label { display: block; margin-bottom: 6px; font-size: 11px; font-weight: 600; color: #52687b; }
    .mp-cats { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
    .mp-cat { border: 0; min-height: 36px; border-radius: 8px; padding: 0 12px; font-size: 11px; font-weight: 700; background: #f4f8fb; color: #46657a; transition: background .15s; cursor: pointer; }
    .mp-cat:hover { background: #eaf5f8; }
    .mp-cat[aria-pressed="true"] { background: #138a8a; color: #fff; }
    .mp-grid-filters { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    @media (min-width: 1024px) { .mp-grid-filters { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .mp-span-all { grid-column: 1 / -1; min-width: 0; }
    @media (min-width: 1024px) { .mp-span-3 { grid-column: span 3 / span 3; } }
    .mp-span-2 { grid-column: span 2 / span 2; min-width: 0; }
    .mp-input { display: block; width: 100%; min-width: 0; min-height: 40px; border: 1px solid #d6e3ef; border-radius: 10px; background: #f8fafb; padding: 9px 12px; color: #365b7a; font-size: 12px; }
    .mp-input:focus { outline: 2px solid #9dc8d7; outline-offset: 1px; background: #fff; }
    .mp-search { position: relative; display: block; }
    .mp-search svg { position: absolute; left: 12px; top: 12px; width: 16px; height: 16px; color: #94a3b8; pointer-events: none; }
    .mp-search .mp-input { padding-left: 36px; }

    .mp-panel-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 0 4px; }
    .mp-panel-head h2 { margin: 0; font-size: 14px; font-weight: 700; color: #123b68; }
    .mp-panel-head p { margin: 4px 0 0; font-size: 11px; color: #607a90; }
    .mp-text-btn { border: 0; min-height: 34px; padding: 5px 7px; border-radius: 6px; color: #126f91; font-size: 11px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 4px; }
    .mp-text-btn:hover { background: #eaf5f8; }
    .mp-text-btn svg { width: 12px; height: 12px; }
    .mp-primary { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 40px; padding: 9px 12px; border-radius: 9px; background: #126f91; color: #fff; font-size: 11px; font-weight: 700; transition: background .15s; cursor: pointer; text-align: center; }
    .mp-primary:hover { background: #0f5e7b; color: #fff; }
    .mp-primary svg { width: 15px; height: 15px; }
    .mp-primary.is-green { background: #28795e; }
    .mp-primary.is-green:hover { background: #21664f; }
    .mp-primary.is-amber { background: #d78a2d; }
    .mp-primary.is-amber:hover { background: #b9741f; }
    .mp-primary.is-light { background: #eaf5f8; color: #126f91; }
    .mp-primary.is-light:hover { background: #d8ecf2; color: #0f5e7b; }
    .mp-icon-btn { display: inline-grid; flex-shrink: 0; place-items: center; width: 38px; height: 38px; border: 1px solid #ddeaf0; border-radius: 10px; color: #607a90; background: #fff; cursor: pointer; }
    .mp-icon-btn:hover { background: #f4f8fb; }
    .mp-icon-btn svg { width: 16px; height: 16px; }
    .mp-icon-btn:disabled { opacity: .45; cursor: not-allowed; }
    .mp-tab:focus-visible, .mp-cat:focus-visible, .mp-primary:focus-visible, .mp-text-btn:focus-visible, .mp-icon-btn:focus-visible { outline: 3px solid #9dc8d7; outline-offset: 2px; }

    .mp-cards { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; }
    @media (min-width: 640px) { .mp-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1280px) { .mp-cards { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .mp-card { display: flex; min-width: 0; flex-direction: column; overflow: hidden; border: 1px solid #ddeaf0; border-radius: 18px; background: #fff; box-shadow: 0 2px 12px #1c5b790a; }
    .mp-card:hover { border-color: #bddbe6; box-shadow: 0 6px 18px #1c5b7912; }
    .mp-card h3 a:hover { color: #126f91; }
    .mp-card-tools { display: flex; justify-content: flex-end; padding: 12px 12px 0; }
    .mp-cover { display: flex; flex-shrink: 0; align-items: center; justify-content: center; width: 100%; height: 150px; margin-top: 8px; padding: 8px; overflow: hidden; background: #f8fbfc; cursor: pointer; }
    .mp-cover img { width: 100%; height: 100%; object-fit: contain; transition: transform .2s; }
    .mp-cover:hover img { transform: scale(1.03); }
    .mp-cover:focus-visible { outline: 3px solid #9dc8d7; outline-offset: -3px; }
    .mp-card-body { display: flex; flex: 1 1 auto; flex-direction: column; padding: 16px; }
    .mp-unit { display: flex; align-items: center; gap: 4px; font-size: 10px; color: #607a90; }
    .mp-unit svg { width: 12px; height: 12px; }
    .mp-card h3 { min-height: 40px; margin: 10px 0 8px; font-size: 14px; line-height: 20px; font-weight: 700; color: #123b68; }
    .mp-quality { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; min-width: 0; margin-bottom: 12px; }
    .mp-quality-row { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; }
    .mp-quality-row > b { font-size: 10px; font-weight: 600; color: #607a90; }
    .mp-badges { display: flex; flex-wrap: wrap; gap: 6px; font-size: 10px; font-weight: 600; }
    .mp-status { display: inline-flex; align-items: center; gap: 4px; width: fit-content; padding: 3px 7px; border: 1px solid transparent; border-radius: 6px; font-size: 10px; font-weight: 600; }
    .mp-status svg { width: 12px; height: 12px; }
    .mp-status.is-opened { color: #28795e; background: #eff9f5; border-color: #d4ede2; }
    .mp-status.is-todo { color: #866523; background: #fff7e3; border-color: #f2e1b6; }
    .mp-status.is-overdue { color: #b42318; background: #fff1f1; border-color: #f5d5d5; }
    .mp-status.is-neutral { color: #607a90; background: #f4f8fb; border-color: #ddeaf0; }
    .mp-desc { margin: 8px 0 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 11px; line-height: 20px; color: #607a90; }
    .mp-author { margin: 4px 0 12px; font-size: 10px; line-height: 16px; color: #64748b; }
    .mp-buy { margin-top: auto; border-top: 1px solid #e7eff3; padding-top: 12px; }
    .mp-price-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 4px; }
    .mp-price-row small { display: block; font-size: 10px; color: #607a90; }
    .mp-price-row strong { display: block; margin-top: 2px; font-size: 16px; font-weight: 800; color: #123b68; }
    .mp-price-row svg { width: 16px; height: 16px; margin-bottom: 4px; color: #607a90; }
    .mp-print-note { min-height: 16px; margin: 4px 0 0; font-size: 10px; color: #607a90; }
    .mp-buy .mp-primary { width: 100%; margin-top: 12px; }
    .mp-buy .mp-text-btn { width: 100%; margin-top: 4px; }
    .mp-empty { border: 1px dashed #c9dfe8; border-radius: 16px; background: #fff; padding: 48px 20px; text-align: center; }
    .mp-empty > svg { width: 40px; height: 40px; margin: 0 auto; color: #9dc8d7; }
    .mp-empty h3 { margin: 12px 0 0; font-size: 14px; font-weight: 700; color: #123b68; }
    .mp-empty p { max-width: 28rem; margin: 8px auto 0; font-size: 12px; line-height: 20px; color: #607a90; }
    .mp-pager { display: flex; align-items: center; justify-content: space-between; border: 1px solid #ddeaf0; border-radius: 12px; background: #fff; padding: 12px; font-size: 12px; color: #607a90; }
    .mp-pager div { display: flex; gap: 8px; }
    .mp-note { padding: 0 4px; font-size: 10px; line-height: 20px; color: #71869a; margin: 0; }
    .mp-alert-info { margin: 0; border: 1px solid #e0f2fe; border-radius: 12px; background: #f0f9ff; padding: 8px 12px; font-size: 11px; line-height: 20px; color: #52687b; }

    /* ── bảng "Tài liệu được giao / đã giao" ── */
    .mp-asg { overflow: hidden; border: 1px solid #ddeaf0; border-radius: 16px; background: #fff; }
    .mp-asg-head { display: none; }
    .mp-asg-row { display: grid; gap: 14px; padding: 16px; border-top: 1px solid #e7eff3; }
    .mp-asg-row:nth-child(odd) { background: #f8fbfc; }
    .mp-asg-row img { flex-shrink: 0; width: 56px; height: 80px; border-radius: 8px; background: #f8fafc; object-fit: contain; }
    .mp-asg-title { margin: 6px 0 0; font-size: 12px; line-height: 20px; font-weight: 700; color: #123b68; }
    .mp-asg-note { margin: 8px 0 0; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 11px; line-height: 20px; color: #607a90; }
    .mp-asg-mid { min-width: 0; font-size: 11px; line-height: 20px; color: #52687b; }
    .mp-asg-mid p { margin: 0; }
    .mp-asg-who { display: flex; align-items: center; gap: 4px; margin-bottom: 4px !important; font-weight: 700; color: #123b68; }
    .mp-asg-who svg { width: 13px; height: 13px; flex-shrink: 0; }
    .mp-asg-time { font-size: 11px; line-height: 20px; }
    .mp-asg-time p { margin: 0; }
    .mp-asg-time .is-deadline { display: flex; align-items: center; gap: 4px; font-weight: 600; color: #866523; }
    .mp-asg-time .is-deadline svg { width: 13px; height: 13px; }
    .mp-asg-time .is-muted { margin-top: 4px; color: #607a90; }
    .mp-asg-time .is-bad { color: #b42318; }
    .mp-asg-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    @media (min-width: 1024px) {
        .mp-asg-head, .mp-asg-row { display: grid; grid-template-columns: minmax(0, 1.8fr) minmax(130px, 1fr) minmax(160px, 1fr) 150px; align-items: center; gap: 20px; }
        .mp-asg-head { padding: 12px 16px; background: #eef4fa; font-size: 10px; text-transform: uppercase; font-weight: 800; letter-spacing: .03em; color: #46657a; }
        .mp-asg-actions { flex-direction: column; align-items: stretch; }
    }
    .mp-asg-left { display: flex; min-width: 0; align-items: flex-start; gap: 12px; }

    /* ── sao độ khó / sao đánh giá dựng bằng Alpine (hộp chi tiết, bảng được giao) ── */
    .mp-sw { display: inline-flex; }
    .mp-sw svg { width: 13px; height: 13px; flex-shrink: 0; fill: none; color: #c9d7e0; }
    .mp-sw.is-on svg { fill: #e9b44a; color: #c58b31; }
    .mp-rs { position: relative; display: inline-block; width: 14px; height: 14px; }
    .mp-rs > svg { position: absolute; inset: 0; width: 14px; height: 14px; fill: none; color: #c9d7e0; }
    .mp-rs-fill { position: absolute; top: 0; bottom: 0; left: 0; overflow: hidden; }
    .mp-rs-fill svg { width: 14px; height: 14px; max-width: none; fill: #e9b44a; color: #c58b31; }
    .mp-q-inline { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 2px 6px; font-size: 11px; }
    .mp-q-inline .mp-q-stars { display: inline-flex; gap: 2px; align-items: center; }
    .mp-q-inline strong { color: #8a5b12; }
    .mp-q-inline .mp-q-count { color: #607a90; }
    .mp-q-num { margin-left: 4px; font-size: 10px; font-weight: 600; color: #607a90; }

    /* ── hộp chi tiết giá & quyền sử dụng ── */
    .mp-modal-back { position: fixed; inset: 0; z-index: 70; display: flex; align-items: center; justify-content: center; padding: 12px; background: rgba(11, 32, 61, .6); backdrop-filter: blur(3px); }
    .mp-modal { position: relative; display: flex; flex-direction: column; width: min(660px, 100%); max-height: min(860px, calc(100dvh - 24px)); overflow: hidden; border: 1px solid #ddeaf0; border-radius: 22px; background: #fff; color: #365b7a; box-shadow: 0 25px 70px #123b6840; }
    .mp-modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-shrink: 0; padding: 20px 24px 16px; border-bottom: 1px solid #e7eff3; }
    .mp-eyebrow { margin: 0; font-size: 10px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; color: #607a90; }
    .mp-modal-head h2 { margin: 4px 0 0; font-size: 18px; font-weight: 800; color: #123b68; }
    .mp-modal-body { min-height: 0; overflow-y: auto; padding: 4px 24px 22px; }
    .mp-modal-item { display: flex; align-items: flex-start; gap: 16px; margin-top: 18px; }
    .mp-modal-item img { flex-shrink: 0; width: 80px; height: 112px; border-radius: 12px; background: #f8fafc; object-fit: contain; }
    .mp-modal-item h3 { margin: 0; font-size: 14px; font-weight: 700; color: #123b68; }
    .mp-modal-item p { margin: 8px 0 0; font-size: 12px; color: #64748b; }
    .mp-modal-item p.is-accent { color: #126f91; }
    .mp-opt { display: flex; align-items: center; gap: 12px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; margin-top: 8px; }
    .mp-opt.is-on { border-color: #0ea5e9; background: #f0f9ff; }
    .mp-opt span { flex: 1 1 auto; font-size: 12px; font-weight: 600; }
    .mp-opt small { display: block; margin-top: 4px; font-weight: 400; color: #64748b; }
    .mp-opt strong { font-size: 14px; color: #123b68; }
    .mp-legend { margin: 20px 0 0; font-size: 12px; font-weight: 700; color: #365b7a; }
    .mp-box { margin-top: 16px; border-radius: 12px; padding: 12px; font-size: 12px; line-height: 20px; }
    .mp-box.is-green { border: 1px solid #a7f3d0; background: #ecfdf5; color: #065f46; }
    .mp-box.is-amber { border: 1px solid #fde68a; background: #fffbeb; color: #78350f; }
    .mp-box.is-sky { background: #f0f9ff; color: #365b7a; }
    .mp-box p { margin: 0; }
    .mp-modal-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px; margin-top: 20px; }
    .mp-modal-actions .mp-primary, .mp-modal-actions a.mp-primary { padding: 9px 16px; }
    .mp-meta-line { display: flex; align-items: center; gap: 6px; margin: 12px 0 0; font-size: 12px; color: #64748b; }
    .mp-meta-line svg { width: 14px; height: 14px; }
    @media (max-width: 480px) { .mp-modal-head { padding: 16px 18px 12px; } .mp-modal-body { padding: 0 16px 18px 18px; } .mp-tab { flex: 1 1 130px; padding: 9px 8px; font-size: 11px; } }

    /* ── popup Giao tài liệu: thêm select/textarea cho khung oi-qa-panel (partials/practice-assign-style) ── */
    .oi-qa-panel select, .oi-qa-panel textarea { display: block; box-sizing: border-box; width: 100%; min-width: 0; border: 1px solid #cfdee7; border-radius: 10px; background: #fff; padding: 10px 12px; color: #123b68; font-size: 14px; font-family: inherit; }
    .oi-qa-panel select { min-height: 44px; }
    .oi-qa-panel textarea { resize: vertical; line-height: 1.5; }
    .oi-qa-panel select:focus, .oi-qa-panel textarea:focus { outline: 3px solid #eaf5f8; border-color: #2d7fa3; }
</style>
