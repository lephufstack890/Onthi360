{{-- ═══════════ CSS TRANG NHẬT KÝ NỘP BÀI ═══════════
     SỬA 7/10 — chép từ education-main/src/components/submissionHistory.css, bỏ các phần chưa có dữ
     liệu thật (dấu hiệu hoạt động, bài mẫu, hộp thoại ma trận điểm). Máy chủ không chạy được vite
     nên dùng CSS thường, tên lớp giữ nguyên như bản mẫu và đều nằm dưới .submission-history. --}}
<style>
.submission-history { --history-heading:#123B68; --history-body:#45657D; --history-muted:#607A90; --history-action:#126F91; --history-border:#DDEAF0; --history-divider:#E7EFF3; --history-surface:#F8FBFC; --history-blue-soft:#EEF4FA; --history-hover:#EAF5F8; --history-green:#2F8A6B; --history-green-soft:#EFF9F5; --history-amber:#866523; --history-amber-soft:#FFF7E3; --history-amber-border:#F2E1B6; min-height:60vh; background:#EEF5FC; color:var(--history-body); font-size:12px; font-weight:500; line-height:1.6; }
.submission-history :is(h1,h2,h3,h4) { color:var(--history-heading); letter-spacing:-.008em; font-weight:700; }
.submission-history :is(button,a) { line-height:1.4; }
.submission-history button { cursor:pointer; }
.submission-history :is(button,a,summary):focus-visible { outline:2px solid var(--history-action); outline-offset:3px; }
.submission-history button:disabled { opacity:.45; cursor:not-allowed; }
.submission-history svg { flex-shrink:0; }
.submission-topbar { background:#fff; border-top:4px solid var(--history-action); border-bottom:1px solid var(--history-border); }
.submission-topbar-inner { width:100%; max-width:none; margin:0; padding:16px 32px; display:flex; align-items:center; gap:18px; }
.submission-heading { flex:1; min-width:0; }
.submission-heading>p { margin-bottom:4px; font-size:11px; font-weight:700; letter-spacing:.06em; color:var(--history-muted); }
.submission-heading h1 { font-size:16px; line-height:24px; }
.submission-button { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:36px; border:1px solid #D6E3EF; border-radius:8px; padding:7px 11px; font-size:12px; font-weight:700; color:var(--history-body); background:var(--history-blue-soft); white-space:nowrap; transition:background .15s,border-color .15s; }
.submission-button:hover { background:var(--history-hover); }
.submission-button.submission-back { min-height:38px; border-color:#258260; background:var(--history-green); color:#fff; }
.submission-button.submission-back:hover { background:#28795E; border-color:#28795E; }
.submission-role { display:flex; align-items:center; gap:6px; font-size:11px; color:var(--history-green); white-space:nowrap; }
.submission-main { width:100%; max-width:none; margin:0; padding:18px 32px 30px; }
.submission-context { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:16px; font-size:11px; }
.submission-context>span { display:flex; align-items:center; gap:8px; }
.submission-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
.submission-stats>div { display:flex; align-items:center; gap:12px; padding:13px 16px; border:1px solid #BFDCE5; border-radius:12px; background:var(--history-hover); }
.submission-stats>div:nth-child(2) { background:var(--history-blue-soft); border-color:#D6E3EF; }
.submission-stats>div:nth-child(3) { background:var(--history-green-soft); border-color:#CBE7D9; }
.submission-stats>div:nth-child(4) { background:var(--history-amber-soft); border-color:var(--history-amber-border); }
.submission-history .stat-icon { display:grid; place-items:center; width:34px; height:34px; flex-shrink:0; color:var(--history-action); background:#fff; border:1px solid #BFDCE5; border-radius:8px; }
.submission-stats>div:nth-child(2) .stat-icon { color:#365B7A; border-color:#D6E3EF; }
.submission-stats>div:nth-child(3) .stat-icon { color:var(--history-green); border-color:#CBE7D9; }
.submission-stats>div:nth-child(4) .stat-icon { color:var(--history-amber); border-color:var(--history-amber-border); }
.submission-stats strong { display:block; font-size:18px; font-weight:700; color:var(--history-heading); line-height:1.4; }
.submission-stats p { font-size:11px; font-weight:600; color:var(--history-muted); margin-top:2px; }
.submission-overview { position:relative; isolation:isolate; overflow:hidden; margin-bottom:18px; border:1px solid #BFDCE5; border-radius:20px; background:linear-gradient(110deg,#164E7B,#258D91); box-shadow:0 6px 18px rgb(18 59 104 / .08); }
.submission-overview-image { position:absolute; z-index:-2; inset:0; width:100%; height:100%; object-fit:cover; object-position:right 42%; pointer-events:none; }
.submission-overview::before { content:""; position:absolute; z-index:-1; inset:0; background:linear-gradient(90deg,rgba(18,65,113,.96) 0%,rgba(21,91,134,.9) 45%,rgba(30,122,143,.55) 70%,rgba(37,140,141,.18) 100%); pointer-events:none; }
.submission-overview-content { position:relative; padding:20px; }
.submission-overview-heading { max-width:65%; margin-bottom:16px; }
.submission-history .submission-overview-heading h2 { color:#fff; font-size:17px; line-height:1.5; }
.submission-overview-heading>p { margin-top:4px; color:#E1F0FA; font-size:12px; }
.submission-overview .submission-stats { width:calc(100% - 280px); max-width:880px; margin:0; gap:8px; }
.submission-overview .submission-stats>div { padding:12px; gap:9px; background:rgba(240,249,253,.96); border-color:rgba(255,255,255,.65); box-shadow:0 2px 6px rgb(13 56 89 / .05); }
.submission-overview .submission-stats>div:nth-child(2) { background:rgba(239,246,253,.96); }
.submission-overview .submission-stats>div:nth-child(3) { background:rgba(239,249,245,.96); }
.submission-overview .submission-stats>div:nth-child(4) { background:rgba(255,247,227,.97); }
@media(max-width:1100px) and (min-width:701px) { .submission-overview .submission-stats { width:calc(100% - 160px); } .submission-overview .submission-stats>div { padding:10px 8px; gap:7px; } .submission-overview .stat-icon { width:28px; height:28px; } }
@media(max-width:700px) { .submission-overview-content { padding:16px; } .submission-overview-heading { max-width:85%; margin-bottom:14px; } .submission-history .submission-overview-heading h2 { font-size:16px; } .submission-overview-image { object-position:72% center; } .submission-overview::before { background:linear-gradient(100deg,rgba(18,65,113,.94),rgba(21,91,134,.75) 60%,rgba(37,140,141,.4)); } .submission-overview .submission-stats { width:100%; grid-template-columns:repeat(2,minmax(0,1fr)); } .submission-overview .submission-stats>div { padding:10px; gap:8px; } }
.submission-filter-panel { margin-bottom:12px; padding:12px; border:1px solid var(--history-border); border-radius:16px; background:#fff; box-shadow:0 2px 10px rgb(28 91 121 / .04); }
.submission-filter-topline { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid var(--history-divider); }
.submission-filter-topline h2 { font-size:13px; }
.submission-mine-toggle { display:inline-flex; align-items:center; gap:7px; min-height:32px; font-size:12px; font-weight:600; color:var(--history-body); }
.submission-switch-track { display:block; width:30px; height:18px; padding:3px; border-radius:12px; background:#93a6b1; }
.submission-switch-track>span { display:block; width:12px; height:12px; border-radius:50%; background:white; transition:transform .15s; }
.submission-mine-toggle[aria-checked=true] { color:var(--history-green); }
.submission-mine-toggle[aria-checked=true] .submission-switch-track { background:var(--history-green); }
.submission-mine-toggle[aria-checked=true] .submission-switch-track>span { transform:translateX(12px); }
.submission-filters { display:flex; flex-wrap:wrap; gap:8px; }
.submission-search { display:flex; align-items:center; gap:8px; flex:1; min-width:220px; min-height:42px; background:#F8FAFB; border:1px solid #D5E3E9; border-radius:12px; padding:0 12px; color:var(--history-muted); }
.submission-search input { width:100%; min-width:0; padding:9px 0; font-size:13px; font-weight:500; color:#183D5E; }
.date-filter { display:flex; align-items:center; gap:7px; min-height:42px; border:1px solid #D6E3EF; border-radius:12px; background:#F8FAFB; padding:7px 10px; font-size:11px; font-weight:600; color:#365B7A; }
.date-filter input { min-width:0; max-width:132px; min-height:26px; color:var(--history-body); color-scheme:light; }
.submission-filter-bottomline { display:flex; align-items:center; flex-wrap:wrap; gap:10px; padding:12px 0 0; }
.submission-result-filters { display:flex; flex-wrap:wrap; flex:1; gap:5px; }
.submission-history .result-all { --result-color:var(--history-action); --result-soft:var(--history-blue-soft); }
.submission-history .result-ac { --result-color:var(--history-green); --result-soft:var(--history-green-soft); }
.submission-history .result-partial { --result-color:var(--history-amber); --result-soft:var(--history-amber-soft); }
.submission-history .result-wa { --result-color:#ad4355; --result-soft:#fae7eb; }
.submission-history .result-pending { --result-color:#586ab0; --result-soft:#e9edf9; }
.submission-result-filters button { display:inline-flex; align-items:center; gap:5px; min-height:36px; padding:7px 10px; border:1px solid #D6E3EF; border-radius:8px; font-size:11px; font-weight:700; color:#365B7A; background:var(--history-blue-soft); }
.submission-result-filters button:hover { background:var(--result-soft); }
.submission-result-filters button[aria-pressed=true] { background:var(--history-heading); border-color:var(--history-heading); color:white; }
.submission-result-filters button b { font-size:11px; font-weight:600; opacity:.8; }
.submission-reset { display:flex; align-items:center; gap:4px; padding:6px; font-size:11px; color:var(--history-muted); }
.submission-filter-summary { display:flex; justify-content:space-between; flex-wrap:wrap; gap:6px; font-size:11px; color:var(--history-muted); margin:10px 0; }
.submission-filter-summary strong { color:var(--history-body); }
.submission-table-scroll { overflow:auto; background:#fff; border:1px solid var(--history-border); border-radius:16px; box-shadow:0 2px 10px rgb(28 91 121 / .05); }
.submission-table-hint { display:none; }
@media(max-width:900px) { .submission-table-hint { display:block; margin:0 0 8px; font-size:11px; color:var(--history-muted); } }
.submission-table { width:100%; border-collapse:collapse; text-align:left; white-space:nowrap; font-size:12px; }
.submission-table th { padding:11px 12px; background:#DFEAF4; border-bottom:1px solid #BCD0E1; color:var(--history-heading); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
.submission-table td { padding:15px 12px; border-bottom:1px solid var(--history-divider); vertical-align:middle; }
.submission-table tbody tr:last-child td { border-bottom:0; }
.submission-table tbody tr:nth-child(even) { background:#F7FBFC; }
.submission-table tbody tr:hover, .submission-table tbody tr.selected { background:var(--history-hover); }
.submission-table tbody tr.selected td:first-child { box-shadow:inset 3px 0 var(--history-action); }
.submission-person { display:block; text-align:left; }
.submission-person strong { display:block; font-size:13px; font-weight:600; color:#2D7FA3; }
.submission-person>span,.table-secondary { display:block; font-size:11px; color:var(--history-muted); margin-top:3px; }
.submission-sort { display:inline-flex; align-items:center; gap:5px; font-weight:700; text-transform:inherit; letter-spacing:inherit; }
.submission-table .score-column { text-align:right; }
.table-score { font-size:13px; font-weight:700; color:var(--result-color); }
.table-score-max { font-size:11px; color:var(--history-muted); }
.submission-result-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 8px; border-radius:8px; font-size:11px; font-weight:700; background:var(--result-soft); color:var(--result-color); }
.submission-result-badge>span { width:5px; height:5px; border-radius:50%; background:currentColor; }
.submission-view { display:inline-flex; align-items:center; justify-content:center; gap:4px; min-height:32px; padding:6px 9px; border:1px solid #D6E3EF; border-radius:8px; background:var(--history-blue-soft); color:#365B7A; font-size:11px; font-weight:700; }
.submission-view[aria-expanded=true]>svg { transform:rotate(90deg); }
.submission-view:hover,.submission-person:hover strong { text-decoration:underline; }
.submission-open-hint,.submission-storage-note { font-size:11px; color:var(--history-muted); margin-top:14px; }
.submission-detail { margin-top:22px; background:#fff; border:1px solid var(--history-border); border-radius:14px; overflow:hidden; }
.submission-detail-caption { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:12px 20px; background:var(--history-surface); }
.submission-detail-caption h2 { font-size:13px; }
.submission-detail-caption button { display:flex; align-items:center; gap:5px; font-size:11px; color:var(--history-muted); }
.submission-detail-heading { display:flex; align-items:center; justify-content:space-between; gap:15px; padding:16px 20px; background:var(--history-hover); border-block:1px solid var(--history-border); }
.submission-eyebrow { font-size:11px; color:var(--history-muted); font-weight:700; letter-spacing:.06em; }
.submission-detail-heading h2 { font-size:16px; line-height:24px; margin:3px 0; }
.submission-detail-heading p:not(.submission-eyebrow) { font-size:12px; }
.submission-detail-heading code { display:block; font-size:10px; color:var(--history-muted); margin-top:4px; overflow-wrap:anywhere; }
.submission-large-score { text-align:right; flex-shrink:0; }
.submission-large-score strong { display:block; font-size:22px; color:var(--result-color); font-weight:700; margin-bottom:4px; }
.submission-large-score small { font-size:12px; color:var(--history-muted); font-weight:400; }
.submission-milestones { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:15px; padding:16px 20px; border-bottom:1px solid var(--history-divider); }
.submission-milestones>div { display:flex; align-items:baseline; flex-wrap:wrap; gap:6px; }
.submission-milestones span { font-size:11px; color:var(--history-muted); }
.submission-milestones strong { font-size:12px; font-weight:600; color:var(--history-body); }
.submission-detail-body { padding:16px 20px; }
.submission-answer-toolbar { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; font-size:11px; color:var(--history-muted); }
.submission-code,.question-expanded pre { padding:16px; background:#F8FBFC; border:1px solid var(--history-border); border-radius:8px; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12px; font-weight:400; line-height:1.8; color:#243B4A; overflow:auto; max-height:420px; tab-size:2; white-space:pre-wrap; overflow-wrap:anywhere; }
.submission-questions details { border-bottom:1px solid var(--history-border); }
.submission-questions summary { display:flex; align-items:center; gap:10px; padding:12px 6px; cursor:pointer; list-style:none; }
.submission-questions summary::-webkit-details-marker { display:none; }
.submission-questions summary>div { flex:1; min-width:0; }
.submission-questions summary strong { font-size:12px; font-weight:600; color:var(--history-heading); }
.submission-questions summary p { font-size:11px; color:var(--history-muted); margin-top:3px; }
.submission-questions summary>b { flex-shrink:0; font-size:12px; color:var(--result-color,var(--history-body)); }
.submission-questions summary small { font-size:11px; font-weight:400; color:var(--history-muted); }
.submission-questions details[open] summary { background:var(--history-hover); }
.submission-questions details[open] summary>svg { transform:rotate(90deg); }
.question-number { display:grid; place-items:center; width:26px; height:26px; flex-shrink:0; border-radius:5px; background:var(--history-blue-soft); color:var(--history-action); font-size:11px; }
.question-expanded { padding:12px; }
.question-expanded>p { font-size:11px; margin-bottom:10px; }
.submission-muted { font-size:12px; color:var(--history-muted); }
.submission-empty { padding:30px 15px; text-align:center; background:white; border:1px solid var(--history-border); border-radius:16px; }
.submission-empty>svg { margin:0 auto 10px; width:32px; height:32px; color:#89a8b8; }
.submission-empty :is(h1,h2,h3) { font-size:13px; margin-bottom:5px; }
.submission-empty p { font-size:12px; color:var(--history-muted); }
.submission-empty :is(button,a) { display:inline-block; margin-top:10px; font-size:12px; color:var(--history-action); }
.submission-pagination { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-top:10px; padding:10px 12px; border:1px solid var(--history-border); border-radius:12px; background:#fff; font-size:11px; }
.submission-page-info,.submission-page-controls,.submission-page-info label { display:flex; align-items:center; gap:8px; }
.submission-page-info { flex-wrap:wrap; gap:16px; }
.submission-page-info strong { color:var(--history-heading); }
.submission-page-info select { min-height:34px; padding:6px 9px; border:1px solid #D6E3EF; border-radius:9px; background:var(--history-surface); color:var(--history-heading); font-weight:600; }
.submission-page-controls { gap:5px; }
.submission-page-count { margin-right:6px; color:var(--history-muted); }
.submission-pagination button { display:grid; place-items:center; min-width:32px; height:32px; padding:5px 7px; border:1px solid #D6E3EF; border-radius:8px; background:var(--history-blue-soft); color:var(--history-heading); font-weight:700; }
.submission-pagination button:hover:not(:disabled) { background:var(--history-hover); border-color:#9DC8D7; }
.submission-pagination button[aria-current=page] { background:var(--history-action); border-color:var(--history-action); color:#fff; }
.submission-page-gap { color:var(--history-muted); }
.submission-history :is(.submission-search,.date-filter):focus-within { outline:none; border-color:#2D7FA3; box-shadow:0 0 0 3px #DDF1F6; background-color:#fff; }
.submission-history :is(.submission-search,.date-filter) input { appearance:none; border:0; outline:none; box-shadow:none; background:transparent; }
.submission-search input::placeholder { color:#8193A3; }
@media(max-width:900px) { .submission-topbar-inner,.submission-main { padding-inline:16px; } .submission-table { min-width:830px; } .submission-context { flex-wrap:wrap; } }
@media(max-width:600px) { .submission-topbar-inner { align-items:flex-start; padding:12px; gap:10px; } .submission-role,.submission-heading>p span { display:none; } .submission-heading h1 { font-size:16px; line-height:22px; } .submission-main { padding:14px 12px 24px; } .submission-stats { grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; } .submission-stats>div { padding:10px; gap:8px; } .submission-search { flex-basis:100%; } .date-filter { flex:1; min-width:0; } .date-filter input { min-width:0; width:100%; } .submission-result-filters button { padding:6px 8px; } .submission-detail-heading { padding:14px 12px; align-items:flex-start; } .submission-large-score strong { font-size:20px; } .submission-milestones { padding:12px; gap:10px; grid-template-columns:minmax(0,1fr); } .submission-detail-body { padding:12px; } .submission-page-info,.submission-page-controls { width:100%; justify-content:space-between; } .submission-page-controls { flex-wrap:wrap; justify-content:center; } .submission-page-count { margin-right:auto; } }
</style>
