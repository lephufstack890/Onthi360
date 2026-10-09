{{-- ═══════════ CSS CHO PHẦN GIAO BÀI / BÀI ĐƯỢC GIAO / BÀI ĐÃ GIAO ═══════════
     SỬA 7/10 — chép từ education-main/src/components (quickAssign.css, assignedWork.css,
     assignmentManagement.css) và bổ sung vài lớp cho thanh tab phạm vi.

     VÌ SAO viết CSS thường chứ không dùng class Tailwind tuỳ ý: máy chủ KHÔNG chạy được vite, mọi
     class Tailwind phải có sẵn trong bản CSS đã build — bộ màu/kích thước mới thì không có.
     Tất cả tên lớp ở đây bắt đầu bằng oi-asg-/oi-qa-/managed-/assignment-management để không
     đụng class của nơi khác. --}}
<style>
    /* ── thanh tab phạm vi: Tất cả / Được giao / Đã giao ── */
    .oi-scope-tabs { display: flex; min-width: 0; flex-wrap: wrap; align-items: center; gap: .375rem; }
    .oi-scope-tab { min-height: 2.5rem; white-space: nowrap; border-radius: .5rem; padding: .375rem .875rem; font-size: .75rem; line-height: 1rem; font-weight: 700; background: #F8FBFC; color: #46657A; transition: background-color .15s, color .15s; cursor: pointer; }
    .oi-scope-tab:hover { background: #EAF5F8; color: #126F91; }
    .oi-scope-tab[aria-pressed="true"] { background: #D78A2D; color: #fff; box-shadow: 0 3px 8px rgba(215, 138, 45, .2); }
    .oi-scope-tab.is-all[aria-pressed="true"] { background: #138A8A; box-shadow: 0 3px 8px rgba(19, 138, 138, .2); }
    .oi-scope-tab:focus-visible { outline: 2px solid #9DC8D7; outline-offset: 2px; }

    /* ── chip trạng thái bài được giao ── */
    .oi-asg-filters { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .oi-asg-filters > span.oi-asg-filter-label { display: inline-flex; align-items: center; gap: .375rem; font-size: 11px; font-weight: 700; color: #45657D; }
    .oi-asg-chip { display: inline-flex; min-height: 2.25rem; align-items: center; gap: .5rem; white-space: nowrap; border: 1px solid #DDEAF0; border-radius: 999px; background: #F8FBFC; padding: .375rem .75rem; font-size: 11px; font-weight: 700; color: #45657D; cursor: pointer; transition: border-color .15s, background-color .15s; }
    .oi-asg-chip:hover { border-color: #9DC8D7; background: #EAF5F8; }
    .oi-asg-chip[aria-pressed="true"] { border-color: #126F91; background: #126F91; color: #fff; }
    .oi-asg-chip[aria-pressed="true"].is-done { border-color: #237052; background: #237052; }
    .oi-asg-chip b { border-radius: 999px; background: #EAF0F3; padding: 1px 6px; font-size: 10px; font-weight: 700; }
    .oi-asg-chip[aria-pressed="true"] b { background: rgba(255, 255, 255, .2); }

    /* ── huy hiệu trạng thái + dòng người giao/hạn ── */
    .oi-asg-badge { display: inline-flex; flex-shrink: 0; align-items: center; gap: 4px; white-space: nowrap; border-radius: 999px; padding: 4px 8px; margin-top: 2px; font-size: 10px; font-weight: 700; background: #F2F6F8; color: #607A90; }
    .oi-asg-badge.is-completed { background: #237052; color: #fff; }
    .oi-asg-badge.is-pending { background: #FFF3D0; color: #886323; }
    .oi-asg-meta { display: inline-flex; max-width: 100%; flex-wrap: wrap; align-items: center; gap: 4px 8px; margin-top: 4px; border-radius: 6px; background: #FFF7E3; padding: 4px 8px; font-size: 10px; font-weight: 600; color: #866523; }
    .oi-asg-meta > span { display: inline-flex; min-width: 0; align-items: center; gap: 4px; }
    .oi-asg-meta > span.is-overdue { color: #BE123C; }
    .oi-asg-meta svg { width: 12px; height: 12px; flex-shrink: 0; }

    /* ── cột kết quả của bảng bài tập ở tab "Bài được giao": rộng hơn cột Tỷ lệ AC ── */
    @media (min-width: 1024px) {
        .oi-prob-grid.oi-prob-grid--asg { grid-template-columns: minmax(0, 1fr) 148px 120px 64px 100px 200px 140px; }
    }
    @media (min-width: 1280px) {
        .oi-prob-grid.oi-prob-grid--asg { grid-template-columns: minmax(0, 1fr) 140px 112px 64px 120px 96px 190px 132px; }
    }

    /* ── kết quả / điểm của một lượt giao ── */
    .oi-asg-result { line-height: 1.5; }
    .oi-asg-result strong { font-size: 14px; }
    .oi-asg-result strong small { font-size: 11px; font-weight: 500; }
    .oi-asg-result .is-ac { color: #2F8A6B; }
    .oi-asg-result .is-partial { color: #866523; }
    .oi-asg-result .is-wa { color: #BE123C; }
    .oi-asg-result .is-none { color: #607A90; font-size: 12px; }
    .oi-asg-result p { margin-top: 4px; font-size: 10px; color: #607A90; }

    /* ── nút "Giao bài"/"Giao đề" ── */
    .oi-assign-chip { display: inline-flex; align-items: center; justify-content: center; gap: 5px; flex-shrink: 0; min-height: 28px; border: 1px solid #b8dfd6; border-radius: 999px; padding: 4px 10px; background: #eff9f5; color: #28765e; font-size: 11px; line-height: 16px; font-weight: 700; cursor: pointer; white-space: nowrap; }
    .oi-assign-chip:hover { background: #dff2e9; border-color: #86bda9; }
    .oi-assign-chip:focus-visible { outline: 3px solid #9dc8d7; outline-offset: 3px; }
    .oi-assign-chip svg { width: 12px; height: 12px; }
    /* SỬA 7/10 — độ khó / đánh giá sao / tỉnh-khu vực của thẻ đề và màn chi tiết đề (bản mẫu: DifficultyStars,
       ExamRating, ContentLocation). CSS thuần vì máy chủ không build lại Tailwind. */
    .oi-muted-note { font-size: 11px; line-height: 16px; color: #607A90; }
    .oi-stars { display: inline-flex; align-items: center; gap: 2px; white-space: nowrap; }
    .oi-star { width: 13px; height: 13px; flex-shrink: 0; }
    .oi-star.is-on { fill: #E9B44A; color: #C58B31; }
    .oi-star.is-off { fill: none; color: #C9D7E0; }
    .oi-stars-num { margin-left: 4px; font-size: 10px; font-weight: 600; color: #607A90; font-variant-numeric: tabular-nums; }
    .oi-stars-label { margin-left: 4px; font-size: 10px; font-weight: 500; color: #607A90; }
    .oi-rating { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 2px 6px; font-size: 11px; }
    .oi-rating-stars { display: inline-flex; align-items: center; gap: 2px; }
    .oi-rating-star { position: relative; display: inline-block; width: 14px; height: 14px; }
    .oi-rating-star > .oi-star { position: absolute; inset: 0; width: 14px; height: 14px; }
    .oi-rating-fill { position: absolute; top: 0; bottom: 0; left: 0; overflow: hidden; }
    .oi-rating-fill .oi-star { width: 14px; height: 14px; max-width: none; }
    .oi-rating strong { color: #8A5B12; }
    .oi-rating-count { color: #607A90; }
    .oi-meta-row { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
    .oi-meta-row > .oi-meta-label { font-size: 10px; font-weight: 600; color: #607A90; }
    .oi-loc { display: flex; min-width: 0; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 8px; }
    .oi-loc-badge { display: inline-flex; max-width: 100%; align-items: center; gap: 4px; border: 1px solid #D6E3EF; border-radius: 6px; background: #F3F8FC; padding: 4px 8px; font-size: 10px; font-weight: 600; line-height: 16px; color: #45657D; }
    .oi-loc-badge svg { width: 12px; height: 12px; flex-shrink: 0; }
    .oi-loc-text { min-width: 0; overflow-wrap: anywhere; }
    .oi-loc-badge.is-north { border-color: #C7D2FE; background: #EEF2FF; color: #4338CA; }
    .oi-loc-badge.is-central { border-color: #FDE68A; background: #FFFBEB; color: #92400E; }
    .oi-loc-badge.is-south { border-color: #A7F3D0; background: #ECFDF5; color: #065F46; }
    .oi-loc-badge.is-all { border-color: #BAE6FD; background: #F0F9FF; color: #075985; }
    .oi-log-link { text-decoration: none; transition: background .15s, border-color .15s; }
    .oi-log-link:hover { border-color: #B9CCDC; background: #E4EEF7; }
    .oi-log-link:focus-visible { outline: 2px solid #CBEAF1; outline-offset: 2px; }
    .oi-log-square { display: grid; place-items: center; width: 40px; height: 40px; flex-shrink: 0; border: 1px solid #D6E3EF; border-radius: 8px; background: #EEF4FA; color: #365B7A; text-decoration: none; transition: background .15s, border-color .15s, color .15s; }
    /* SỬA 9/10 — nút "Nhật ký làm đề" trên thẻ đề (PracticePage.jsx: min-h-10, viền #D6E3EF, nền #EEF4FA, chữ 10px đậm). */
    .oi-log-btn { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; gap: 4px; min-height: 40px; padding: 8px; border: 1px solid #D6E3EF; border-radius: 8px; background: #EEF4FA; color: #365B7A; font-size: 10px; font-weight: 700; line-height: 1.4; white-space: nowrap; text-decoration: none; transition: border-color .15s, background-color .15s, color .15s; }
    .oi-log-btn:hover { border-color: #9DC8D7; background: #EAF5F8; color: #126F91; }
    .oi-log-btn svg { width: 14px; height: 14px; flex: none; }
    .oi-log-square:hover { border-color: #9DC8D7; background: #EAF5F8; color: #126F91; }
    .oi-log-square svg { width: 14px; height: 14px; }

    /* ── popup giao bài ── */
    .oi-qa-backdrop { position: fixed; inset: 0; z-index: 80; display: flex; align-items: center; justify-content: center; padding: 12px; background: rgba(16, 43, 73, .65); backdrop-filter: blur(3px); }
    .oi-qa-panel { position: relative; width: min(520px, calc(100vw - 24px)); max-height: calc(100dvh - 32px); overflow: auto; border: 1px solid #ddeaf0; border-radius: 22px; background: #fff; color: #183d5e; box-shadow: 0 24px 80px rgba(18, 59, 104, .2); padding: 24px; scrollbar-width: thin; scrollbar-color: #c4d4df transparent; overscroll-behavior-y: contain; }
    .oi-qa-heading { display: flex; align-items: center; gap: 12px; }
    .oi-qa-icon { display: grid; place-items: center; width: 44px; height: 44px; flex-shrink: 0; border-radius: 14px; background: #eff9f5; color: #28765e; }
    .oi-qa-heading h2 { margin: 0; font-size: 18px; font-weight: 750; }
    .oi-qa-heading p { margin-top: 3px; color: #607a90; font-size: 12px; }
    .oi-qa-close { display: grid; place-items: center; margin-left: auto; min-width: 36px; min-height: 36px; border-radius: 10px; color: #607a90; cursor: pointer; }
    .oi-qa-close:hover { background: #f0f5f8; }
    .oi-qa-subject { margin: 20px 0; padding: 13px 15px; border: 1px solid #ddeaf0; border-radius: 12px; background: #f8fbfc; overflow-wrap: anywhere; }
    .oi-qa-subject span { display: block; font-size: 10px; color: #607a90; }
    .oi-qa-subject strong { display: block; margin-top: 4px; font-size: 13px; }
    .oi-qa-panel label { display: flex; align-items: center; gap: 7px; margin: 18px 0 8px; font-size: 12px; font-weight: 700; }
    .oi-qa-panel input { display: block; box-sizing: border-box; width: 100%; min-width: 0; min-height: 44px; border: 1px solid #cfdee7; border-radius: 10px; padding: 10px 12px; background: #fff; color: #183d5e; font-size: 14px; }
    .oi-qa-panel input:focus { outline: 3px solid #eaf5f8; border-color: #2d7fa3; }
    .oi-qa-note { margin-top: 7px; color: #607a90; font-size: 11px; line-height: 1.6; }
    .oi-qa-error { margin-top: 16px; border-radius: 10px; padding: 10px 12px; background: #fff1f2; color: #be123c; font-size: 12px; }
    .oi-qa-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; padding-top: 18px; border-top: 1px solid #e7eff3; }
    .oi-qa-primary, .oi-qa-cancel { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 42px; border-radius: 10px; padding: 10px 18px; font-size: 12px; font-weight: 700; cursor: pointer; }
    .oi-qa-primary { background: #28795e; color: #fff; }
    .oi-qa-primary:hover { background: #21664f; }
    .oi-qa-primary:disabled { opacity: .6; cursor: wait; }
    .oi-qa-cancel { border: 1px solid #ddeaf0; color: #52687b; background: #fff; }
    .oi-qa-success { text-align: center; padding: 6px 0; font-size: 13px; line-height: 1.8; }
    .oi-qa-success > svg { margin: 0 auto 10px; width: 32px; height: 32px; color: #28795e; }
    .oi-qa-success h3 { font-weight: 700; font-size: 17px; margin-bottom: 12px; }
    .oi-qa-success .oi-qa-primary { width: 100%; margin-top: 20px; }
    .oi-qa-panel button:focus-visible { outline: 3px solid #9dc8d7; outline-offset: 3px; }
    @media (max-width: 480px) { .oi-qa-panel { padding: 18px; } .oi-qa-heading h2 { font-size: 16px; } }

    /* ── danh sách ĐỀ được giao (assignedWork.css) ── */
    .oi-asg-list { overflow: hidden; border: 1px solid #DDEAF0; border-radius: 1rem; background: #fff; box-shadow: 0 2px 10px rgba(28, 91, 121, .05); }
    .assigned-work-grid { display: grid; grid-template-columns: minmax(0, 1fr) 170px 130px 220px 128px; gap: 16px; padding: 14px 16px; align-items: center; }
    .assigned-work-header { padding-block: 11px; background: #F4F8FB; color: #365B7A; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; border-left: 2px solid transparent; border-bottom: 1px solid #DDEAF0; }
    .assigned-work-row { border-left: 2px solid transparent; border-bottom: 1px solid #E7EFF3; background: #FCFEFF; font-size: 11px; line-height: 1.6; color: #607A90; transition: background .15s, border-color .15s; }
    .assigned-work-row.is-alternate { background: #F7FBFC; }
    .assigned-work-row:hover { border-left-color: #2D7FA3; background: #F2F8FA; }
    .assigned-work-grid > div { min-width: 0; }
    .assigned-work-title { display: block; width: 100%; text-align: left; font-size: 13px; line-height: 1.6; font-weight: 600; color: #2D7FA3; }
    .assigned-work-title:hover { color: #1F647F; text-decoration: underline; }
    .assigned-work-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 3px 8px; margin-top: 5px; font-size: 10px; }
    .assigned-work-meta code { font-size: 10px; color: #607A90; }
    .assigned-work-owner > strong { display: block; font-size: 11px; font-weight: 600; color: #45657D; }
    .assigned-work-group { display: flex; align-items: flex-start; gap: 4px; margin-top: 3px; font-size: 10px; }
    .assigned-work-group svg { margin-top: 2px; flex-shrink: 0; width: 12px; height: 12px; }
    .assigned-work-owner small, .assigned-work-due small { display: block; margin-top: 3px; font-size: 10px; color: #71869A; }
    .assigned-work-due > strong { display: flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: #45657D; white-space: nowrap; }
    .assigned-work-due > strong svg { width: 13px; height: 13px; }
    .assigned-work-due > strong.is-overdue { color: #BE123C; }
    .assigned-work-action-label { text-align: center; }
    .assigned-work-actions { display: flex; flex-direction: column; gap: 5px; }
    .assigned-work-actions :is(button, a) { display: flex; justify-content: center; align-items: center; gap: 5px; min-height: 32px; padding: 5px 8px; border-radius: 7px; font-size: 11px; font-weight: 600; cursor: pointer; white-space: nowrap; }
    .assigned-work-actions svg { width: 14px; height: 14px; }
    .assigned-work-primary { background: #126F91; color: #fff; border: 1px solid #126F91; }
    .assigned-work-primary:hover { background: #0F5E7B; }
    .assigned-work-primary.is-complete { background: #2F8A6B; border-color: #2F8A6B; }
    .assigned-work-actions a.is-secondary { color: #45657D; background: #EEF4FA; border: 1px solid #D6E3EF; }
    .assigned-work-actions a.is-secondary:hover { background: #EAF5F8; border-color: #9DC8D7; }
    .assigned-work-mobile-label { display: none; }
    @media (min-width: 1024px) and (max-width: 1199px) { .assigned-work-grid { grid-template-columns: minmax(0, 1fr) 145px 106px 190px 110px; gap: 12px; padding-inline: 12px; } }
    @media (max-width: 1023px) {
        .assigned-work-header { display: none; }
        .assigned-work-grid { grid-template-columns: minmax(0, 1fr) minmax(110px, .7fr); gap: 12px 16px; padding: 14px; }
        .assigned-work-identity, .assigned-work-result, .assigned-work-actions { grid-column: 1 / -1; }
        .assigned-work-owner, .assigned-work-due { align-self: start; }
        .assigned-work-mobile-label { display: block; margin-bottom: 4px; color: #607A90; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .assigned-work-result { padding-top: 10px; border-top: 1px solid #E7EFF3; }
        .assigned-work-actions { flex-direction: row; }
        .assigned-work-actions :is(button, a) { flex: 1; min-height: 36px; }
    }

    /* ── bộ lọc đề thi (tìm kiếm + tỉnh/thành + cuộc thi) ── */
    .oi-field-label { display: block; margin-bottom: 6px; font-size: 11px; font-weight: 600; color: #52687B; }
    .oi-select { display: block; width: 100%; min-width: 0; min-height: 40px; border: 1px solid #D6E3EF; border-radius: 12px; background: #F8FAFB; padding: 0 8px; font-size: 12px; font-weight: 500; color: #365B7A; }
    .oi-select:focus { outline: 2px solid #EAF5F8; border-color: #9DC8D7; }

    /* ── quản lý: Bài đã giao / Đề đã giao (assignmentManagement.css) ── */
    .assignment-management { min-width: 0; display: flex; flex-direction: column; gap: 14px; color: #466278; }
    .managed-heading { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
    .managed-heading p { font-size: 10px; font-weight: 700; letter-spacing: .08em; color: #607a90; }
    .managed-heading h2 { display: flex; align-items: center; gap: 8px; margin: 4px 0; color: #123b68; font-size: 21px; font-weight: 750; }
    .managed-heading h2 svg { width: 21px; height: 21px; }
    .managed-heading span { font-size: 12px; }
    .managed-library { display: inline-flex; align-items: center; gap: 5px; min-height: 36px; border: 1px solid #cde2e9; border-radius: 10px; padding: 7px 11px; background: #fff; font-size: 12px; font-weight: 650; color: #126f91; cursor: pointer; }
    .managed-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .managed-stats > div { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 5px 8px; padding: 15px; border: 1px solid #dce7ec; border-radius: 14px; background: #fff; }
    .managed-stats svg { grid-column: 2; grid-row: 1 / 3; width: 18px; height: 18px; color: #126f91; }
    .managed-stats span { grid-column: 1; grid-row: 1; font-size: 12px; font-weight: 650; }
    .managed-stats strong { grid-column: 1; font-size: 25px; line-height: 1.2; color: #123b68; }
    .managed-stats small { grid-column: 1 / -1; font-size: 11px; }
    .managed-stats .is-green { background: #eff9f5; border-color: #cde7db; }
    .managed-stats .is-amber { background: #fff9e9; border-color: #ecdfbd; }
    .managed-stats .is-red { background: #fff5f4; border-color: #f0dbd8; }
    .managed-filters { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr); gap: 12px; padding: 14px; border: 1px solid #dce7ec; border-radius: 14px; background: #fff; }
    .managed-filters label { display: flex; flex-direction: column; gap: 6px; min-width: 0; font-size: 11px; font-weight: 650; }
    .managed-filters input, .managed-filters select, .managed-attempt-select select { width: 100%; min-width: 0; min-height: 40px; padding: 8px 10px; border: 1px solid #d6e3eb; border-radius: 9px; background: #f8fbfc; color: #365b7a; font-size: 12px; font-weight: 500; }
    .managed-search > div { position: relative; }
    .managed-search svg { position: absolute; top: 12px; left: 10px; width: 16px; height: 16px; }
    .managed-search input { padding-left: 34px; }
    .managed-count { display: flex; align-items: center; justify-content: space-between; font-size: 11px; }
    .managed-count button { color: #126f91; font-weight: 700; cursor: pointer; }
    .managed-list { border: 1px solid #dce7ec; border-radius: 15px; overflow: hidden; background: #fff; }
    .managed-grid { display: grid; grid-template-columns: minmax(180px, 1.6fr) minmax(115px, .8fr) minmax(100px, .7fr) minmax(135px, 1fr) minmax(140px, 1fr) 85px; align-items: center; gap: 12px; padding: 14px; }
    .managed-table-header { background: #edf4f8; font-size: 10px; text-transform: uppercase; font-weight: 750; letter-spacing: .04em; }
    .managed-entry + .managed-entry { border-top: 1px solid #e7eff3; }
    .managed-row { font-size: 12px; }
    .managed-entry:nth-child(odd) .managed-row { background: #fafcfd; }
    .managed-row > div { min-width: 0; }
    .managed-row small { display: block; font-size: 10px; margin-top: 5px; color: #607a90; }
    .managed-identity { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; overflow-wrap: anywhere; }
    .managed-identity h3 { font-size: 13px; font-weight: 700; line-height: 1.5; color: #123b68; }
    .managed-identity code { font-size: 10px; color: #7a8e9f; }
    .managed-identity strong { font-size: 12px; color: #28765e; }
    .managed-identity > span { font-size: 11px; }
    .managed-status { display: inline-flex; border-radius: 20px; padding: 5px 8px; font-size: 10px; font-weight: 700; }
    .managed-status.neutral { background: #edf3f7; color: #52687b; }
    .managed-status.green { background: #dff2e9; color: #21664f; }
    .managed-status.amber { background: #fff3d0; color: #886323; }
    .managed-status.red { background: #ffe4e6; color: #b02e43; }
    .managed-score { font-size: 15px; color: #123b68; }
    .managed-row .managed-late, .managed-late { color: #b02e43; font-size: 11px; }
    .managed-actions button { display: inline-flex; align-items: center; justify-content: center; gap: 4px; min-height: 34px; padding: 6px 8px; border: 1px solid #cde2e9; border-radius: 8px; color: #126f91; background: #f0f8fa; font-size: 11px; font-weight: 650; cursor: pointer; }
    .managed-actions svg { width: 14px; height: 14px; }
    .managed-detail { margin: 0 14px 16px; padding: 18px; border: 1px solid #cde2e9; border-radius: 12px; background: #f6fafc; }
    .managed-detail-title { display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; font-size: 12px; overflow-wrap: anywhere; }
    .managed-detail-title h3 { font-size: 14px; font-weight: 700; color: #123b68; }
    .managed-milestones { display: grid; grid-template-columns: repeat(auto-fit, minmax(145px, 1fr)); gap: 10px; margin: 14px 0; }
    .managed-milestones > div { padding: 10px; border: 1px solid #dce7ec; border-radius: 9px; background: #fff; }
    .managed-milestones dt { font-size: 10px; color: #607a90; }
    .managed-milestones dd { font-size: 12px; font-weight: 650; margin-top: 5px; overflow-wrap: anywhere; }
    .managed-result { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; font-size: 12px; margin: 14px 0; }
    .managed-result strong { font-size: 18px; color: #123b68; }
    .managed-answer h4 { display: flex; gap: 12px; font-size: 12px; font-weight: 700; margin: 10px 0; }
    .managed-answer h4 span { font-weight: 500; }
    .managed-detail pre { max-height: 380px; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; padding: 14px; border-radius: 9px; background: #132e45; color: #e1eef6; font-size: 12px; line-height: 1.7; }
    .managed-no-submission { font-size: 12px; margin-top: 12px; }
    .managed-empty { display: flex; flex-direction: column; align-items: center; gap: 10px; text-align: center; padding: 35px 20px; }
    .managed-empty svg { width: 32px; height: 32px; color: #86a8bd; }
    .managed-empty h3 { font-size: 15px; font-weight: 700; color: #123b68; }
    .managed-empty p { font-size: 12px; max-width: 450px; line-height: 1.7; }
    .managed-pagination { display: flex; align-items: center; justify-content: flex-end; gap: 12px; font-size: 12px; }
    .managed-pagination button { display: grid; place-items: center; width: 36px; height: 36px; border: 1px solid #dce7ec; border-radius: 8px; background: #fff; cursor: pointer; }
    .managed-pagination button:disabled { opacity: .4; cursor: default; }
    .managed-pagination svg { width: 16px; height: 16px; }
    .managed-note { font-size: 11px; line-height: 1.7; color: #607a90; }
    .assignment-management :is(button, a, input, select, summary):focus-visible { outline: 3px solid #9dc8d7; outline-offset: 2px; }
    @media (max-width: 1199px) {
        .managed-table-header { display: none; }
        .managed-row { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .managed-identity { grid-column: 1 / -1; }
        .managed-row [data-label]::before { content: attr(data-label); display: block; font-size: 10px; color: #607a90; margin-bottom: 6px; }
        .managed-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; border-top: 1px solid #e7eff3; padding-top: 10px; }
    }
    @media (max-width: 639px) {
        .managed-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .managed-stats > div { padding: 12px; }
        .managed-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .managed-search { grid-column: 1 / -1; }
        .managed-detail { margin: 0 10px 12px; padding: 12px; }
        .managed-milestones { grid-template-columns: minmax(0, 1fr); }
    }

    .assigned-work-meta > span { padding-left: 8px; border-left: 1px solid #CDDDE7; }
    .oi-exam-filters { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; align-items: end; border: 1px solid #DDEAF0; border-radius: 1rem; background: #fff; padding: 12px; box-shadow: 0 2px 10px rgba(28, 91, 121, .04); }
    .oi-exam-filter-selects { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; min-width: 0; }
    .oi-exam-filter-selects > label:last-child { grid-column: 1 / -1; }
    @media (min-width: 640px) { .oi-exam-filter-selects { grid-template-columns: repeat(3, minmax(0, 1fr)); } .oi-exam-filter-selects > label:last-child { grid-column: auto; } }
    @media (min-width: 1024px) { .oi-exam-filters { grid-template-columns: minmax(0, 1.4fr) minmax(0, 2fr); } }
    /* ── Ô chọn nhiều học sinh (Select2) trong popup giao bài ── */
    .oi-pk { position: relative; }
    .oi-pk > label { margin-top: 0; }
    .oi-pk-count { margin-left: auto; padding: 2px 9px; border-radius: 999px; background: #eff9f5; color: #28765e; font-size: 11px; font-weight: 700; }
    .oi-pk-box { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; box-sizing: border-box; width: 100%; min-height: 46px; padding: 6px 34px 6px 8px; border: 1px solid #cfdee7; border-radius: 12px; background: #fff; cursor: text; position: relative; transition: border-color .15s, box-shadow .15s; }
    .oi-pk-box:hover { border-color: #b3cad7; }
    .oi-pk-box.is-open, .oi-pk-box:focus-within { border-color: #2d7fa3; box-shadow: 0 0 0 3px #eaf5f8; }
    .oi-pk-box .oi-pk-input { flex: 1 1 140px; width: auto; min-width: 120px; min-height: 30px; padding: 4px 4px; border: 0; border-radius: 0; background: transparent; font-size: 14px; }
    .oi-pk-box .oi-pk-input:focus { outline: none; border-color: transparent; }
    .oi-pk-caret { position: absolute; top: 50%; right: 10px; display: grid; place-items: center; width: 20px; height: 20px; margin-top: -10px; color: #7c93a6; pointer-events: none; transition: transform .15s; }
    .oi-pk-caret svg { width: 16px; height: 16px; }
    .oi-pk-box.is-open .oi-pk-caret { transform: rotate(180deg); }
    .oi-pk-chip { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; padding: 3px 4px 3px 4px; border: 1px solid #cfe6dc; border-radius: 999px; background: #eff9f5; color: #1f5f4a; font-size: 12px; font-weight: 600; line-height: 1; }
    .oi-pk-chip-name { overflow: hidden; max-width: 170px; text-overflow: ellipsis; white-space: nowrap; }
    .oi-pk-chip-x { border: 0; background: transparent; padding: 0; display: grid; place-items: center; flex: none; width: 20px; height: 20px; border-radius: 999px; color: #4c8a73; cursor: pointer; transition: background .12s, color .12s; }
    .oi-pk-chip-x:hover { background: #d2ebe0; color: #b42318; }
    .oi-pk-chip-x svg { width: 12px; height: 12px; }
    .oi-pk-avatar { display: inline-grid; place-items: center; flex: none; width: 22px; height: 22px; border-radius: 999px; background: linear-gradient(135deg, #2d7fa3, #28795e); color: #fff; font-size: 11px; font-weight: 800; line-height: 1; }
    .oi-pk-drop { margin-top: 6px; overflow: hidden; border: 1px solid #ddeaf0; border-radius: 12px; background: #fff; box-shadow: 0 10px 28px rgba(18, 59, 104, .1); position: relative; }
    .oi-pk-bar { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px; border-bottom: 1px solid #edf3f6; background: #f8fbfc; }
    .oi-pk-hint { color: #607a90; font-size: 11px; font-weight: 600; }
    .oi-pk-bar-actions { display: inline-flex; gap: 12px; }
    .oi-pk-link { border: 0; background: transparent; padding: 0; color: #1f6f8f; font-size: 11px; font-weight: 700; cursor: pointer; }
    .oi-pk-link:hover { text-decoration: underline; }
    .oi-pk-link.is-danger { color: #b42318; }
    .oi-pk-list { margin: 0; padding: 4px; max-height: 232px; overflow-y: auto; list-style: none; scrollbar-width: thin; scrollbar-color: #c4d4df transparent; overscroll-behavior: contain; }
    .oi-pk-opt { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 9px; cursor: pointer; }
    .oi-pk-opt.is-active { background: #f0f7fa; }
    .oi-pk-opt.is-selected { background: #eff9f5; }
    .oi-pk-opt.is-selected.is-active { background: #e3f4ec; }
    .oi-pk-opt .oi-pk-avatar { width: 30px; height: 30px; font-size: 13px; }
    .oi-pk-opt-text { display: flex; flex-direction: column; flex: 1 1 auto; min-width: 0; }
    .oi-pk-opt-name { overflow: hidden; color: #123b68; font-size: 13px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .oi-pk-opt-sub { overflow: hidden; margin-top: 2px; color: #607a90; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
    .oi-pk-tick { display: grid; place-items: center; flex: none; width: 22px; height: 22px; border-radius: 999px; background: #28795e; color: #fff; }
    .oi-pk-tick svg { width: 13px; height: 13px; }
    .oi-pk-empty { display: flex; align-items: center; justify-content: center; gap: 8px; margin: 0; padding: 18px 12px; color: #607a90; font-size: 12px; }
    .oi-pk-empty svg { width: 16px; height: 16px; }
    .oi-pk-empty.is-error { color: #b42318; }
    .oi-pk-loading { position: absolute; inset: 37px 0 0 0; display: grid; place-items: center; background: rgba(255, 255, 255, .65); }
    .oi-pk-spin { width: 22px; height: 22px; border: 3px solid #d6e6ee; border-top-color: #2d7fa3; border-radius: 50%; animation: oi-pk-rot .7s linear infinite; }
    @keyframes oi-pk-rot { to { transform: rotate(360deg); } }
    /* Màn thành công: danh sách học sinh vừa được giao */
    .oi-qa-result { margin: 0 0 12px; padding: 4px; max-height: 190px; overflow-y: auto; border: 1px solid #ddeaf0; border-radius: 12px; background: #f8fbfc; list-style: none; text-align: left; scrollbar-width: thin; }
    .oi-qa-result li { display: flex; align-items: center; gap: 9px; padding: 6px 8px; border-radius: 8px; }
    .oi-qa-result li + li { border-top: 1px solid #edf3f6; }
    .oi-qa-result-name { overflow: hidden; flex: 1 1 auto; min-width: 0; color: #123b68; font-size: 13px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .oi-qa-result-acc { overflow: hidden; max-width: 45%; color: #607a90; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
    @media (max-width: 480px) { .oi-pk-chip-name { max-width: 120px; } .oi-pk-opt-sub { font-size: 10.5px; } }
</style>
