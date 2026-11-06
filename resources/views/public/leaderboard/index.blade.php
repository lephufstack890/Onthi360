@extends('layouts.guest')

@section('title', 'Bảng xếp hạng học sinh')
@section('meta-description', 'Bảng xếp hạng học sinh Ôn Thi 360 — theo tổng điểm, số bài đã giải, tỷ lệ đúng và chuỗi luyện tập; xem theo toàn thời gian, tháng này, cuộc thi gần nhất hoặc lớp của bạn.')

@section('content')
{{-- ═══════════════ [LEADERBOARD] MÀN BẢNG XẾP HẠNG ═══════════════
     SỬA 8/10 — dựng lại theo ĐÚNG source giao diện mới của khách:
     education-main/src/components/LeaderboardPage.jsx + leaderboard.css (class `lb-*` chép nguyên).
     React state đổi sang Alpine (tìm kiếm, ẩn tên, mở chi tiết, phân trang 5/10 chạy tại trình duyệt);
     4 phạm vi là link thật tới ?scope= vì mỗi phạm vi tính từ một nguồn dữ liệu riêng.

     Dữ liệu: App\Services\Public\LeaderboardService::indexData() — số liệu THẬT tính từ bài làm của học sinh
     (cách tính ghi ở docblock của service, cũng là nội dung ô "Cách xếp hạng" dưới đây).

     KHÁC bản mẫu (chủ ý, không phải thiếu sót):
       · Bản mẫu có "Trường", "Lớp/khối", "Rating" — hệ thống chưa có dữ liệu này nên dòng phụ dưới tên
         là số bài đã thử (hoặc tỉnh/thành ở bảng lớp) và ô chi tiết đổi sang số liệu có thật.
       · Ô "Ẩn tên học sinh" của bản mẫu đã BỎ theo yêu cầu khách: mọi bảng hiện tên thật + tỉnh/thành. --}}
@php
    $rows = $rows ?? [];
    $hero = $hero ?? ['students' => 0, 'solved' => 0, 'bestStreak' => 0];
    $contest = $contest ?? null;
    $classes = $classes ?? [];
    $you = $you ?? null;
    $empty = $empty ?? null;
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $updated = !empty($updatedAt) ? \Illuminate\Support\Carbon::parse($updatedAt)->setTimezone(config('app.timezone'))->format('H:i d/m/Y') : null;

    // Đổi phạm vi: giữ nguyên chọn lựa riêng của phạm vi đó (cuộc thi/lớp) chứ không mang sang phạm vi khác.
    $scopeUrl = fn (string $key) => route('leaderboard.index', ['scope' => $key]);

    $config = [
        'rows' => $rows,
        'namesLocked' => (bool) $namesLocked,
        'scopeLabel' => $scopeLabel,
        'pageSize' => 10, // SỬA 4/11 (khách: "bảng xếp hạng 10 học sinh / trang")
    ];
@endphp

