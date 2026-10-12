{{-- SỬA 12/10 — CSS khu GIÁO VIÊN dựng lại theo source mới (education-main: teacherWorkspace.css +
     TeacherDashboard / TeacherWorkPanel / TeacherNoticePanel). Chép nguyên các lớp .tw-* của source, thêm vài lớp phụ
     cho phần chỉ có ở Laravel. Máy chủ không có Vite nên viết tay, không dùng lớp Tailwind chưa build.
     Dùng chung phần hero + phân trang + swPager() với khu Học sinh (partials/student-workspace-style). --}}
@include('partials.student-workspace-style')
@once
<style>
    .tw-dashboard { color: #304e67; display: flex; flex-direction: column; gap: 12px; min-width: 0; font-size: 12px; line-height: 1.5; }
    .tw-dashboard * { box-sizing: border-box; }
    .tw-dashboard svg { flex-shrink: 0; }
    .tw-dashboard :is(h1, h2, h3, h4, p, dl, dd, ul) { margin: 0; }
    .tw-dashboard :is(h2, h3, h4) { font-size: 12px; line-height: 1.4; font-weight: 700; letter-spacing: -.008em; color: #1e293b; }
    .tw-dashboard :is(button, summary) { font-family: inherit; font-size: 11px; line-height: 1.4; }
    .tw-dashboard :is(input, select, textarea) { font-size: 12px; line-height: 1.5; }
    .tw-dashboard a { text-decoration: none; }
    .tw-dashboard .sw-hero-in h1, .tw-dashboard .sw-hero-in p { margin: 8px 0 0; }
    .tw-dashboard .sw-hero-in h1 { font-size: 1.25rem; line-height: 1.25; font-weight: 700; color: #fff; }
    .tw-dashboard .sw-hero-in p { color: #f0f9ff; }
    .tw-dashboard a.sw-hero-btn { color: #1d4ed8; }
    .sw-hero.is-compact .sw-hero-in h1 { font-size: 1.25rem; }
    @media (min-width: 640px) { .sw-hero.is-compact .sw-hero-in h1 { font-size: 1.5rem; } }
    .sw-hero.is-compact .sw-hero-in p { font-size: 12px; }

    .tw-demo, .tw-muted { color: #6a8094; font-size: 11px; line-height: 1.6; }
    .tw-card { min-width: 0; padding: 14px; border: 1px solid #dfeaf3; border-radius: 16px; background: #fff; box-shadow: 0 3px 12px #225b8908; }
    .tw-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px 12px; margin-bottom: 12px; }
    .tw-heading > div { min-width: 0; flex: 1; }
    .tw-heading h2 { display: flex; align-items: center; gap: 7px; }
    .tw-heading h2 svg { color: #3981ad; width: 16px; height: 16px; }
    .tw-heading p, .tw-card p { margin-top: 5px; color: #6a8094; font-size: 11px; line-height: 1.6; }
    .tw-button, .tw-secondary { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 34px; padding: 6px 10px; border: 1px solid #16634b; border-radius: 8px; background: #176b50; color: #fff; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; transition: background-color .15s; }
    .tw-button:hover { background: #11543e; color: #fff; }
    .tw-button svg { width: 13px; height: 13px; }
    .tw-secondary { background: #f3f8fa; color: #37667c; border-color: #d6e6ee; }
    .tw-secondary:hover { background: #e7f2f5; }
    .tw-secondary[aria-pressed="true"] { border-color: #448b73; background: #eaf6f0; color: #176b50; }
    .tw-dashboard button:disabled { opacity: .5; cursor: not-allowed; }
    .tw-dashboard :is(button, input, select, textarea, summary, a):focus-visible { outline: 2px solid #34b78b; outline-offset: 2px; }
    .tw-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px; }
    .tw-heading .tw-actions { margin: 0 0 7px; }
    .tw-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 12px; }
    .tw-class-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .tw-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .tw-stat { min-width: 0; display: flex; flex-direction: column; align-items: flex-start; gap: 7px; padding: 12px; border: 1px solid #dfeaf3; border-radius: 14px; background: #fff; color: #304e67; text-align: left; cursor: pointer; text-decoration: none; }
    .tw-stat:hover { border-color: #7eaf9e; background: #f5faf7; }
    .tw-stat span { display: flex; align-items: center; gap: 6px; color: #3f6f8b; font-weight: 600; }
    .tw-stat span svg { width: 15px; height: 15px; }
    .tw-stat strong { font-size: 16px; line-height: 1.4; }
    .tw-stat small { color: #6a8094; font-weight: 400; font-size: 11px; }
    .tw-highlight { padding: 10px; background: #f0f7fb; border-radius: 10px; }
    .tw-row { padding: 12px 0; border-bottom: 1px solid #edf2f7; }
    .tw-row:last-child { border-bottom: 0; }
    .tw-row.tw-highlight { padding: 10px; margin-bottom: 4px; border-bottom: 0; }
    .tw-row time { display: block; color: #287ba0; font-size: 11px; margin-bottom: 5px; }
    .tw-task { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 0; border-bottom: 1px solid #edf2f7; }
    .tw-task:last-of-type { border-bottom: 0; }
    .tw-task > div { min-width: 0; }
    .tw-task > button, .tw-task > a { flex-shrink: 0; }
    .tw-class-header { display: flex; gap: 10px; align-items: center; }
    .tw-class-header > div { min-width: 0; }
    .tw-class-header img, .tw-class-cover { width: 64px; height: 68px; object-fit: cover; border-radius: 10px; flex-shrink: 0; }
    .tw-class-header small { color: #2f8069; font-size: 11px; }
    .tw-tag { display: inline-flex; align-items: center; gap: 4px; padding: 3px 7px; border-radius: 6px; background: #edf4f9; color: #4f6f87; font-size: 10px; }
    .tw-status-overdue { background: #fff1e9; color: #a5552e; }
    .tw-status-unsubmitted { background: #edf4f9; color: #4f6f87; }
    .tw-status-pending { background: #fff8df; color: #8c722c; }
    .tw-status-completed { background: #eaf7f0; color: #2e8063; }
    .tw-dashboard :is(input:not([type="checkbox"]):not([type="radio"]), select, textarea) { width: 100%; min-width: 0; min-height: 36px; padding: 7px 10px; border: 1px solid #d6e3eb; border-radius: 9px; background: #f8fbfc; color: #365b7a; font-family: inherit; }
    .tw-dashboard textarea { resize: vertical; }
    .tw-dashboard label { display: flex; min-width: 0; flex-direction: column; gap: 6px; font-size: 11px; font-weight: 600; }
    .tw-form { display: flex; flex-direction: column; gap: 12px; }
    .tw-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .tw-filters { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.4fr); gap: 10px; margin: 12px 0; }
    .tw-inline-filter { max-width: 420px; margin: 12px 0; }
    .tw-heading > input { max-width: 300px; }
    .tw-work-count { padding-bottom: 8px; }
    .tw-work-list { container-type: inline-size; }
    .tw-work-row { padding: 12px 0; border-top: 1px solid #e4edf4; }
    .tw-work-summary { display: grid; grid-template-columns: minmax(0, 1fr) 130px 228px; align-items: center; gap: 12px; }
    .tw-work-info { min-width: 0; }
    .tw-work-title { display: flex; gap: 7px; align-items: flex-start; }
    .tw-work-title h3 { margin: 2px 0 0; overflow-wrap: anywhere; }
    .tw-work-kind { display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0; padding: 3px 6px; border-radius: 6px; background: #edf4f9; color: #376f94; font-size: 10px; line-height: 1.5; }
    .tw-work-kind svg { width: 12px; height: 12px; }
    .tw-work-kind-exam { background: #f1effb; color: #70629e; }
    .tw-work-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 3px 12px; margin-top: 6px; color: #6a8094; font-size: 11px; }
    .tw-work-recipient { color: #426079; font-weight: 600; }
    .tw-work-result { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; }
    .tw-work-result .tw-tag { font-size: 11px; white-space: nowrap; }
    .tw-work-result small { font-size: 10px; color: #a5552e; }
    .tw-work-actions { display: flex; align-items: center; gap: 6px; }
    .tw-work-actions .tw-button { min-height: 32px; padding: 6px 8px; white-space: nowrap; }
    .tw-work-actions button[aria-expanded="true"] { box-shadow: 0 0 0 2px #83b9a5; }
    @container (max-width: 850px) {
        .tw-work-summary { grid-template-columns: minmax(0, 1fr) auto; gap: 8px 10px; }
        .tw-work-result { align-self: start; align-items: flex-end; }
        .tw-work-actions { grid-column: 1/-1; }
    }
    @container (max-width: 480px) {
        .tw-work-summary { grid-template-columns: minmax(0, 1fr); gap: 7px; }
        .tw-work-result { flex-direction: row; align-items: center; gap: 8px; }
        .tw-work-actions { justify-content: flex-start; flex-wrap: wrap; }
        .tw-work-row { padding: 10px 0; }
    }
    .tw-assignment-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 16px; }
    .tw-assignment-details dt { color: #6a8094; font-size: 11px; margin-bottom: 3px; }
    .tw-assignment-details dd { margin: 0; overflow-wrap: anywhere; }
    @media (max-width: 479px) { .tw-assignment-details { grid-template-columns: minmax(0, 1fr); } }
    .tw-submission { padding: 12px; margin-top: 10px; background: #f5f9fc; border: 1px solid #dce7ee; border-radius: 10px; }
    .tw-submission-score { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 12px; margin: 10px 0; padding: 9px 12px; background: #fff; border: 1px solid #dce7ee; border-radius: 8px; }
    .tw-submission-score > span:first-child { font-size: 11px; color: #6a8094; }
    .tw-submission-score > strong { font-size: 16px; line-height: 1.4; font-variant-numeric: tabular-nums; }
    .tw-result-ac { background: #eaf7f0; color: #2e8063; }
    .tw-result-partial { background: #fff8df; color: #8c722c; }
    .tw-result-wa { background: #fff1e9; color: #a5552e; }
    .tw-submission pre { max-height: 300px; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; background: #163449; color: #e1eff5; padding: 12px; border-radius: 8px; margin: 10px 0; font-size: 12px; }
    .tw-resource { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid #e4edf4; border-radius: 12px; }
    .tw-resource > img { width: 56px; height: 78px; object-fit: contain; flex-shrink: 0; }
    .tw-resource > div { min-width: 0; }
    .tw-notice-body { white-space: pre-wrap; overflow-wrap: anywhere; }
    .tw-notice-history { margin-top: 16px; padding-top: 14px; border-top: 1px solid #e4edf4; }
    .tw-notice-history > .tw-heading { margin-bottom: 4px; }
    .tw-notice-history > .tw-heading > select { width: auto; max-width: 180px; }
    .tw-notice-history .tw-row > .tw-actions { margin-top: 8px; }
    .tw-notice-editing { border-left: 3px solid #38896c; padding-left: 10px; }
    .tw-empty { padding: 24px 0; text-align: center; color: #6a8094; }
    .tw-error, .tw-success { padding: 10px 12px; border: 1px solid #f1d5d5; border-radius: 10px; background: #fff3f1; color: #aa4545; font-size: 12px; }
    .tw-success { color: #2b795e; background: #f0faf4; border-color: #cfe9da; }
    .tw-tabs { display: flex; gap: 4px; overflow-x: auto; }
    .tw-tab { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; min-height: 36px; padding: 6px 12px; border: 1px solid #d6e6ee; border-radius: 10px; background: #f3f8fa; color: #37667c; font-size: 12px; font-weight: 600; text-decoration: none; cursor: pointer; }
    .tw-tab:hover { background: #e7f2f5; }
    .tw-tab.on, .tw-tab[aria-current="page"] { border-color: #176b50; background: #176b50; color: #fff; }
    .tw-tab svg { width: 14px; height: 14px; }
    .tw-tab .n { padding: 0 6px; border-radius: 999px; background: rgba(255,255,255,.25); font-size: 10px; }
    .tw-tab:not(.on):not([aria-current="page"]) .n { background: #e0ecf3; color: #37667c; }
    .tw-dashboard .sw-pager button.on { border-color: #176b50; background: #176b50; color: #fff; }
    .tw-dashboard .sw-pager button { border-color: #d6e3eb; }
    .tw-roster-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 4px; }
    .tw-chev { width: 12px; height: 12px; transition: transform .15s; } .tw-chev.up { transform: rotate(180deg); }
    .tw-assign-pick { max-height: 180px; overflow: auto; border: 1px solid #d6e3eb; border-radius: 9px; background: #fff; }
    .tw-assign-pick button { display: block; width: 100%; padding: 7px 10px; border: 0; border-bottom: 1px solid #edf2f7; background: transparent; color: #365b7a; font-size: 12px; text-align: left; cursor: pointer; }
    .tw-assign-pick button:hover, .tw-assign-pick button.on { background: #eaf6f0; color: #176b50; }
    .tw-count-pill { display: inline-flex; align-items: center; padding: 1px 7px; border-radius: 999px; background: #eaf6f0; color: #176b50; font-size: 10px; font-weight: 700; }
    .tw-banner { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 12px; border: 1px solid #f6e7b8; border-radius: 12px; background: #fff9e8; color: #8c722c; }
    .tw-banner p { margin: 0; color: inherit; font-size: 12px; flex: 1; min-width: 200px; }
    .tw-iframe-gap { display: grid; gap: 12px; }
    @media (max-width: 767px) { .tw-grid, .tw-class-grid { grid-template-columns: minmax(0, 1fr); } .tw-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } .tw-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); } .tw-filters label:last-child { grid-column: 1/-1; } }
    @media (max-width: 479px) { .tw-fields { grid-template-columns: minmax(0, 1fr); } .tw-card { padding: 12px; } .tw-task { flex-wrap: wrap; } }
</style>
@endonce
