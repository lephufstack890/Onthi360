{{-- SỬA 12/10 — CSS khu HỌC SINH dựng lại theo source mới (education-main: studentWorkspace.css +
     RoleWorkspace.jsx / StudentOverview / StudentAssignmentInbox / CompletenessPanels).
     Viết tay (máy chủ không có Vite nên không dùng lớp Tailwind chưa build). Các lớp .student-*
     giữ nguyên tên của source; lớp .sw-* là phần Tailwind của source được viết lại thành CSS thường. --}}
@once
<style>
    .sw { display: grid; gap: 12px; min-width: 0; color: #304e67; font-size: 12px; line-height: 1.5; }
    .sw * { box-sizing: border-box; }
    .sw svg { flex-shrink: 0; }
    .sw :is(h1, h2, h3, p, dl, dd, ul) { margin: 0; }
    .sw :is(h2, h3) { font-size: 12px; line-height: 1.4; font-weight: 700; letter-spacing: -.008em; color: #1e293b; }
    .sw button, .sw summary { font-family: inherit; font-size: 11px; line-height: 1.4; }
    .sw :is(button, summary, a, select, input):focus-visible { outline: 2px solid #34b78b; outline-offset: 2px; }

    /* ── Hero (Hero() của RoleWorkspace.jsx) ── */
    .sw-hero { position: relative; min-width: 0; overflow: hidden; border-radius: 24px; min-height: 188px; border: 1px solid #e0f2fe; background: #0d5faf; box-shadow: 0 7px 20px rgba(0,95,180,.09); }
    .sw-hero > img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .sw-hero::before { content: ""; position: absolute; inset: 0; z-index: 1; background: linear-gradient(90deg, rgba(7,89,168,.95), rgba(9,118,201,.72) 55%, rgba(9,118,201,.1)); }
    .sw-hero-in { position: relative; z-index: 2; display: flex; flex-direction: column; gap: 16px; padding: 20px; color: #fff; }
    .sw-hero-in small { display: block; font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #e0f2fe; }
    .sw-hero-in h1 { margin: 8px 0 0; font-size: 1.5rem; line-height: 1.25; font-weight: 700; letter-spacing: -.01em; color: #fff; overflow-wrap: anywhere; }
    .sw-hero-in p { margin: 8px 0 0; max-width: 36rem; font-size: 12px; line-height: 1.6; color: #f0f9ff; }
    .sw-hero-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 8px 14px; border-radius: 12px; background: #fff; color: #1d4ed8; font-size: 12px; font-weight: 700; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,.12); transition: background-color .15s; }
    .sw-hero-btn:hover { background: #f0f9ff; }
    @media (min-width: 640px) { .sw-hero-in { flex-direction: row; align-items: flex-end; justify-content: space-between; padding: 24px 28px; } .sw-hero-in h1 { font-size: 1.875rem; } .sw-hero-btn { min-height: 44px; } }

    /* ── Thẻ + tiêu đề (student-overview-* của source) ── */
    .student-overview { color: #304e67; }
    .student-overview-card, .sw-card { min-width: 0; padding: 14px; border: 1px solid #dfeaf3; border-radius: 20px; background: #fff; box-shadow: 0 3px 12px #225b8908; }
    .student-overview-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 12px; }
    .student-overview-grid.is-even { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .student-overview-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 4px 10px; }
    .student-overview-title { display: flex; align-items: center; gap: 7px; }
    .student-overview-title > svg { color: #3981ad; }
    .student-overview-muted { color: #6a8094; font-size: 11px; line-height: 1.6; }
    .student-overview-card > .student-overview-muted { margin-top: 5px; }
    .student-overview-action { display: inline-flex; align-items: center; justify-content: center; gap: 5px; flex-shrink: 0; min-height: 32px; padding: 5px 10px; border-radius: 8px; border: 1px solid #16634b; background: #176b50; color: #fff; font-weight: 600; text-decoration: none; cursor: pointer; transition: background-color 150ms ease; }
    .student-overview-action:hover { background: #11543e; }
    .student-overview-action:disabled { opacity: .55; cursor: not-allowed; }
    .student-overview-card > .student-overview-action { margin-top: 10px; }
    .student-work-counts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-top: 12px; }
    .student-work-count { padding: 10px; border-radius: 10px; background: #f1f6fe; color: #3a6ca7; }
    .student-work-violet { background: #f6f3fc; color: #7761a5; }
    .student-work-amber { background: #fffaed; color: #9a7624; }
    .student-work-teal { background: #eff9f5; color: #30856c; }
    .student-work-count dt { display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; }
    .student-work-count dd { margin-top: 4px; font-size: 16px; font-weight: 700; }
    .student-work-count p { margin-top: 3px; font-size: 10px; line-height: 1.5; }
    .student-overview-list { margin-top: 8px; }
    .student-overview-list > article { padding: 10px 0; border-top: 1px solid #edf2f7; }
    .student-overview-list > article:first-child { border-top: 0; }
    .student-overview-list h3 { margin: 4px 0; }
    .student-task-heading { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .student-task-kind { color: #326db4; background: #eef5fe; border-radius: 5px; padding: 2px 6px; font-size: 10px; font-weight: 600; }
    .student-task-kind.is-exam { color: #8061af; background: #f4f0fb; }
    .student-overdue { color: #b05235; font-size: 11px; }
    .student-session-date { font-size: 11px; font-weight: 600; color: #31749f; }
    .student-overview-list > .student-next-session { padding: 10px; margin-bottom: 4px; border-radius: 10px; background: #f2f8fd; }
    .student-overview-empty { padding: 20px 0; font-size: 12px; color: #6a8094; }
    .student-class-enter { display: flex; align-items: center; justify-content: center; gap: 7px; min-height: 34px; border: 1px solid #16634b; border-radius: 9px; color: #fff; background: #176b50; font-weight: 600; text-decoration: none; cursor: pointer; transition: background-color 150ms ease; }
    .student-class-enter:hover { background: #11543e; }
    .student-next-session .student-class-enter { display: inline-flex; margin-top: 9px; padding-inline: 14px; }
    .student-connection-person { display: flex; align-items: center; gap: 8px; padding: 12px 0; flex-wrap: wrap; }
    .student-connection-person > div { flex: 1; min-width: 120px; }
    .student-connection-person p { margin-top: 3px; font-size: 11px; color: #6a8094; }
    .student-connection-person img, .student-connection-avatar { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
    .student-connection-avatar { color: #6981b6; background: #f0f3fb; font-size: 12px; font-weight: 700; }
    .student-connected { display: inline-flex; align-items: center; gap: 3px; color: #328067; font-size: 10px; }
    .student-teacher-list .student-connection-person { border-bottom: 1px solid #edf2f7; padding: 8px 0; }
    .student-teacher-list .student-connection-person:last-child { border: 0; padding-bottom: 0; }
    @media (max-width: 767px) { .student-overview-grid, .student-overview-grid.is-even { grid-template-columns: minmax(0, 1fr); } .student-work-counts { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

    /* ── Lớp học của tôi (StudentClassesPanel) ── */
    .student-classes-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 2px 10px; color: #274a65; }
    .student-classes-heading h2 { display: flex; align-items: center; gap: 7px; }
    .student-classes-heading h2 span { padding: 1px 6px; border-radius: 6px; background: #e4effb; color: #2c6bad; font-size: 10px; }
    .student-classes-heading p { color: #71869a; font-size: 11px; }
    .student-classes-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .student-class-card { --class-accent: #356cbb; --class-tint: #f0f6fe; min-width: 0; display: flex; flex-direction: column; gap: 12px; padding: 14px; border: 1px solid #dfeaf3; border-radius: 16px; background: #fff; box-shadow: 0 3px 12px #225b8910; }
    .student-class-violet { --class-accent: #7862ad; --class-tint: #f6f3fc; }
    .student-class-teal { --class-accent: #278680; --class-tint: #eef9f7; }
    .student-class-header { display: flex; align-items: center; gap: 10px; }
    .student-class-cover { width: 64px; height: 68px; flex-shrink: 0; border-radius: 12px; object-fit: cover; }
    .student-class-title { min-width: 0; }
    .student-class-code { color: var(--class-accent); font-size: 10px; font-weight: 600; }
    .student-class-title h3 { margin: 3px 0 5px; color: #244664; }
    .student-class-format { display: flex; align-items: center; gap: 5px; color: #6b8296; font-size: 11px; }
    .student-class-teacher { display: flex; align-items: center; gap: 8px; }
    .student-class-teacher img, .student-class-teacher .ini { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid #e1eaf2; }
    .student-class-teacher .ini { display: grid; place-items: center; background: #f0f3fb; color: #6981b6; font-size: 11px; font-weight: 700; }
    .student-class-teacher span { color: #71869a; font-size: 10px; }
    .student-class-teacher p { color: #466078; font-size: 11px; font-weight: 600; }
    .student-class-schedule { display: flex; align-items: center; gap: 8px; padding: 9px 10px; border-radius: 10px; background: var(--class-tint); color: var(--class-accent); }
    .student-class-schedule strong { display: block; font-size: 11px; font-weight: 600; }
    .student-class-weekly { margin-left: auto; font-size: 10px; white-space: nowrap; }
    .student-class-progress { margin-top: auto; }
    .student-class-progress > div { display: flex; align-items: center; justify-content: space-between; gap: 8px; color: #6b8194; font-size: 11px; }
    .student-class-progress strong { color: var(--class-accent); font-weight: 600; }
    @media (max-width: 639px) { .student-classes-grid { grid-template-columns: minmax(0, 1fr); } }
    @media (min-width: 1280px) { .student-classes-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }

    /* ── Khối chung của các mục (thay các lớp Tailwind của source) ── */
    .sw-h2 { display: flex; align-items: center; gap: 8px; color: #1e293b; }
    .sw-h2 > svg { width: 16px; height: 16px; color: #2d7fa3; }
    .sw-sub { margin-top: 4px; font-size: 12px; color: #64748b; }
    .sw-note { font-size: 11px; color: #64748b; }
    .sw-split { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 8px; }
    .sw-stat3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 12px; }
    .sw-stat3 > div { border-radius: 12px; padding: 10px; }
    .sw-stat3 dt { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; }
    .sw-stat3 dt svg { width: 14px; height: 14px; }
    .sw-stat3 dd { margin-top: 4px; font-size: 16px; font-weight: 700; }
    .sw-stat3 p { margin-top: 4px; font-size: 11px; }
    .sw-t-blue { background: rgba(239,246,255,.7); color: #1d4ed8; } .sw-t-violet { background: rgba(245,243,255,.7); color: #6d28d9; } .sw-t-emerald { background: rgba(236,253,245,.7); color: #047857; }
    .sw-filters { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
    .sw-seg { display: flex; flex-wrap: wrap; gap: 4px; }
    .sw-act { min-height: 36px; padding: 0 10px; border: 1px solid transparent; border-radius: 8px; background: transparent; color: #2d7fa3; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .sw-act:hover { background: #f0f9ff; }
    .sw-act.on { border-color: #bae6fd; background: #f0f9ff; }
    .sw-select-l { display: flex; align-items: center; gap: 8px; font-size: 11px; color: #64748b; }
    .sw-select-l select { min-height: 36px; border: 1px solid #e0f2fe; border-radius: 8px; background: #f8fbfd; padding: 0 8px; font-size: 12px; color: #334155; }
    .sw-list { display: grid; gap: 8px; margin-top: 8px; }
    .sw-item { border: 1px solid #e0f2fe; border-radius: 12px; background: #fbfdfe; padding: 12px; }
    .sw-item-top { display: flex; align-items: flex-start; gap: 10px; }
    .sw-ic { display: grid; place-items: center; width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px; background: #eff6ff; color: #2563eb; }
    .sw-ic svg { width: 16px; height: 16px; }
    .sw-ic.is-exam { background: #f5f3ff; color: #7c3aed; }
    .sw-item-main { min-width: 0; flex: 1; }
    .sw-item-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
    .sw-item-head > div { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; min-width: 0; }
    .sw-kind { font-size: 11px; font-weight: 600; color: #1d4ed8; } .sw-kind.is-exam { color: #6d28d9; }
    .sw-state { border-radius: 6px; padding: 3px 8px; font-size: 11px; font-weight: 500; white-space: nowrap; }
    .sw-state.new { background: #f1f5f9; color: #475569; } .sw-state.active { background: #eff6ff; color: #1d4ed8; } .sw-state.submitted { background: #fffbeb; color: #b45309; } .sw-state.completed { background: #ecfdf5; color: #047857; }
    .sw-item p.m { margin-top: 4px; font-size: 11px; color: #64748b; } .sw-item p.m2 { margin-top: 4px; font-size: 11px; color: #475569; }
    .sw-dates { display: grid; gap: 4px 16px; margin-top: 12px; font-size: 11px; color: #64748b; }
    .sw-dates b { font-weight: 500; color: #334155; }
    @media (min-width: 640px) { .sw-dates { grid-template-columns: 1fr 1fr; } }
    .sw-res { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; margin-top: 8px; border-radius: 8px; background: #ecfdf5; padding: 8px 10px; font-size: 11px; color: #047857; }
    .sw-res b { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; } .sw-res svg { width: 14px; height: 14px; }
    .sw-wait { margin-top: 8px; border-radius: 8px; background: #fffbeb; padding: 8px 10px; font-size: 11px; color: #b45309; }
    .sw-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 4px; margin-top: 4px; }
    .sw-detail { margin-top: 4px; border-top: 1px solid #e0f2fe; padding-top: 8px; font-size: 12px; line-height: 1.6; color: #475569; }
    .sw-detail p + p { margin-top: 8px; }
    .sw-detail .s { font-size: 11px; color: #64748b; }
    .sw-chev { width: 12px; height: 12px; transition: transform .15s; } .sw-chev.up { transform: rotate(180deg); }
    .student-assignment-hide { min-height: 34px; padding: 6px 10px; border: 1px solid #16634b; border-radius: 8px; background: #176b50; color: #fff; font-size: 11px; font-weight: 600; cursor: pointer; }
    .student-assignment-hide:hover { background: #11543e; }
    .sw-empty { padding: 24px 0; text-align: center; font-size: 12px; color: #64748b; }
    .sw-hidden { margin-top: 12px; border: 1px dashed #bae6fd; border-radius: 12px; padding: 12px; }
    .sw-hidden summary { min-height: 32px; cursor: pointer; font-weight: 600; color: #475569; }
    .sw-hidden .sw-seg { margin-top: 6px; }

    /* ── Kết quả & lịch sử ── */
    .sw-rows { display: grid; gap: 8px; }
    .sw-row { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 12px; border-radius: 12px; background: #f8fafc; }
    .sw-dot { width: 8px; height: 8px; flex-shrink: 0; border-radius: 999px; }
    .sw-dot.emerald { background: #10b981; } .sw-dot.amber { background: #f59e0b; } .sw-dot.rose { background: #f43f5e; } .sw-dot.blue { background: #3b82f6; }
    .sw-row-main { flex: 1; min-width: 176px; }
    .sw-row-main b { display: block; font-size: 12px; font-weight: 700; color: #1e293b; overflow-wrap: anywhere; }
    .sw-row-main span { display: block; margin-top: 2px; font-size: 10px; color: #64748b; }
    .sw-row-st { font-size: 10px; font-weight: 700; color: #64748b; }
    .sw-row-sc { min-width: 56px; text-align: right; font-size: 12px; font-weight: 900; color: #1d4ed8; }
    .sw-row a.sw-detail-link { font-size: 11px; font-weight: 700; color: #2563eb; text-decoration: none; white-space: nowrap; }

    /* ── Phân trang ── */
    .sw-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-top: 12px; }
    .sw-pager p { font-size: 11px; color: #64748b; }
    .sw-pager nav { display: flex; gap: 4px; }
    .sw-pager button { min-width: 32px; min-height: 32px; padding: 0 8px; border: 1px solid #e0f2fe; border-radius: 8px; background: #fff; color: #475569; font-weight: 700; cursor: pointer; display: inline-grid; place-items: center; }
    .sw-pager button svg { width: 14px; height: 14px; }
    .sw-pager button.on { border-color: #2563eb; background: #2563eb; color: #fff; }
    .sw-pager button:disabled { opacity: .45; cursor: default; }

    /* ── Thông báo / Đánh giá / Tài liệu ── */
    .sw-tabs { display: flex; gap: 4px; overflow-x: auto; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
    .sw-tab { white-space: nowrap; border: 0; border-radius: 12px; background: transparent; padding: 8px 12px; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; }
    .sw-tab:hover { background: #f0f9ff; } .sw-tab.on { background: #2563eb; color: #fff; }
    .sw-notice { display: flex; gap: 12px; padding: 16px 0; border-top: 1px solid #f1f5f9; text-decoration: none; color: inherit; }
    .sw-notice:first-of-type { border-top: 0; }
    .sw-notice.unread { background: linear-gradient(90deg, rgba(239,246,255,.6), transparent); }
    .sw-tile { display: grid; place-items: center; width: 36px; height: 36px; flex-shrink: 0; border-radius: 12px; background: #eff6ff; color: #2563eb; }
    .sw-tile.emerald { background: #ecfdf5; color: #059669; } .sw-tile.amber { background: #fffbeb; color: #d97706; } .sw-tile.rose { background: #fff1f2; color: #e11d48; }
    .sw-tile svg { width: 16px; height: 16px; }
    .sw-notice-b { min-width: 0; flex: 1; }
    .sw-notice-b > div { display: flex; justify-content: space-between; gap: 16px; }
    .sw-notice-b h2 { font-size: 12px; font-weight: 800; }
    .sw-notice-b time { font-size: 10px; color: #94a3b8; white-space: nowrap; }
    .sw-notice-b p { margin-top: 4px; font-size: 11px; color: #64748b; }
    .sw-new { width: 8px; height: 8px; flex-shrink: 0; margin-top: 6px; border-radius: 999px; background: #3b82f6; }
    .sw-rv { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; border-radius: 16px; background: #f8fafc; padding: 16px; }
    .sw-rv .star { display: grid; place-items: center; width: 40px; height: 40px; flex-shrink: 0; border-radius: 12px; background: #fffbeb; color: #f59e0b; }
    .sw-rv .star svg { width: 16px; height: 16px; fill: currentColor; }
    .sw-rv-b { min-width: 176px; flex: 1; }
    .sw-rv-b b { display: block; font-size: 12px; font-weight: 800; color: #1e293b; overflow-wrap: anywhere; }
    .sw-rv-b span { display: block; margin-top: 4px; font-size: 10px; color: #64748b; }
    .sw-rv-st { font-size: 10px; font-weight: 700; color: #2563eb; }
    .sw-btn-o { display: inline-flex; align-items: center; justify-content: center; min-height: 34px; border: 1px solid #bfdbfe; border-radius: 12px; background: #fff; padding: 0 12px; font-size: 10px; font-weight: 700; color: #2563eb; text-decoration: none; cursor: pointer; }
    .sw-btn-o:hover { background: #eff6ff; }
    .sw-box { margin-top: 16px; border-radius: 16px; padding: 16px; }
    .sw-box b { display: block; font-size: 12px; font-weight: 800; } .sw-box p { margin-top: 4px; font-size: 11px; color: #475569; }
    .sw-box.ok { border: 1px solid #d1fae5; background: #ecfdf5; } .sw-box.ok b { color: #065f46; }
    .sw-box.warn { border: 1px solid #fef3c7; background: #fffbeb; } .sw-box.warn b { color: #92400e; }
    .sw-btn-g { display: inline-flex; align-items: center; justify-content: center; margin-top: 12px; min-height: 36px; border-radius: 12px; background: #059669; padding: 0 12px; font-size: 12px; font-weight: 700; color: #fff; text-decoration: none; }
    .sw-mats { display: grid; gap: 12px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 768px) { .sw-mats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .sw-mat { display: flex; min-width: 0; gap: 12px; border: 1px solid #e0f2fe; border-radius: 16px; background: #fff; padding: 16px; }
    .sw-mat img { width: 64px; height: 96px; flex-shrink: 0; border-radius: 8px; object-fit: contain; background: #f8fafc; }
    .sw-mat-b { min-width: 0; flex: 1; }
    .sw-mat-b .st { font-size: 10px; font-weight: 600; } .st.ok { color: #047857; } .st.soon { color: #b45309; } .st.off { color: #be123c; }
    .sw-mat-b h3 { margin-top: 4px; }
    .sw-mat-b p { margin-top: 4px; font-size: 11px; color: #64748b; }
    .sw-mat-b .row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 12px; }
    .sw-mat-b .student-class-enter { padding: 0 12px; display: inline-flex; }

    /* ── Phụ huynh (student-family-* của source) ── */
    .student-family-grid { align-items: stretch; }
    .student-family-linked, .student-family-link-form { display: flex; flex-direction: column; }
    .student-family-requests { grid-column: 1 / -1; }
    .student-family-linked > .student-family-footnote { margin-top: auto; padding-top: 12px; }
    .student-family-link-form > .student-family-help { margin-top: auto; padding-top: 14px; }
    .student-family-row { margin-top: 10px; padding-top: 10px; border-top: 1px solid #edf2f7; overflow-wrap: anywhere; }
    .student-family-row .student-connection-person { padding: 0 0 8px; }
    .student-family-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .student-family-form { display: grid; gap: 10px; margin-top: 12px; }
    .student-family-form label { display: grid; gap: 5px; font-size: 11px; font-weight: 600; }
    .student-family-form :is(input, select) { width: 100%; min-width: 0; min-height: 38px; padding: 8px 10px; border: 1px solid #dce7ec; border-radius: 10px; background: #f8fbfd; color: #36566d; font-family: inherit; font-size: 12px; font-weight: 500; outline: none; }
    .student-family-form :is(input, select):focus { border-color: #6babc2; box-shadow: 0 0 0 3px #2d7fa31c; }
    .student-family-form > button { justify-self: start; }
    .student-family-remove, .student-family-secondary { min-height: 32px; border-radius: 8px; padding: 5px 10px; font-weight: 600; cursor: pointer; }
    .student-family-remove { background: #fff1f2; border: 1px solid #fecdd3; color: #a52c41; }
    .student-family-remove:hover { background: #ffe4e6; }
    .student-family-secondary { background: #f1f5f9; border: 1px solid #dce7ec; color: #466078; }
    .student-family-confirm, .student-family-candidate { padding: 12px; margin-top: 10px; border-radius: 10px; background: #f4f8fc; }
    .student-family-confirm p { font-size: 12px; line-height: 1.5; }
    .student-family-help { display: flex; align-items: flex-start; gap: 6px; margin-top: 14px; font-size: 11px; line-height: 1.5; color: #6a8094; }
    .student-family-help svg { margin-top: 2px; }
    .student-family-footnote { margin-top: 10px; }
    .student-family-alert, .student-family-notice { padding: 10px 12px; border-radius: 10px; font-size: 12px; line-height: 1.5; }
    .student-family-alert { background: #fff1f2; color: #a52c41; }
    .student-family-notice { background: #eff9f5; color: #246b52; }
    .student-family-form .err { margin: 0; font-size: 11px; color: #a52c41; }
</style>
<script>
    /* Phân trang phía trình duyệt cho khu Học sinh (10 dòng/trang): dữ liệu đã được máy chủ giới hạn số dòng. */
    window.swPager = window.swPager || function (total, size, extra) {
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
            show: function (i) { return i >= (this.page - 1) * this.size && i < this.page * this.size; },
            go: function (n) { this.page = Math.min(Math.max(1, n), this.pages); },
        };
        return extra ? Object.defineProperties(base, Object.getOwnPropertyDescriptors(extra)) : base;
    };
</script>
@endonce