<style>
:where(.leaderboard-view) button { background: none; border: 0; padding: 0; color: inherit; text-align: inherit; }
:where(.leaderboard-view) select { background-color: #fff; }
body:has(.leaderboard-view) { background-color: #e0ebf0; }
.leaderboard-view {
  --lb-ink: #17394f;
  --lb-muted: #526d7d;
  --lb-line: #c8dce5;
  --lb-blue: #126f91;
  --lb-soft-surface: #eaf0f3;
  --lb-soft-hover: #dfe8ed;
  --lb-gold: #85744d;
  --lb-gold-soft: #eae5d7;
  --lb-silver: #58758a;
  --lb-silver-soft: #dfe8ee;
  --lb-bronze: #8b705c;
  --lb-bronze-soft: #ebe2db;
  /* Match practice: 24px page title, 13px item names, 12px body, 11px metadata, 10px badges. */
  --lb-font-page: 1.5rem;
  --lb-font-section: 1rem;
  --lb-font-title: .875rem;
  --lb-font-item: .8125rem;
  --lb-font-body: .75rem;
  --lb-font-meta: .6875rem;
  --lb-font-badge: .625rem;
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1440px;
  margin: 0 auto;
  color: var(--lb-ink);
  font-size: var(--lb-font-body);
  line-height: 1.5;
}
.leaderboard-view :is(button, input, select, summary) { font: inherit; }
.leaderboard-view button, .leaderboard-view summary { cursor: pointer; }
.leaderboard-view :is(button, input, select, summary):focus-visible { outline: 3px solid #8bc7d8; outline-offset: 3px; }
.leaderboard-view button { transition: background .15s, color .15s, border-color .15s; }
.leaderboard-view h1, .leaderboard-view h2, .leaderboard-view h3, .leaderboard-view p { margin: 0; }
.leaderboard-view h2 { font-size: var(--lb-font-section); font-weight: 700; letter-spacing: -.025em; }
.lb-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
.lb-hero { display: flex; align-items: center; justify-content: space-between; gap: 32px; padding: 27px 32px; border: 1px solid #245776; border-radius: 20px; background: linear-gradient(90deg, rgb(18 59 91 / .92), rgb(18 59 91 / .58) 48%, rgb(19 76 92 / .78)), url("{{ asset('assets/hero-leaderboard.jpg') }}") center right / cover no-repeat, #123b5b; color: #fff; }
.lb-eyebrow { display: flex; align-items: center; gap: 7px; color: #eacf88; font-size: var(--lb-font-meta); font-weight: 700; letter-spacing: .13em; }
.lb-hero h1 { margin-top: 7px; font-size: var(--lb-font-page); font-weight: 900; letter-spacing: -.035em; line-height: 1.25; color: #fff; }
.lb-hero-copy > p { margin-top: 7px; color: #d4e6ee; font-size: var(--lb-font-title); line-height: 1.7; }
.lb-demo-label { display: inline-flex; align-items: center; gap: 5px; margin-top: 12px; color: #b7d5e2; font-size: var(--lb-font-meta); }
.lb-demo-label > span { width: 5px; height: 5px; border-radius: 50%; background: #8bb7c9; }
.lb-hero-stats { display: grid; grid-template-columns: repeat(3, minmax(100px, 1fr)); flex: 0 1 465px; padding: 8px 0; }
.lb-hero-stats > div { display: flex; align-items: flex-start; flex-direction: column; gap: 5px; padding: 0 23px; border-left: 1px solid #ffffff26; }
.lb-hero-stats svg { color: #a9d7df; }
.lb-hero-stats strong { font-size: 24px; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1.2; margin-top: 4px; }
.lb-hero-stats strong small { font-size: var(--lb-font-body); font-weight: 500; }
.lb-hero-stats span { font-size: var(--lb-font-meta); color: #c9e0e8; white-space: nowrap; }
.lb-workspace, .lb-board { background: #eaf2f5; border: 1px solid var(--lb-line); border-radius: 16px; box-shadow: 0 4px 16px #193c5509; }
.lb-workspace { background: linear-gradient(120deg, #e4f0f2, #eaf1f7); }
.lb-scope-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 18px; border-bottom: 1px solid var(--lb-line); background: #dcebf0; border-radius: 16px 16px 0 0; }
.lb-scopes { display: flex; flex-wrap: wrap; gap: 4px; }
.lb-scopes a { display: inline-block; text-decoration: none; padding: 9px 14px; border-radius: 8px; color: #5a7182; font-size: var(--lb-font-body); font-weight: 600; white-space: nowrap; }
.lb-scopes a:hover { background: #cde2e9; }
.lb-scopes a[aria-pressed="true"] { background: #216c84; color: #fff; box-shadow: 0 3px 8px #15536e20; }
.lb-ranking-help { position: relative; color: var(--lb-muted); font-size: var(--lb-font-meta); }
.lb-ranking-help summary { display: flex; align-items: center; gap: 5px; min-height: 36px; white-space: nowrap; list-style: none; }
.lb-ranking-help summary::-webkit-details-marker { display: none; }
.lb-ranking-help > p { position: absolute; top: 42px; right: 0; z-index: 5; width: min(310px, 75vw); padding: 15px; border: 1px solid var(--lb-line); border-radius: 12px; background: #edf5f7; box-shadow: 0 8px 30px #16354d18; line-height: 1.8; }
.lb-tools { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 13px 18px; }
.lb-search { display: flex; align-items: center; gap: 9px; flex: 0 1 470px; min-width: 0; padding: 0 12px; border: 1px solid #bbd2df; border-radius: 9px; background: #f0f6f8; color: #607e8e; }
.lb-search:focus-within { border-color: #7cb3c5; box-shadow: 0 0 0 3px #126f9110; }
.lb-search > svg { flex-shrink: 0; }
.lb-search input { width: 100%; min-width: 0; height: 40px; border: 0; outline: none !important; background: transparent; color: var(--lb-ink); font-size: var(--lb-font-item); }
.lb-search input::placeholder { color: #647f8f; }
.lb-search button { display: grid; place-items: center; width: 26px; height: 26px; flex-shrink: 0; border-radius: 5px; }
.lb-search button:hover { background: #e5eef2; }
.lb-anonymous { display: flex; align-items: center; gap: 7px; font-size: var(--lb-font-meta); color: #5a7182; cursor: pointer; white-space: nowrap; }
.lb-anonymous input { width: 15px; height: 15px; accent-color: var(--lb-blue); }
.lb-section-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; padding-inline: 2px; }
.lb-section-heading > div { display: flex; align-items: center; gap: 8px; }
.lb-section-icon { display: grid; place-items: center; width: 30px; height: 30px; border: 1px solid #cad7df; border-radius: 9px; color: #60798a; background: #e5edf1; }
.lb-section-icon svg { fill: none; }
.lb-section-heading > span { font-size: var(--lb-font-meta); color: var(--lb-muted); }
.lb-podium { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.lb-podium-card { position: relative; isolation: isolate; overflow: hidden; padding: 16px 19px; border: 1px solid #cddae2; border-radius: 14px; background: var(--lb-soft-surface); box-shadow: 0 3px 12px #193c5506; }
.lb-podium-card > :not(.lb-podium-watermark) { position: relative; z-index: 1; }
.lb-podium-card.lb-place-1 { order: 2; background: #f3ecd5; border-color: #dfd4ad; }
.lb-podium-card.lb-place-2 { order: 1; background: #e1eee3; border-color: #c3d9c7; }
.lb-podium-card.lb-place-3 { order: 3; background: #f3e4d8; border-color: #dfcbb9; }
.lb-podium-watermark { position: absolute; z-index: 0; right: 22px; top: 46px; width: 100px; height: 100px; color: #718b9b; opacity: .08; transform: rotate(-13deg); pointer-events: none; }
.lb-podium-heading, .lb-podium-bottom { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.lb-place-label { display: flex; align-items: center; gap: 5px; padding: 3px 8px; border: 1px solid #ccd9e2; border-radius: 6px; font-size: var(--lb-font-meta); font-weight: 700; color: var(--lb-silver); background: var(--lb-silver-soft); }
.lb-place-label svg { fill: none; stroke: currentColor; }
.lb-place-1 .lb-place-label { color: var(--lb-gold); background: var(--lb-gold-soft); border-color: #d8d1bf; }
.lb-place-3 .lb-place-label { color: var(--lb-bronze); background: var(--lb-bronze-soft); border-color: #dbcec4; }
.lb-podium-title { font-size: var(--lb-font-badge); font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #486e89; }
.lb-place-1 .lb-podium-title, .lb-place-3 .lb-podium-title { color: #486e89; }
.lb-podium-person { display: flex; align-items: center; gap: 13px; margin: 17px 0; }
.lb-avatar { display: inline-grid; place-items: center; width: 38px; height: 38px; flex-shrink: 0; overflow: hidden; border: 1px solid #dce7eb; border-radius: 50%; background: #edf4f7; color: #65879b; }
.lb-avatar img { width: 100%; height: 100%; object-fit: cover; }
.lb-avatar > svg { width: 48%; height: 48%; }
.lb-avatar-initials { font-size: var(--lb-font-meta); font-weight: 700; letter-spacing: .02em; }
.lb-avatar-tone-0 { background: #eaf3ee; color: #4e7c64; border-color: #d8e7dd; }
.lb-avatar-tone-1 { background: #edf0f8; color: #677ba2; border-color: #dce3f0; }
.lb-avatar-tone-2 { background: #f6ede5; color: #a17c56; border-color: #eddfd2; }
.lb-avatar-large { width: 54px; height: 54px; border: 3px solid #f2f5f7; box-shadow: 0 0 0 1px #b7cbd7; }
.lb-podium-avatar { position: relative; flex-shrink: 0; }
.lb-podium-avatar > span:last-child { display: grid; place-items: center; position: absolute; bottom: -4px; right: -3px; width: 20px; height: 20px; border: 2px solid #f2f5f7; border-radius: 50%; background: var(--lb-silver-soft); color: var(--lb-silver); font-size: var(--lb-font-badge); font-weight: 800; }
.lb-place-1 .lb-podium-avatar > span:last-child { background: var(--lb-gold-soft); color: var(--lb-gold); }
.lb-place-3 .lb-podium-avatar > span:last-child { background: var(--lb-bronze-soft); color: var(--lb-bronze); }
.lb-podium-identity { min-width: 0; }
.lb-podium-identity h3 { font-size: var(--lb-font-title); font-weight: 700; letter-spacing: -.02em; }
.lb-podium-identity p { margin-top: 4px; font-size: var(--lb-font-meta); color: var(--lb-muted); }
.lb-podium-bottom { border-top: 1px solid #d5e1e666; padding-top: 12px; }
.lb-podium-score { display: flex; align-items: baseline; gap: 5px; }
.lb-podium-score strong { font-size: 24px; font-weight: 700; letter-spacing: -.04em; font-variant-numeric: tabular-nums; line-height: 1.2; }
.lb-podium-score strong { color: var(--lb-ink); }
.lb-podium-score > span { font-size: var(--lb-font-meta); color: var(--lb-muted); }
.lb-podium-stats { display: flex; flex-direction: column; gap: 3px; font-size: var(--lb-font-meta); color: var(--lb-muted); }
.lb-podium-stats span { display: flex; align-items: center; gap: 5px; }
.lb-podium-stats span:first-child svg { color: #6a8c7d; fill: none; }
.lb-podium-stats span:last-child svg { color: #958365; fill: none; }
.lb-board { overflow: hidden; }
.lb-board-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 19px 23px; background: #e3ebf0; }
.lb-board-heading p { margin-top: 4px; font-size: var(--lb-font-meta); color: var(--lb-muted); }
.lb-board-heading p > span { margin-inline: 5px; color: #a3b3bd; }
.lb-score-hint { display: flex; align-items: center; gap: 6px; font-size: var(--lb-font-meta); color: var(--lb-muted); }
.lb-table-wrap { width: 100%; overflow-x: auto; }
.lb-table { --lb-list-value: .875rem; --lb-list-meta: .75rem; width: 100%; border-collapse: collapse; text-align: left; font-variant-numeric: tabular-nums; }
.lb-table thead { border-top: 1px solid #cbd9e1; border-bottom: 1px solid #cbd9e1; background: #dce6ec; }
.lb-table th { padding: 12px 16px; color: #3d6176; font-size: var(--lb-list-meta); font-weight: 700; text-transform: uppercase; letter-spacing: .035em; white-space: nowrap; }
.lb-table .lb-rank-col { width: 80px; text-align: center; }
.lb-table .lb-number-col { width: 110px; text-align: right; }
.lb-table .lb-accuracy-col { width: 140px; padding-left: 28px; }
.lb-table .lb-streak-col { width: 150px; text-align: center; }
.lb-table .lb-action-col { width: 54px; }
.lb-table td { padding: 15px 16px; vertical-align: middle; }
.lb-row { --lb-row-bg: var(--lb-soft-surface); --lb-row-hover: var(--lb-soft-hover); border-bottom: 1px solid #cfdee6; background: var(--lb-row-bg); transition: background .15s; }
.lb-row.is-alternate { --lb-row-bg: #e4ecf1; }
.lb-row-rank-1 { --lb-row-bg: #f3ecd5; --lb-row-hover: #eae1c3; box-shadow: inset 3px 0 #c6bda2; }
.lb-row.lb-row-rank-2 { --lb-row-bg: #e1eee3; --lb-row-hover: #d3e4d6; box-shadow: inset 3px 0 #afcbb5; }
.lb-row-rank-3 { --lb-row-bg: #f3e4d8; --lb-row-hover: #ead8c8; box-shadow: inset 3px 0 #cdbbad; }
.lb-row:hover { background: var(--lb-row-hover); }
.lb-row.is-expanded { background: var(--lb-row-hover); }
.lb-row:is(.lb-row-rank-1, .lb-row-rank-2, .lb-row-rank-3) :is(.lb-student-info > p, .lb-points-cell > span, .lb-ac-cell > span, .lb-accuracy-cell > span, .lb-streak-cell small) { color: #3e5968; }
.lb-rank-cell { text-align: center; }
.lb-rank-mark { display: inline-flex; justify-content: center; align-items: center; gap: 2px; min-width: 32px; height: 30px; font-size: 16px; font-weight: 700; color: #607a8b; border-radius: 8px; }
.lb-rank-1 { color: var(--lb-gold); background: var(--lb-gold-soft); }
.lb-rank-2 { color: var(--lb-silver); background: var(--lb-silver-soft); }
.lb-rank-3 { color: var(--lb-bronze); background: var(--lb-bronze-soft); }
.lb-rank-mark svg { fill: none; stroke: currentColor; }
.lb-movement { display: flex; justify-content: center; align-items: center; gap: 1px; margin-top: 2px; font-size: var(--lb-font-meta); font-weight: 600; }
.lb-movement.is-up { color: #368568; }
.lb-movement.is-down { color: #bd6670; }
.lb-movement.is-neutral { color: #a9b9c2; }
.lb-student { display: flex; align-items: center; gap: 11px; }
.lb-student-info { min-width: 0; }
.lb-student-name { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.lb-student-name > button { text-align: left; font-size: var(--lb-list-value); font-weight: 600; line-height: 1.5; color: var(--lb-ink); }
.lb-student-name > button:hover { color: var(--lb-blue); text-decoration: underline; text-underline-offset: 3px; }
.lb-student-info > p { margin-top: 4px; color: var(--lb-muted); font-size: var(--lb-list-meta); }
.lb-skill-badge { display: inline-flex; align-items: center; border-radius: 4px; padding: 1px 5px; background: var(--lb-silver-soft); color: #496777; font-size: var(--lb-font-meta); font-weight: 600; white-space: nowrap; }
.lb-skill-badge.is-gold { background: var(--lb-gold-soft); color: #756642; }
.lb-skill-badge.is-green { background: #e0eae4; color: #557664; }
.lb-points-cell, .lb-ac-cell { text-align: right; white-space: nowrap; }
.lb-points-cell strong { font-size: var(--lb-list-value); letter-spacing: -.025em; font-weight: 700; color: #1e5977; }
.lb-points-cell > span { display: block; color: #5d7889; font-size: var(--lb-list-meta); }
.lb-ac-cell strong { font-size: var(--lb-list-value); font-weight: 600; color: #3b6f5d; }
.lb-ac-cell > span { margin-left: 4px; color: #547666; font-size: var(--lb-list-meta); }
.lb-table td.lb-accuracy-cell { padding-left: 28px; }
.lb-accuracy-cell > span { color: #526d7c; font-size: var(--lb-list-value); font-weight: 600; }
.lb-accuracy-track { height: 4px; width: 82px; max-width: 100%; margin-top: 6px; border-radius: 10px; overflow: hidden; background: #e8eff1; }
.lb-accuracy-track > span { display: block; height: 100%; border-radius: inherit; background: #88aaa0; }
.lb-streak-cell { text-align: center; }
.lb-streak-cell > span { display: inline-flex; align-items: center; gap: 4px; color: #9a793e; font-size: var(--lb-list-value); font-weight: 600; }
.lb-streak-cell svg { color: #958365; fill: none; }
.lb-streak-cell small { font-size: var(--lb-list-meta); font-weight: 400; color: #8c8d80; }
.lb-action-cell > button { display: grid; place-items: center; height: 30px; width: 30px; color: #4c7389; border: 1px solid #bed3df; border-radius: 8px; background: #e5f0f5; }
.lb-action-cell > button:hover { color: var(--lb-blue); background: #e9f3f6; border-color: #bddbe4; }
.lb-action-cell > button[aria-expanded="true"] { background: #e3f0f5; color: var(--lb-blue); }
.lb-action-cell > button[aria-expanded="true"] svg { transform: rotate(180deg); }
.lb-detail-row { background: #e0e9ee; border-bottom: 1px solid #cbd9e1; }
.lb-detail-row[hidden] { display: none; }
.lb-detail-grid { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap: 16px; margin: 0; padding: 1px 12px 3px 64px; }
.lb-detail-grid dt { font-size: var(--lb-font-meta); color: var(--lb-muted); }
.lb-detail-grid dd { margin: 5px 0 0; font-size: var(--lb-font-body); color: var(--lb-ink); font-weight: 600; }
.lb-pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 22px; background: #e1edf2; }
.lb-page-summary { display: flex; align-items: center; gap: 4px; font-size: var(--lb-font-meta); color: var(--lb-muted); }
.lb-page-summary > strong { color: #405f72; font-weight: 600; }
.lb-page-summary select { margin-left: 10px; padding: 5px 6px; border: 1px solid #bed2dd; border-radius: 7px; background: #edf4f7; color: #49697d; font-size: var(--lb-font-meta); }
.lb-page-buttons { display: flex; gap: 5px; }
.lb-page-buttons button { display: grid; place-items: center; width: 32px; height: 32px; border: 1px solid var(--lb-line); border-radius: 8px; color: #698291; font-size: var(--lb-font-meta); font-weight: 600; }
.lb-page-buttons button:hover:not(:disabled) { background: #ecf5f8; border-color: #b7d6e0; }
.lb-page-buttons button[aria-current="page"] { border-color: #b0c5d2; background: #d4e2ea; color: var(--lb-ink); }
.lb-page-buttons button:disabled { opacity: .35; cursor: not-allowed; }
.lb-board-note { display: flex; align-items: center; gap: 7px; padding: 12px 22px; border-top: 1px solid var(--lb-line); background: #d6e5eb; color: #4f7183; font-size: var(--lb-font-meta); }
.lb-board-note > svg { color: #688a9b; flex-shrink: 0; }
.lb-board-note > span { margin-left: auto; white-space: nowrap; color: #607e8d; }
.lb-empty { display: flex; align-items: center; flex-direction: column; gap: 10px; padding: 56px 20px; border-top: 1px solid var(--lb-line); text-align: center; }
.lb-empty > span { display: grid; place-items: center; width: 58px; height: 58px; border-radius: 18px; background: #edf5f8; color: #7ea4b7; margin-bottom: 5px; }
.lb-empty > h3 { font-size: var(--lb-font-title); font-weight: 600; }
.lb-empty > p { max-width: 390px; font-size: var(--lb-font-body); color: var(--lb-muted); }
.lb-empty > button { padding: 9px 16px; margin-top: 6px; background: #eaf4f7; color: var(--lb-blue); border: 1px solid #cfe2ea; border-radius: 8px; font-size: var(--lb-font-body); font-weight: 600; }
@media (min-width: 1400px) { .lb-table td { padding-block: 16px; } .lb-table .lb-number-col { width: 130px; } .lb-table .lb-accuracy-col { width: 160px; } .lb-table .lb-streak-col { width: 180px; } }
@media (max-width: 1100px) { .lb-hero { padding: 24px; } .lb-hero-stats { flex-basis: 400px; } .lb-hero-stats > div { padding-inline: 17px; } .lb-podium-card { padding: 14px; } .lb-podium-identity h3 { font-size: var(--lb-font-item); } .lb-table th, .lb-table td { padding-inline: 12px; } .lb-table .lb-streak-col { width: 115px; } .lb-table .lb-accuracy-col { width: 112px; } .lb-table .lb-accuracy-cell { padding-left: 16px; } }
@media (max-width: 800px) {
  .leaderboard-view { gap: 16px; }
  .lb-hero { gap: 20px; padding: 22px; flex-direction: column; align-items: stretch; }
  .lb-hero h1 { font-size: var(--lb-font-page); }
  .lb-hero-copy > p { font-size: var(--lb-font-body); }
  .lb-hero-stats { flex-basis: auto; padding-top: 17px; padding-bottom: 0; border-top: 1px solid #ffffff26; }
  .lb-hero-stats > div { padding-inline: 16px; }
  .lb-hero-stats > div:first-child { padding-left: 0; border-left: none; }
  .lb-hero-stats svg { display: none; }
  .lb-hero-stats strong { font-size: 24px; }
  .lb-scope-bar { padding: 10px 12px; flex-wrap: wrap; }
  .lb-scopes a { padding: 8px 10px; font-size: var(--lb-font-body); }
  .lb-ranking-help { margin-left: auto; }
  .lb-tools { padding: 12px; gap: 12px; }
  .lb-podium { gap: 9px; }
  .lb-podium-card { padding: 13px 10px; }
  .lb-podium-title { display: none; }
  .lb-podium-person { flex-direction: column; text-align: center; gap: 9px; margin: 13px 0; }
  .lb-podium-identity p { font-size: var(--lb-font-meta); }
  .lb-podium-bottom { flex-direction: column; gap: 5px; }
  .lb-podium-score strong { font-size: 21px; }
  .lb-podium-stats { font-size: var(--lb-font-meta); align-items: center; }
  .lb-board-heading { padding: 17px; }
  .lb-score-hint { display: none; }
  .lb-table, .lb-table tbody { display: block; }
  .lb-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
  .lb-table-wrap { overflow: visible; }
  .lb-row { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 16px 12px; position: relative; padding: 17px; border-top: 1px solid var(--lb-line); border-bottom: 0; }
  .lb-table td { display: block; padding: 0; }
  .lb-table .lb-rank-cell { position: absolute; top: 17px; left: 13px; }
  .lb-rank-mark { min-width: 30px; }
  .lb-movement { margin-top: 0; }
  .lb-student-cell { grid-column: 1 / -1; padding-left: 35px !important; padding-right: 25px !important; }
  .lb-student { gap: 9px; }
  .lb-student .lb-avatar { width: 34px; height: 34px; }
  .lb-student-name { gap: 4px; }
  .lb-points-cell, .lb-ac-cell, .lb-accuracy-cell { text-align: left; border-top: 1px solid #edf2f5; padding-top: 10px !important; }
  .lb-table td.lb-accuracy-cell { padding-left: 0; }
  .lb-table td[data-label]::before { display: block; content: attr(data-label); font-size: var(--lb-font-meta); font-weight: 500; color: var(--lb-muted); margin-bottom: 5px; }
  .lb-points-cell > span { display: inline; margin-left: 4px; }
  .lb-accuracy-track { margin-top: 5px; width: 80px; }
  .lb-table td.lb-streak-cell { display: none; }
  .lb-table td.lb-action-cell { position: absolute; top: 21px; right: 12px; }
  .lb-action-cell > button { width: 27px; height: 27px; }
  .lb-detail-row { display: block; padding: 17px; }
  .lb-detail-grid { grid-template-columns: repeat(2,minmax(0,1fr)); padding: 0; }
  .lb-pagination { padding: 14px 17px; border-top: 1px solid var(--lb-line); flex-wrap: wrap; }
  .lb-board-note { padding: 12px 17px; align-items: flex-start; }
  .lb-board-note > span { display: none; }
}
@media (max-width: 480px) {
  .lb-scope-bar { flex-direction: column; align-items: stretch; gap: 5px; }
  .lb-scopes { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 5px; }
  .lb-ranking-help { margin-left: 0; }
  .lb-ranking-help summary { min-height: 30px; font-size: var(--lb-font-meta); }
  .lb-ranking-help > p { right: auto; left: 0; }
  .lb-tools { align-items: stretch; flex-direction: column; }
  .lb-search { flex-basis: auto; }
  .lb-anonymous { align-self: flex-end; font-size: var(--lb-font-meta); }
  .lb-hero { border-radius: 15px; padding: 20px; }
  .lb-hero-stats { grid-template-columns: repeat(3,minmax(0,1fr)); }
  .lb-hero-stats > div { padding-inline: 10px; }
  .lb-hero-stats strong { font-size: 24px; }
  .lb-hero-stats span { font-size: var(--lb-font-meta); }
  .lb-hero-stats strong small { font-size: var(--lb-font-body); }
  .lb-section-heading > span { font-size: var(--lb-font-meta); }
  .lb-section-heading h2 { font-size: 14px; }
  .lb-podium { grid-template-columns: 1fr; gap: 9px; }
  .lb-podium-card { padding: 12px 15px; display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 8px 12px; }
  .lb-podium-card.lb-place-1 { order: 0; }
  .lb-podium-heading { grid-column: 1/-1; }
  .lb-podium-title { display: inline; font-size: var(--lb-font-badge); }
  .lb-podium-watermark { width: 74px; height: 74px; right: 65px; top: 17px; }
  .lb-podium-person { flex-direction: row; text-align: left; gap: 11px; margin: 0; }
  .lb-avatar-large { width: 39px; height: 39px; }
  .lb-podium-avatar > span:last-child { width: 19px; height: 19px; font-size: var(--lb-font-badge); }
  .lb-podium-identity h3 { font-size: var(--lb-font-item); }
  .lb-podium-identity p { font-size: var(--lb-font-meta); }
  .lb-podium-bottom { border: 0; padding: 0; justify-content: center; align-items: flex-end; }
  .lb-podium-score { gap: 3px; }
  .lb-podium-score strong { font-size: 19px; }
  .lb-podium-score > span { font-size: var(--lb-font-meta); }
  .lb-podium-stats > span:last-child { display: none; }
  .lb-page-summary { font-size: var(--lb-font-meta); }
  .lb-page-summary select { margin-left: 5px; }
  .lb-pagination { justify-content: center; }
  .lb-page-summary { flex-basis: 100%; justify-content: center; }
}

/* ── bổ sung cho bản Blade ── */
.leaderboard-view svg { flex-shrink: 0; }
.lb-scope-bar + .lb-subbar, .lb-subbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 18px; padding: 10px 18px; border-bottom: 1px solid var(--lb-line); background: #e4f0f2; }
.lb-subbar label { display: flex; align-items: center; gap: 8px; font-size: var(--lb-font-meta); color: var(--lb-muted); font-weight: 600; }
.lb-subbar select { max-width: 320px; padding: 6px 8px; border: 1px solid #bed2dd; border-radius: 8px; background: #edf4f7; color: #34586e; font-size: var(--lb-font-body); }
.lb-anonymous.is-locked { cursor: not-allowed; opacity: .85; }
.lb-you { display: inline-flex; align-items: center; border: 1px solid #a8cfdc; border-radius: 4px; padding: 1px 5px; background: #d9edf3; color: #1b6a86; font-size: var(--lb-font-meta); font-weight: 700; }
.lb-row.is-you { box-shadow: inset 3px 0 #2a8aa8; }
.lb-group { display: table-row-group; }
.lb-empty > a { padding: 9px 16px; margin-top: 6px; background: #eaf4f7; color: var(--lb-blue); border: 1px solid #cfe2ea; border-radius: 8px; font-size: var(--lb-font-body); font-weight: 600; text-decoration: none; }
.lb-empty > a:hover { background: #dff0f5; }
.lb-you-pill { color: #1b6a86; font-weight: 600; }
.lb-board-heading p > b.lb-count { font-weight: 400; }
.lb-hero-copy { min-width: 0; }
.lb-page-buttons { flex-wrap: wrap; justify-content: flex-end; }
@media (max-width: 800px) { .lb-group { display: block; } .lb-subbar { padding: 10px 12px; } .lb-subbar select { max-width: 100%; } }

/* ── SỬA 5/11 (khách: "kích độ cao dòng nhỏ xuống cho gọn, màu tươi lên") — chỉ ghi đè CSS, không đổi cấu trúc / logic ── */
body:has(.leaderboard-view) { background-color: #eef5fa; }
.leaderboard-view { --lb-ink: #0f3554; --lb-muted: #5b7488; --lb-line: #d9e7f0; --lb-blue: #0b7fb0; --lb-soft-surface: #ffffff; --lb-soft-hover: #eef7fc;
  --lb-gold: #b7791f; --lb-gold-soft: #ffe9a8; --lb-silver: #3f6a8a; --lb-silver-soft: #dcebf7; --lb-bronze: #c0622b; --lb-bronze-soft: #ffdcc4; }
.lb-board-heading { background: #fff; padding-block: 14px; }
.lb-table thead { background: #f1f7fb; border-color: #dbe8f0; }
.lb-table th { color: #3b6683; }
@media (min-width: 801px) { .lb-table th { padding-block: 9px; } .lb-table td { padding-block: 7px; } }
@media (min-width: 1400px) { .lb-table td { padding-block: 8px; } }
.lb-row { border-bottom: 1px solid #e3eef5; }
.lb-row.is-alternate { --lb-row-bg: #f7fbfe; }
.lb-row-rank-1 { --lb-row-bg: #fff6d6; --lb-row-hover: #ffefbd; box-shadow: inset 4px 0 #f5b301; }
.lb-row.lb-row-rank-2 { --lb-row-bg: #e8f8ee; --lb-row-hover: #d7f2e2; box-shadow: inset 4px 0 #22b36b; }
.lb-row-rank-3 { --lb-row-bg: #fff0e5; --lb-row-hover: #ffe2cf; box-shadow: inset 4px 0 #f08a4b; }
.lb-row:is(.lb-row-rank-1, .lb-row-rank-2, .lb-row-rank-3) :is(.lb-student-info > p, .lb-points-cell > span, .lb-ac-cell > span, .lb-accuracy-cell > span, .lb-streak-cell small) { color: #4a6478; }
.lb-rank-mark { height: 26px; min-width: 30px; font-size: 15px; color: #47677e; }
.lb-movement { margin-top: 0; line-height: 1.2; }
.lb-movement.is-up { color: #16a34a; } .lb-movement.is-down { color: #e11d48; }
@media (min-width: 801px) { .lb-table .lb-avatar { width: 32px; height: 32px; } }
.lb-avatar-tone-0 { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
.lb-avatar-tone-1 { background: #e0ecff; color: #2563eb; border-color: #c7dbff; }
.lb-avatar-tone-2 { background: #ffedd5; color: #c2410c; border-color: #fed7aa; }
.lb-student { gap: 9px; }
.lb-student-name > button { line-height: 1.35; }
.lb-student-info > p { margin-top: 1px; }
.lb-skill-badge { background: #e0f2fe; color: #0369a1; }
.lb-skill-badge.is-gold { background: #fef3c7; color: #b45309; }
.lb-skill-badge.is-green { background: #dcfce7; color: #15803d; }
.lb-points-cell strong { color: #0b7fb0; }
.lb-points-cell > span { display: inline; margin-left: 4px; }
.lb-ac-cell strong { color: #15803d; }
.lb-accuracy-cell > span { color: #0f3554; }
.lb-accuracy-track { height: 5px; margin-top: 3px; background: #e3edf3; }
.lb-accuracy-track > span { background: linear-gradient(90deg, #22c55e, #10b981); }
.lb-streak-cell > span { color: #ea7a0c; }
.lb-streak-cell svg { color: #f97316; }
.lb-action-cell > button { height: 26px; width: 26px; background: #f0f8fc; border-color: #cfe4ef; color: #0b7fb0; }
.lb-detail-row { background: #f3f9fd; }
.lb-pagination { background: #fff; padding-block: 12px; }
.lb-page-buttons button[aria-current="page"] { background: #0b7fb0; border-color: #0b7fb0; color: #fff; }
@media (max-width: 800px) { .lb-row { padding: 12px 14px; gap: 10px 12px; } }

/* ══════ SỬA 6/11 (khách gửi mẫu: "chỉnh lại bảng xếp hạng UI như này cho đẹp") — Gương mặt dẫn đầu (lbx-*) + bảng thứ hạng
   kiểu mới: cột Danh hiệu, dòng phụ "📍 Tỉnh • N bài đã thử", phân trang góc phải. Chỉ đổi giao diện; dữ liệu / tìm kiếm /
   phân trang giữ nguyên (Alpine onthiLeaderboardPage). ══════ */
.lbx-podium-wrap { position: relative; padding: 18px 20px 26px; border-radius: 22px; border: 1px solid #f1e3b8;
  background: linear-gradient(100deg, #fff1c7 0%, #fff8e3 38%, #f3f6ff 68%, #dfeaff 100%); box-shadow: 0 8px 26px rgba(15, 53, 84, .06); }
.lbx-podium-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.lbx-podium-ic { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 10px; background: rgba(255, 255, 255, .85); border: 1px solid #ead9a8; color: #b7791f; }
.lbx-podium-ic svg { width: 18px; height: 18px; fill: none; }
.lbx-podium-head h2 { font-size: 20px !important; font-weight: 800 !important; color: #0f3554; letter-spacing: -.02em; }
.lbx-podium-scope { margin-left: auto; padding: 4px 10px; border-radius: 999px; background: rgba(255, 255, 255, .75); font-size: 11.5px; font-weight: 700; color: #6b7f8f; }
.lbx-podium { display: grid; grid-template-columns: 1fr 1.12fr 1fr; gap: 22px; align-items: end; padding-top: 16px; }
.lbx-card { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 22px 20px 16px; border-radius: 20px;
  background: rgba(255, 255, 255, .62); border: 1px solid rgba(255, 255, 255, .9); box-shadow: 0 12px 30px rgba(15, 53, 84, .10); backdrop-filter: blur(6px); }
.lbx-card.is-r1 { padding: 30px 24px 18px; transform: translateY(-14px); background: linear-gradient(180deg, rgba(255, 250, 232, .95), rgba(255, 255, 255, .9));
  border-color: #f3dc94; box-shadow: 0 18px 40px rgba(183, 121, 31, .18); z-index: 1; }
.lbx-medal { position: absolute; left: 22px; top: -6px; width: 52px; height: 78px; filter: drop-shadow(0 4px 6px rgba(0, 0, 0, .15)); }
.lbx-ava-wrap { position: relative; width: 84px; height: 84px; margin-bottom: 12px; }
.lbx-card.is-r1 .lbx-ava-wrap { width: 100px; height: 100px; margin-top: 18px; }
.lbx-crown { position: absolute; left: 50%; top: -40px; width: 52px; height: 34px; transform: translateX(-50%); filter: drop-shadow(0 3px 4px rgba(183, 121, 31, .35)); }
.lbx-ava { width: 100% !important; height: 100% !important; border: 4px solid #d1d5db !important; box-shadow: 0 6px 16px rgba(15, 53, 84, .15); font-size: 22px !important; }
.lbx-card.is-r1 .lbx-ava { border-color: #f5c542 !important; }
.lbx-card.is-r3 .lbx-ava { border-color: #e7a06b !important; }
.lbx-ava-rank { position: absolute; right: -2px; bottom: -2px; display: grid; place-items: center; width: 26px; height: 26px; border-radius: 50%; border: 2px solid #fff;
  background: #94a3b8; color: #fff; font-size: 12.5px; font-weight: 800; box-shadow: 0 2px 6px rgba(0, 0, 0, .15); }
.lbx-card.is-r1 .lbx-ava-rank { background: #f5b301; color: #5b3a00; }
.lbx-card.is-r3 .lbx-ava-rank { background: #e07b39; }
.lbx-card h3 { font-size: 18px !important; font-weight: 800; color: #0f3554; line-height: 1.3; }
.lbx-card.is-r1 h3 { font-size: 21px !important; }
.lbx-sub { margin-top: 2px !important; font-size: 13px; color: #6b7f8f; }
.lbx-card .lbx-title-chip { margin-top: 6px; }
.lbx-card-foot { align-self: stretch; display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(15, 53, 84, .10); text-align: left; }
.lbx-score strong { font-size: 26px; font-weight: 800; letter-spacing: -.03em; color: #0f3554; font-variant-numeric: tabular-nums; }
.lbx-card.is-r1 .lbx-score strong { font-size: 30px; color: #b7791f; }
.lbx-score span { margin-left: 4px; font-size: 13px; color: #5b7488; }
.lbx-stats { display: grid; gap: 3px; font-size: 12px; color: #5b7488; }
.lbx-stats span { display: flex; align-items: center; gap: 5px; }
.lbx-stats svg { width: 13px; height: 13px; fill: none; }
.lbx-tried { font-size: 14px; color: #3b5568; padding-right: 64px; }
.lbx-trophy { position: absolute; right: 10px; bottom: 34px; width: 88px; height: 88px; filter: drop-shadow(0 6px 10px rgba(183, 121, 31, .28)); pointer-events: none; }

/* Danh hiệu */
.lbx-title-chip { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 7px; font-size: 11.5px; font-weight: 800; letter-spacing: .04em; white-space: nowrap; }
.lbx-t-legend { background: linear-gradient(90deg, #fde68a, #fbbf24); color: #7c4a03; }
.lbx-t-master { background: linear-gradient(90deg, #ede9fe, #ddd6fe); color: #6d28d9; }
.lbx-t-expert { background: linear-gradient(90deg, #ffe4e6, #fde9d1); color: #c2410c; }
.lbx-t-pro { background: linear-gradient(90deg, #dcfce7, #d1fae5); color: #047857; }
.lbx-t-pupil { background: #e0f2fe; color: #0369a1; }
.lbx-t-new { background: #f1f5f9; color: #64748b; }

/* Bảng thứ hạng */
.lb-board { background: #fff; border-color: #e3ecf3; border-radius: 18px; }
.lbx-board-heading { align-items: flex-end; padding: 18px 20px 14px !important; }
.lbx-board-heading h2 { font-size: 22px !important; font-weight: 800 !important; text-transform: uppercase; letter-spacing: .01em !important; color: #0f3554; }
.lbx-board-sub { margin-top: 4px !important; padding-left: 20px; font-size: 15px !important; color: #5b7488 !important; }
.lbx-board-meta { font-size: 12px; color: #6b7f8f; text-align: right; }
.lbx-board-meta > span { margin-inline: 5px; color: #b6c4ce; }
.lb-table thead { background: #f7fafc; border-top: 1px solid #e6eef4; border-bottom: 1px solid #e6eef4; }
.lb-table th { font-size: 11.5px; letter-spacing: .03em; color: #4b6578; }
.lb-table .lbx-title-col { width: 130px; }
.lb-row, .lb-row.is-alternate { --lb-row-bg: #fff; --lb-row-hover: #f4f9fd; }
.lb-row.is-alternate { --lb-row-bg: #fafcfe; }
.lb-row-rank-1, .lb-row.lb-row-rank-2, .lb-row-rank-3 { --lb-row-bg: #fff; --lb-row-hover: #f4f9fd; box-shadow: none; }
.lb-row.is-you { box-shadow: inset 3px 0 #0b7fb0; --lb-row-bg: #f0f8fd; }
.lb-row { border-bottom: 1px solid #eef3f7; }
/* SỬA 6/11 (khách: "dãn dòng ra tí nữa") — chỉ màn rộng; điện thoại giữ dạng thẻ. */
@media (min-width: 801px) { .lb-table td { padding-block: 12px; } .lb-table th { padding-block: 12px; } }
@media (min-width: 1400px) { .lb-table td { padding-block: 13px; } }
.lb-student-info > p.lbx-sub-line { margin-top: 3px !important; }
.lb-movement { margin-top: 2px; }
.lb-rank-mark { height: 24px; min-width: 32px; padding: 0 6px; font-size: 14.5px; font-weight: 800; color: #0f3554; background: transparent; }
.lb-rank-1 { background: #fff3c4; color: #a16207; border: 1px solid #f5d77a; }
.lb-rank-2 { background: #eef2f6; color: #475569; border: 1px solid #d5dde6; }
.lb-rank-3 { background: #ffe8d6; color: #b45309; border: 1px solid #f6c8a3; }
.lb-student-name > button { font-weight: 700; color: #0f3554; }
.lbx-sub-line { display: flex; align-items: center; gap: 4px; margin-top: 1px !important; font-size: 12px !important; color: #6b7f8f !important; }
.lbx-sub-line svg { width: 12px; height: 12px; flex: none; fill: none; color: #94a3b8; }
.lb-points-cell strong { color: #1d6fa5; }
.lb-ac-cell strong { color: #15803d; } .lb-ac-cell > span { color: #15803d; font-weight: 600; }
.lb-accuracy-track { width: 74px; height: 5px; background: #e8eef3; }
.lb-accuracy-track > span { background: linear-gradient(90deg, #34d399, #10b981); }
.lb-streak-cell > span { color: #ea7a0c; }
.lb-streak-cell small { color: #ea7a0c !important; }
.lb-action-cell > button { background: #f4f8fb; border-color: #dbe6ee; color: #5b7488; }

/* Phân trang góc phải + ghi chú cuối bảng */
.lb-pagination { flex-direction: column; align-items: flex-end; gap: 10px; padding: 14px 20px; background: #fff; border-top: 1px solid #eef3f7; }
.lb-page-summary { font-size: 13px; color: #3b5568; }
.lb-page-summary select { margin-left: 12px; padding: 6px 10px; border-radius: 9px; border-color: #d5e1ea; background: #fff; font-size: 12.5px; color: #3b5568; }
.lb-page-buttons { gap: 6px; }
.lb-page-buttons button { width: 34px; height: 34px; border-radius: 9px; border-color: #dbe6ee; background: #fff; color: #3b5568; font-size: 13px; }
.lb-page-buttons button[aria-current="page"] { background: #126f91 !important; border-color: #126f91 !important; color: #fff !important; box-shadow: 0 4px 10px rgba(18, 111, 145, .25); }
.lb-board-note { background: #fff; border-top: 1px solid #eef3f7; }
.lb-board-note p, .lb-board-note > span { font-size: 13px; color: #5b7488; }

@media (max-width: 1100px) { .lbx-podium { gap: 14px; } .lbx-trophy { width: 64px; height: 64px; } .lbx-tried { padding-right: 44px; } .lb-table .lbx-title-col { width: 110px; } }
@media (max-width: 800px) {
  .lbx-podium-wrap { padding: 14px 12px 18px; }
  .lbx-podium-scope { display: none; }
  .lbx-podium { grid-template-columns: 1fr 1fr; gap: 10px; padding-top: 6px; }
  .lbx-card.is-r1 { order: -1; grid-column: 1 / -1; transform: none; }
  .lbx-card:not(.is-r1) { padding: 16px 10px 12px; }
  .lbx-card:not(.is-r1) .lbx-ava-wrap { width: 62px; height: 62px; }
  .lbx-card:not(.is-r1) h3 { font-size: 14.5px !important; }
  .lbx-card:not(.is-r1) .lbx-card-foot { flex-direction: column; align-items: center; gap: 4px; text-align: center; }
  .lbx-card:not(.is-r1) .lbx-score strong { font-size: 20px; }
  .lbx-medal { left: 8px; width: 34px; height: 52px; }
  .lb-table td.lb-student-cell { padding-left: 54px !important; }
  .lbx-board-heading { flex-direction: column; align-items: flex-start; }
  .lbx-board-sub { padding-left: 0; }
  .lbx-board-meta { text-align: left; }
  .lb-table td.lbx-title-cell { display: none; }
  .lb-pagination { align-items: stretch; }
}


/* SỬA 6/11 (khách: "cho full ra bằng khung") — bỏ giới hạn 1440px, lề bằng thanh menu (nav-public: px-3 / sm:px-5 / lg:px-4 / 2xl:px-8). */
.lbx-page { width: 100%; margin: 0; padding: 12px 12px 24px; }
@media (min-width: 640px) { .lbx-page { padding: 20px 20px 28px; } }
@media (min-width: 1024px) { .lbx-page { padding: 20px 16px 32px; } }
@media (min-width: 1536px) { .lbx-page { padding: 24px 32px 32px; } }
.lbx-page .leaderboard-view { max-width: none; margin: 0; }
/* ══════ SỬA 6/11 (khách: "chữ to quá, đồng bộ cỡ chữ với trang chủ") — theo thang chữ trang chủ (resources/css/app.css:
   tiêu đề trang 18–20 · tiêu đề khối 16 · tên / tiêu đề thẻ 12–13 · nội dung 12 · phụ 11 · nhãn nhỏ 10). Đặt cuối để ghi đè. ══════ */
.leaderboard-view { --lb-font-page: 1.25rem; --lb-font-section: 1rem; --lb-font-title: .8125rem; --lb-font-item: .8125rem; --lb-font-body: .75rem; --lb-font-meta: .6875rem; --lb-font-badge: .625rem; }
.lb-hero h1 { font-size: 20px; }
.lb-hero-copy > p { font-size: 13px; }
.lb-hero-stats strong { font-size: 20px; }
.lb-scopes a { font-size: 12px; }
.lb-search input { font-size: 12.5px; }
.lbx-podium-head h2 { font-size: 16px !important; }
.lbx-podium-scope { font-size: 10.5px; }
.lbx-podium-ic { width: 30px; height: 30px; } .lbx-podium-ic svg { width: 16px; height: 16px; }
.lbx-card h3 { font-size: 14px !important; }
.lbx-card.is-r1 h3 { font-size: 15px !important; }
.lbx-sub { font-size: 11px; }
.lbx-score strong { font-size: 20px; }
.lbx-card.is-r1 .lbx-score strong { font-size: 22px; }
.lbx-score span { font-size: 11px; }
.lbx-stats { font-size: 11px; }
.lbx-tried { font-size: 12px; }
.lbx-ava-rank { width: 22px; height: 22px; font-size: 11px; }
.lbx-ava { font-size: 18px !important; }
.lbx-title-chip { padding: 2px 8px; font-size: 10px; }
.lbx-board-heading h2 { font-size: 16px !important; }
.lbx-board-sub { font-size: 12px !important; padding-left: 0; }
.lbx-board-meta { font-size: 11px; }
.lb-table { --lb-list-value: .8125rem; --lb-list-meta: .6875rem; }
.lb-table th { font-size: 10.5px; }
.lb-student-name > button { font-size: 13px; }
.lb-skill-badge { font-size: 10px; }
.lbx-sub-line { font-size: 11px !important; }
.lb-rank-mark { font-size: 13px; height: 22px; min-width: 28px; }
.lb-movement { font-size: 10.5px; }
.lb-points-cell strong, .lb-ac-cell strong, .lb-accuracy-cell > span, .lb-streak-cell > span { font-size: 13px; }
.lb-points-cell > span, .lb-ac-cell > span, .lb-streak-cell small { font-size: 11px; }
.lb-page-summary { font-size: 12px; }
.lb-page-summary select { font-size: 11.5px; }
.lb-page-buttons button { width: 30px; height: 30px; font-size: 12px; }
.lb-board-note p, .lb-board-note > span { font-size: 12px; }
.lb-detail-grid dt { font-size: 11px; } .lb-detail-grid dd { font-size: 12px; }
@media (max-width: 800px) { .lbx-card:not(.is-r1) h3 { font-size: 13px !important; } .lbx-card:not(.is-r1) .lbx-score strong { font-size: 17px; } }
/* ══════ SỬA 6/11 (khách: "giống 100% UI mẫu") — đầu trang dạng dải nền vàng → xanh trải hết bề ngang (tiêu đề, thanh lọc,
   Gương mặt dẫn đầu), bỏ banner tối; bảng thứ hạng: tiêu đề nằm ngoài, bảng là 1 thẻ trắng bo góc, phân trang + ghi chú bên dưới. ══════ */
.lbx-page { --lbx-px: 12px; --lbx-pt: 12px; }
@media (min-width: 640px) { .lbx-page { --lbx-px: 20px; --lbx-pt: 20px; } }
@media (min-width: 1024px) { .lbx-page { --lbx-px: 16px; --lbx-pt: 20px; } }
@media (min-width: 1536px) { .lbx-page { --lbx-px: 32px; --lbx-pt: 24px; } }
body:has(.leaderboard-view) { background-color: #f3f6fa; }
.leaderboard-view { gap: 22px; }
.lbx-top { display: grid; gap: 18px; margin: calc(-1 * var(--lbx-pt)) calc(-1 * var(--lbx-px)) 0; padding: calc(var(--lbx-pt) + 8px) var(--lbx-px) 30px;
  background: linear-gradient(100deg, #fff1c4 0%, #fff7df 32%, #f1f4fb 62%, #dbe8ff 100%); }
.lbx-title h1 { font-size: 26px !important; font-weight: 800 !important; letter-spacing: -.02em; color: #0f2f57; line-height: 1.25; }
.lbx-title p { margin-top: 4px !important; font-size: 14px; color: #4b6074; }
.lbx-filter { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 14px; padding: 8px 10px; border-radius: 16px;
  background: rgba(255, 255, 255, .72); border: 1px solid rgba(255, 255, 255, .95); box-shadow: 0 8px 22px rgba(15, 53, 84, .08); backdrop-filter: blur(6px); }
.lbx-scopes { display: flex; flex-wrap: wrap; gap: 4px; }
.lbx-scopes a { padding: 8px 14px !important; border-radius: 10px !important; font-size: 13px !important; color: #34506a !important; }
.lbx-scopes a:hover { background: rgba(18, 111, 145, .08) !important; }
.lbx-scopes a[aria-pressed="true"] { background: #2b6f8a !important; color: #fff !important; box-shadow: 0 4px 10px rgba(18, 111, 145, .25) !important; }
.lbx-filter-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.lbx-search, .lbx-select { position: relative; display: flex; align-items: center; gap: 7px; height: 38px; padding: 0 12px; border: 1px solid #dbe5ee; border-radius: 10px; background: #fff; color: #5b7488; }
.lbx-search svg, .lbx-select svg { width: 15px; height: 15px; flex: none; fill: none; }
.lbx-search input { width: 170px; border: 0; outline: none; background: transparent; font-size: 13px; color: #0f3554; }
.lbx-search input::placeholder { color: #8195a6; }
.lbx-search button { display: grid; place-items: center; width: 20px; height: 20px; border-radius: 6px; }
.lbx-select select { appearance: none; -webkit-appearance: none; border: 0; outline: none; background: transparent !important; padding-right: 18px; font-size: 13px; color: #34506a; cursor: pointer; max-width: 170px; }
.lbx-select::after { content: ''; position: absolute; right: 12px; top: 50%; width: 7px; height: 7px; border-right: 1.5px solid #6b7f8f; border-bottom: 1.5px solid #6b7f8f; transform: translateY(-70%) rotate(45deg); pointer-events: none; }
.lbx-top .lb-subbar { border: 0; border-radius: 12px; background: rgba(255, 255, 255, .6); }
.lbx-top .lbx-podium-wrap { padding: 0; border: 0; background: none; box-shadow: none; }
.lbx-top .lbx-podium-head { margin-bottom: 4px; }
.lbx-top .lbx-podium-head h2 { font-size: 20px !important; }
.lbx-top .lbx-podium-scope { display: none; }

.lb-board { background: transparent !important; border: 0 !important; box-shadow: none !important; overflow: visible; }
.lbx-board-heading { padding: 0 0 12px !important; background: transparent !important; }
.lbx-board-heading h2 { font-size: 22px !important; color: #0f2f57; }
.lbx-board-sub { margin-top: 2px !important; padding-left: 22px !important; font-size: 15px !important; color: #4b6074 !important; }
.lbx-board-meta { display: none; }
.lb-board .lb-table-wrap { border: 1px solid #e3ebf2; border-radius: 16px; background: #fff; box-shadow: 0 6px 18px rgba(15, 53, 84, .05); overflow: hidden; }
.lb-pagination { padding: 14px 0 0 !important; background: transparent !important; border-top: 0 !important; }
.lb-page-summary { font-size: 13px !important; color: #2c4458; }
.lb-board-note { padding: 18px 0 0 !important; background: transparent !important; border-top: 0 !important; }
.lb-board-note > svg { display: none; }
.lb-board-note p, .lb-board-note > span { font-size: 13px !important; color: #3d5466 !important; }

@media (max-width: 800px) {
  .lbx-top { padding-bottom: 20px; }
  .lbx-title h1 { font-size: 22px !important; }
  .lbx-filter { flex-direction: column; align-items: stretch; }
  .lbx-filter-tools { display: grid; grid-template-columns: 1fr 1fr; }
  .lbx-search { grid-column: 1 / -1; } .lbx-search input { width: 100%; }
  .lbx-select select { max-width: none; width: 100%; }
  .lbx-board-heading h2 { font-size: 18px !important; }
  .lbx-board-sub { padding-left: 0 !important; font-size: 13px !important; }
  .lb-board .lb-table-wrap { border-radius: 14px; }
  .lb-board-note > span { display: none; }
}

/* SỬA 6/11 (khách: "chữ Bảng xếp hạng, Thứ hạng học sinh, Gương mặt dẫn đầu to quá") — tiêu đề theo thang trang chủ. */
.lbx-title h1 { font-size: 20px !important; }
.lbx-title p { font-size: 13px; }
.lbx-top .lbx-podium-head h2 { font-size: 16px !important; }
.lbx-top .lbx-podium-ic { width: 28px; height: 28px; } .lbx-top .lbx-podium-ic svg { width: 15px; height: 15px; }
.lbx-board-heading h2 { font-size: 16px !important; }
.lbx-board-sub { padding-left: 0 !important; font-size: 12px !important; }
@media (max-width: 800px) { .lbx-title h1 { font-size: 18px !important; } .lbx-board-heading h2 { font-size: 15px !important; } }
/* ══════ SỬA 6/11 (khách lần 2: "chữ vẫn to, giảm nữa") — thu nhỏ đồng loạt ~10–15% cả trang (đặt cuối để ghi đè). ══════ */
.lbx-title h1 { font-size: 17px !important; }
.lbx-title p { font-size: 12px !important; margin-top: 2px !important; }
.lbx-top { gap: 14px; padding-bottom: 24px; }
.lbx-scopes a { padding: 7px 12px !important; font-size: 12px !important; }
.lbx-search, .lbx-select { height: 34px; padding: 0 10px; }
.lbx-search input, .lbx-select select { font-size: 12px; }
.lbx-search svg, .lbx-select svg { width: 14px; height: 14px; }
.lbx-top .lbx-podium-head h2 { font-size: 14px !important; }
.lbx-top .lbx-podium-ic { width: 26px; height: 26px; } .lbx-top .lbx-podium-ic svg { width: 14px; height: 14px; }
.lbx-card { padding: 18px 16px 14px; }
.lbx-card.is-r1 { padding: 24px 20px 14px; }
.lbx-ava-wrap { width: 72px; height: 72px; margin-bottom: 10px; }
.lbx-card.is-r1 .lbx-ava-wrap { width: 86px; height: 86px; margin-top: 14px; }
.lbx-crown { top: -34px; width: 44px; height: 29px; }
.lbx-medal { width: 44px; height: 66px; }
.lbx-ava { font-size: 16px !important; border-width: 3px !important; }
.lbx-ava-rank { width: 20px; height: 20px; font-size: 10.5px; }
.lbx-card h3 { font-size: 13px !important; }
.lbx-card.is-r1 h3 { font-size: 14px !important; }
.lbx-sub { font-size: 11px; }
.lbx-card-foot { margin-top: 10px; padding-top: 10px; }
.lbx-score strong { font-size: 18px; }
.lbx-card.is-r1 .lbx-score strong { font-size: 19px; }
.lbx-score span { font-size: 10.5px; }
.lbx-stats { font-size: 10.5px; }
.lbx-tried { font-size: 11.5px; }
.lbx-trophy { width: 72px; height: 72px; bottom: 28px; }
.lbx-title-chip { font-size: 9.5px; padding: 2px 7px; }
.lbx-board-heading { padding-bottom: 10px !important; }
.lbx-board-heading h2 { font-size: 14px !important; }
.lbx-board-sub { font-size: 11px !important; }
.lb-table th { font-size: 10px; }
.lb-student-name > button { font-size: 12px; }
.lb-skill-badge { font-size: 9.5px; }
.lbx-sub-line { font-size: 10.5px !important; }
.lb-table .lb-avatar { width: 30px !important; height: 30px !important; }
.lb-rank-mark { font-size: 12px; height: 21px; min-width: 26px; }
.lb-movement { font-size: 10px; }
.lb-points-cell strong, .lb-ac-cell strong, .lb-accuracy-cell > span, .lb-streak-cell > span { font-size: 12px; }
.lb-points-cell > span, .lb-ac-cell > span, .lb-streak-cell small { font-size: 10.5px; }
@media (min-width: 801px) { .lb-table td { padding-block: 10px; } .lb-table th { padding-block: 10px; } }
.lb-page-summary { font-size: 11.5px !important; }
.lb-page-summary select { font-size: 11px; }
.lb-page-buttons button { width: 28px; height: 28px; font-size: 11.5px; }
.lb-board-note p, .lb-board-note > span { font-size: 11.5px !important; }
@media (max-width: 800px) { .lbx-title h1 { font-size: 16px !important; } .lbx-board-heading h2 { font-size: 14px !important; } .lbx-card:not(.is-r1) .lbx-ava-wrap { width: 56px; height: 56px; } .lbx-card:not(.is-r1) h3 { font-size: 12px !important; } .lbx-card:not(.is-r1) .lbx-score strong { font-size: 16px; } }
</style>

{{-- SỬA 6/11 (khách: "cho full ra") — trải hết bề ngang, lề trái/phải bằng lề thanh menu trên cùng (giống trang chính sách). --}}
<div class="lbx-page">
<div class="leaderboard-view" x-data="onthiLeaderboardPage({{ Js::from($config) }})">

    {{-- ══════ ĐẦU TRANG — SỬA 6/11 (khách: "giống 100% UI mẫu"): dải nền vàng → xanh gồm tiêu đề, thanh lọc (phạm vi · tìm kiếm ·
         Tỉnh/Thành phố · Danh hiệu) và Gương mặt dẫn đầu. Bỏ banner tối + 3 số liệu tổng. Lọc tỉnh / danh hiệu chạy ở trình duyệt. ══════ --}}
    <div class="lbx-top">
        <header class="lbx-title">
            <h1>Bảng xếp hạng</h1>
            <p>Theo dõi thành tích, chinh phục từng thứ hạng</p>
        </header>

        <section class="lbx-filter" aria-label="Bộ lọc bảng xếp hạng">
            <div class="lb-scopes lbx-scopes" role="group" aria-label="Phạm vi bảng xếp hạng">
                @foreach ($scopes as $key => $label)
                    <a href="{{ $scopeUrl($key) }}" aria-pressed="{{ $scope === $key ? 'true' : 'false' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="lbx-filter-tools">
                <label class="lbx-search">
                    <x-lucide name="search" />
                    <input type="text" x-model="query" placeholder="Tìm kiếm học sinh..." aria-label="Tìm kiếm học sinh" autocomplete="off">
                    <button type="button" x-show="query" x-cloak aria-label="Xóa tìm kiếm" @click="query = ''"><x-lucide name="x" /></button>
                </label>
                <label class="lbx-select">
                    <x-lucide name="map-pin" />
                    <select x-model="provinceFilter" aria-label="Lọc theo tỉnh / thành phố">
                        <option value="">Tỉnh/Thành phố</option>
                        <template x-for="v in provinces" :key="v"><option :value="v" x-text="v"></option></template>
                    </select>
                </label>
                <label class="lbx-select">
                    <x-lucide name="trophy" />
                    <select x-model="titleFilter" aria-label="Lọc theo danh hiệu">
                        <option value="">Danh hiệu</option>
                        <template x-for="t in titleOptions" :key="t"><option :value="t" x-text="t"></option></template>
                    </select>
                </label>
            </div>
        </section>

        {{-- Chọn cuộc thi / kỳ thi (phạm vi cuộc thi) --}}
        @if ($scope === 'contest' && $contest && count($contest['boards']) > 0)
            <div class="lb-subbar">
                <label>Cuộc thi
                    <select onchange="location.href=this.value" aria-label="Chọn cuộc thi">
                        @foreach ($contest['boards'] as $b)
                            <option value="{{ route('leaderboard.index', ['scope' => 'contest', 'competition' => $b['id']]) }}" @selected($contest['selectedId'] === $b['id'])>{{ $b['title'] }} ({{ $b['participants'] }})</option>
                        @endforeach
                    </select>
                </label>
                @if (count($contest['examTabs']) > 0)
                    <label>Kỳ thi
                        <select onchange="location.href=this.value" aria-label="Chọn kỳ thi">
                            <option value="{{ route('leaderboard.index', ['scope' => 'contest', 'competition' => $contest['selectedId']]) }}" @selected($contest['selectedExamId'] === null)>Tổng hợp</option>
                            @foreach ($contest['examTabs'] as $t)
                                <option value="{{ route('leaderboard.index', ['scope' => 'contest', 'competition' => $contest['selectedId'], 'exam' => $t['id']]) }}" @selected($contest['selectedExamId'] === $t['id'])>{{ $t['title'] }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </div>
        @endif

        {{-- Chọn lớp (phạm vi lớp, khi có nhiều lớp) --}}
        @if ($scope === 'class' && count($classes) > 1)
            <div class="lb-subbar">
                <label>Lớp
                    <select onchange="location.href=this.value" aria-label="Chọn lớp">
                        @foreach ($classes as $c)
                            <option value="{{ route('leaderboard.index', ['scope' => 'class', 'class' => $c['id']]) }}" @selected($classId === $c['id'])>{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        @endif


    @if (count($rows) > 0)
        {{-- ══════ GƯƠNG MẶT DẪN ĐẦU ══════
             SỬA 6/11 (khách gửi mẫu) — làm lại: hạng 2 · hạng 1 (nổi, vương miện + cúp) · hạng 3, huy chương bạc / đồng góc trái.
             Dữ liệu giữ nguyên (top 3 của bảng, ẩn khi đang tìm kiếm). --}}
        <section class="lbx-podium-wrap" aria-labelledby="lb-top-title" x-show="showPodium" x-cloak>
            <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
                <defs>
                    <linearGradient id="lbx-gold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fde68a"/><stop offset=".55" stop-color="#f5b301"/><stop offset="1" stop-color="#d97706"/></linearGradient>
                    <linearGradient id="lbx-silver" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f8fafc"/><stop offset=".55" stop-color="#cbd5e1"/><stop offset="1" stop-color="#94a3b8"/></linearGradient>
                    <linearGradient id="lbx-bronze" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fed7aa"/><stop offset=".55" stop-color="#ea8a4a"/><stop offset="1" stop-color="#b45309"/></linearGradient>
                </defs>
            </svg>
            <div class="lbx-podium-head">
                <span class="lbx-podium-ic"><x-lucide name="trophy" /></span>
                <h2 id="lb-top-title">Gương mặt dẫn đầu</h2>
                <span class="lbx-podium-scope">Top 3 · {{ $scopeLabel }}</span>
            </div>
            <div class="lbx-podium">
                <template x-for="p in podiumOrdered" :key="p.rank">
                    <article class="lbx-card" :class="'is-r' + p.rank">
                        <svg class="lbx-medal" viewBox="0 0 56 84" x-show="p.rank !== 1" aria-hidden="true">
                            <polygon points="9,0 25,0 34,40 19,46" fill="#60a5fa"/>
                            <polygon points="47,0 31,0 22,40 37,46" fill="#2563eb"/>
                            <circle cx="28" cy="58" r="22" :fill="p.rank === 2 ? 'url(#lbx-silver)' : 'url(#lbx-bronze)'" stroke="rgba(0,0,0,.08)"/>
                            <circle cx="28" cy="58" r="16" fill="none" stroke="rgba(255,255,255,.75)" stroke-width="2"/>
                            <text x="28" y="65" text-anchor="middle" font-size="19" font-weight="800" :fill="p.rank === 2 ? '#64748b' : '#fff'" x-text="p.rank"></text>
                        </svg>
                        <div class="lbx-ava-wrap">
                            <svg class="lbx-crown" viewBox="0 0 64 42" x-show="p.rank === 1" aria-hidden="true">
                                <path d="M6 36 L3 9 L19 22 L32 3 L45 22 L61 9 L58 36 Z" fill="url(#lbx-gold)" stroke="#d97706" stroke-width="1.5" stroke-linejoin="round"/>
                                <rect x="6" y="35" width="52" height="6" rx="2" fill="#d97706"/>
                                <circle cx="32" cy="4" r="3" fill="#fde68a"/><circle cx="3.5" cy="9" r="2.6" fill="#fde68a"/><circle cx="60.5" cy="9" r="2.6" fill="#fde68a"/>
                            </svg>
                            <span class="lb-avatar lbx-ava" :class="avatarClass(p)" aria-hidden="true">
                                <x-lucide name="user-round" x-show="isAnon(p)" />
                                <img x-show="p.avatar" :src="p.avatar" alt="" loading="lazy">
                                <span x-show="!isAnon(p) && !p.avatar" x-text="p.initials"></span>
                            </span>
                            <span class="lbx-ava-rank" x-text="p.rank"></span>
                        </div>
                        <h3 x-text="display(p)"></h3>
                        <span class="lbx-title-chip" x-show="p.rank === 1" :class="titleClass(p)" x-text="titleVi(p)"></span>
                        <p class="lbx-sub" x-show="p.rank !== 1" x-text="p.sub"></p>
                        <div class="lbx-card-foot">
                            <div class="lbx-score"><strong x-text="fmt(p.score)"></strong><span>điểm</span></div>
                            <div class="lbx-stats" x-show="p.rank !== 1">
                                <span><x-lucide name="clock" /><span x-text="p.ac + ' bài AC'"></span></span>
                                <span><x-lucide name="flame" /><span x-text="p.streak + ' ngày'"></span></span>
                            </div>
                            <div class="lbx-tried" x-show="p.rank === 1" x-text="triedText(p)"></div>
                        </div>
                        <svg class="lbx-trophy" viewBox="0 0 96 96" x-show="p.rank === 1" aria-hidden="true">
                            <path d="M25 20 C7 20 8 48 32 48" fill="none" stroke="#f5b301" stroke-width="6" stroke-linecap="round"/>
                            <path d="M71 20 C89 20 88 48 64 48" fill="none" stroke="#d99a06" stroke-width="6" stroke-linecap="round"/>
                            <path d="M22 12 H74 V30 C74 50 62 60 48 60 C34 60 22 50 22 30 Z" fill="url(#lbx-gold)" stroke="#d97706" stroke-width="1.5"/>
                            <path d="M30 18 V30 C30 40 35 47 41 51" fill="none" stroke="#fff" stroke-opacity=".6" stroke-width="3" stroke-linecap="round"/>
                            <rect x="43" y="59" width="10" height="12" fill="#e9a20a"/>
                            <path d="M31 71 H65 L69 84 H27 Z" fill="url(#lbx-gold)" stroke="#d97706" stroke-width="1.5" stroke-linejoin="round"/>
                            <rect x="25" y="83" width="46" height="6" rx="2" fill="#b45309"/>
                        </svg>
                    </article>
                </template>
            </div>
        </section>
    @endif
    </div>

    {{-- ══════ THỨ HẠNG HỌC SINH ══════ --}}
    <section class="lb-board" aria-labelledby="lb-board-title">
        <div class="lb-board-heading lbx-board-heading">
            <div>
                <h2 id="lb-board-title" title="{{ $rule }}">Thứ hạng học sinh</h2>
                <p class="lbx-board-sub">Cập nhật liên tục, thi đấu sôi nổi</p>
            </div>
            <div>
                <p class="lbx-board-meta" role="status" aria-live="polite">
                    {{ $scopeLabel }}@if ($scope === 'contest' && $contest && $contest['title']) <span>·</span> {{ $contest['title'] }}@endif
                    @if ($scope === 'class' && !empty($className)) <span>·</span> {{ $className }}@endif
                    <span>·</span> <b class="lb-count" x-text="filtered.length + ' học sinh' + (query.trim() ? ' phù hợp' : '')">{{ count($rows) }} học sinh</b>
                    @if ($you)<span>·</span> <b class="lb-you-pill">Bạn: hạng #{{ $you['rank'] }}/{{ $nf($you['total']) }}</b>@endif
                </p>
            </div>
        </div>

        @if (count($rows) > 0)
            <div x-show="filtered.length > 0">
                <div class="lb-table-wrap">
                    <table class="lb-table">
                        <caption class="lb-sr-only">Bảng xếp hạng học sinh theo tổng điểm — {{ $scopeLabel }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="lb-rank-col">Hạng</th>
                                <th scope="col">Học sinh</th>
                                <th scope="col" class="lbx-title-col">Danh hiệu</th>
                                <th scope="col" class="lb-number-col" aria-sort="descending">Tổng điểm</th>
                                <th scope="col" class="lb-number-col">Bài đã giải</th>
                                <th scope="col" class="lb-accuracy-col">Tỷ lệ đúng</th>
                                <th scope="col" class="lb-streak-col">Chuỗi luyện tập</th>
                                <th scope="col" class="lb-action-col"><span class="lb-sr-only">Chi tiết</span></th>
                            </tr>
                        </thead>
                        <template x-for="p in visible" :key="p.rank">
                            <tbody class="lb-group">
                                <tr :class="rowClass(p)">
                                    <td class="lb-rank-cell">
                                        <span class="lb-rank-mark" :class="p.rank <= 3 ? 'lb-rank-' + p.rank : ''">
                                            <x-lucide name="medal" style="width:13px;height:13px" x-show="p.rank <= 3" /><span x-text="p.rank"></span>
                                        </span>
                                        <span class="lb-movement is-up" x-show="p.movement > 0" :aria-label="'Tăng ' + p.movement + ' hạng'"><x-lucide name="arrow-up-right" style="width:12px;height:12px" /><span x-text="p.movement"></span></span>
                                        <span class="lb-movement is-down" x-show="p.movement < 0" :aria-label="'Giảm ' + Math.abs(p.movement) + ' hạng'"><x-lucide name="arrow-down-right" style="width:12px;height:12px" /><span x-text="Math.abs(p.movement)"></span></span>
                                        <span class="lb-movement is-neutral" x-show="!p.movement" aria-label="Giữ nguyên hoặc chưa có dữ liệu biến động"><x-lucide name="minus" style="width:12px;height:12px" /></span>
                                    </td>
                                    <td class="lb-student-cell">
                                        <div class="lb-student">
                                            <span class="lb-avatar" :class="avatarClass(p)" aria-hidden="true">
                                                <x-lucide name="user-round" x-show="isAnon(p)" />
                                                <img x-show="p.avatar" :src="p.avatar" alt="" loading="lazy">
                                                <span x-show="!isAnon(p) && !p.avatar" x-text="p.initials"></span>
                                            </span>
                                            <div class="lb-student-info">
                                                <div class="lb-student-name">
                                                    <button type="button" :aria-expanded="expanded === p.rank" :aria-controls="'lb-detail-' + p.rank" @click="toggle(p.rank)" x-text="display(p)"></button>
                                                    <span class="lb-you" x-show="p.isYou">Bạn</span>
                                                    <span class="lb-skill-badge" :class="badgeClass(p)" x-text="p.badge"></span>
                                                </div>
                                                <p class="lbx-sub-line"><x-lucide name="map-pin" x-show="hasProvince(p)" /><span x-text="subLine(p)"></span></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="lbx-title-cell" data-label="Danh hiệu"><span class="lbx-title-chip" :class="titleClass(p)" x-text="titleVi(p)"></span></td>
                                    <td class="lb-points-cell" data-label="Tổng điểm"><strong x-text="fmt(p.score)"></strong><span>điểm</span></td>
                                    <td class="lb-ac-cell" data-label="Bài đã giải"><strong x-text="p.ac"></strong><span>AC</span></td>
                                    <td class="lb-accuracy-cell" data-label="Tỷ lệ đúng"><span x-text="p.accuracy + '%'"></span><div class="lb-accuracy-track" aria-hidden="true"><span :style="'width:' + p.accuracy + '%'"></span></div></td>
                                    <td class="lb-streak-cell"><span><x-lucide name="flame" style="width:14px;height:14px" /><span x-text="p.streak"></span><small>ngày</small></span></td>
                                    <td class="lb-action-cell">
                                        <button type="button" :aria-label="(expanded === p.rank ? 'Ẩn chi tiết ' : 'Xem chi tiết ') + display(p)" :aria-expanded="expanded === p.rank" :aria-controls="'lb-detail-' + p.rank" @click="toggle(p.rank)"><x-lucide name="chevron-down" style="width:17px;height:17px" /></button>
                                    </td>
                                </tr>
                                <tr :id="'lb-detail-' + p.rank" class="lb-detail-row" x-show="expanded === p.rank" x-cloak>
                                    <td colspan="8">
                                        <dl class="lb-detail-grid">
                                            <template x-for="d in p.details" :key="d[0]"><div><dt x-text="d[0]"></dt><dd x-text="d[1]"></dd></div></template>
                                        </dl>
                                    </td>
                                </tr>
                            </tbody>
                        </template>
                    </table>
                </div>

                <nav class="lb-pagination" aria-label="Phân trang bảng xếp hạng">
                    <div class="lb-page-summary">
                        Hiển thị <strong x-text="(start + 1) + '-' + Math.min(start + pageSize, filtered.length)"></strong> / <span x-text="filtered.length"></span> học sinh
                        <label><select aria-label="Số học sinh mỗi trang" x-model.number="pageSize"><option value="10">Số trang: 10</option><option value="20">Số trang: 20</option><option value="50">Số trang: 50</option></select></label>
                    </div>
                    <div class="lb-page-buttons" x-show="totalPages > 1">
                        <button type="button" aria-label="Trang trước" :disabled="currentPage === 1" @click="goto(currentPage - 1)"><x-lucide name="chevron-left" style="width:16px;height:16px" /></button>
                        <template x-for="n in pageButtons" :key="n">
                            <button type="button" :aria-label="'Trang ' + n" :aria-current="currentPage === n ? 'page' : null" @click="goto(n)" x-text="n"></button>
                        </template>
                        <button type="button" aria-label="Trang sau" :disabled="currentPage === totalPages" @click="goto(currentPage + 1)"><x-lucide name="chevron-right" style="width:16px;height:16px" /></button>
                    </div>
                </nav>
            </div>

            {{-- Tìm không ra --}}
            <div class="lb-empty" x-show="filtered.length === 0" x-cloak>
                <span><x-lucide name="search" style="width:27px;height:27px" /></span>
                <h3>Không tìm thấy học sinh phù hợp</h3>
                <p>Thử tìm theo tên, danh hiệu hoặc thứ hạng khác.</p>
                <button type="button" @click="query = ''">Xóa tìm kiếm</button>
            </div>
        @else
            {{-- Chưa có dữ liệu cho phạm vi này --}}
            <div class="lb-empty">
                <span>
                    @if (in_array($empty, ['guest', 'no-class', 'class-empty'], true))
                        <x-lucide name="school" style="width:27px;height:27px" />
                    @else
                        <x-lucide name="search" style="width:27px;height:27px" />
                    @endif
                </span>
                @switch($empty)
                    @case('guest')
                        <h3>Đăng nhập để xem bảng xếp hạng lớp</h3>
                        <p>Bảng này chỉ dành cho học sinh và giáo viên của lớp. Hãy đăng nhập để xem thứ hạng của bạn trong lớp.</p>
                        <a href="{{ route('login') }}">Đăng nhập</a>
                        @break
                    @case('no-class')
                        <h3>Bạn chưa tham gia lớp nào</h3>
                        <p>Khi bạn được duyệt vào một lớp, bảng xếp hạng riêng của lớp sẽ xuất hiện ở đây.</p>
                        <a href="{{ route('courses.index') }}">Xem các lớp học</a>
                        @break
                    @case('class-empty')
                        <h3>Lớp chưa có bảng xếp hạng</h3>
                        <p>Bảng xếp hạng sẽ xuất hiện khi các bạn trong lớp bắt đầu giải bài.</p>
                        <a href="{{ route('practice.index') }}">Luyện tập ngay</a>
                        @break
                    @case('contest')
                        @if ($contest && $contest['selectedId'])
                            {{-- SỬA 6/11 — đã có cuộc thi nhưng chưa thí sinh nào giải đúng câu nào --}}
                            <h3>Cuộc thi này chưa có thí sinh nào giải đúng</h3>
                            <p>Bảng xếp hạng sẽ hiện ngay khi có bài làm được chấm đúng. Bạn có thể chọn cuộc thi khác ở ô "Cuộc thi" phía trên.</p>
                            <a href="{{ $scopeUrl('all-time') }}">Xem toàn thời gian</a>
                            @break
                        @endif
                        <h3>Chưa có cuộc thi nào có bài làm</h3>
                        <p>Bảng xếp hạng cuộc thi sẽ hiện khi có thí sinh nộp bài. Bạn có thể xem bảng toàn thời gian trong lúc chờ.</p>
                        <a href="{{ $scopeUrl('all-time') }}">Xem toàn thời gian</a>
                        @break
                    @default
                        <h3>Chưa có dữ liệu cho phạm vi này</h3>
                        <p>{{ $scope === 'month' ? 'Tháng này chưa có ai giải bài. Bạn có thể xem bảng toàn thời gian hoặc bắt đầu luyện tập ngay.' : 'Bảng xếp hạng sẽ xuất hiện khi học sinh bắt đầu giải bài.' }}</p>
                        <a href="{{ $scope === 'month' ? $scopeUrl('all-time') : route('practice.index') }}">{{ $scope === 'month' ? 'Xem toàn thời gian' : 'Luyện tập ngay' }}</a>
                @endswitch
            </div>
        @endif

        <div class="lb-board-note">
            <x-lucide name="award" style="width:15px;height:15px" />
            <p>Thành tích đến từ sự bền bỉ. Mỗi bài giải đúng là một bước tiến.</p>
            <span>{{ $updated ? 'Cập nhật lúc '.$updated : '' }}</span>
        </div>
    </section>
</div>
</div>
@endsection

@push('scripts')
    @include('partials.leaderboard-page-script')
@endpush
