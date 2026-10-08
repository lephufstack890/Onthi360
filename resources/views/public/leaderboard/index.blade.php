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
        'pageSize' => 5,
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

</style>

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div class="leaderboard-view" x-data="onthiLeaderboardPage({{ Js::from($config) }})">

    {{-- ══════ HERO ══════ --}}
    <header class="lb-hero">
        <div class="lb-hero-copy">
            <span class="lb-eyebrow"><x-lucide name="trophy" style="width:15px;height:15px" />NỖ LỰC ĐƯỢC GHI NHẬN</span>
            <h1>Bảng xếp hạng</h1>
            <p>Theo dõi thành tích, chinh phục từng thứ hạng.</p>
            <span class="lb-demo-label"><span></span>{{ $updated ? 'Cập nhật lúc '.$updated : 'Chưa có dữ liệu xếp hạng' }}</span>
        </div>
        <div class="lb-hero-stats" aria-label="Tổng quan bảng xếp hạng {{ $scopeLabel }}">
            <div><x-lucide name="users" style="width:18px;height:18px" /><strong>{{ $nf($hero['students']) }}</strong><span>Học sinh</span></div>
            <div><x-lucide name="check-circle-2" style="width:18px;height:18px" /><strong>{{ $nf($hero['solved']) }}</strong><span>Bài AC tích lũy</span></div>
            <div><x-lucide name="flame" style="width:18px;height:18px" /><strong>{{ $nf($hero['bestStreak']) }}<small> ngày</small></strong><span>Chuỗi tốt nhất</span></div>
        </div>
    </header>

    {{-- ══════ PHẠM VI + TÌM KIẾM ══════ --}}
    <section class="lb-workspace" aria-label="Bộ lọc bảng xếp hạng">
        <div class="lb-scope-bar">
            <div class="lb-scopes" role="group" aria-label="Phạm vi bảng xếp hạng">
                @foreach ($scopes as $key => $label)
                    <a href="{{ $scopeUrl($key) }}" aria-pressed="{{ $scope === $key ? 'true' : 'false' }}">{{ $label }}</a>
                @endforeach
            </div>
            <details class="lb-ranking-help">
                <summary><x-lucide name="info" style="width:14px;height:14px" />Cách xếp hạng</summary>
                <p>{{ $rule }}</p>
            </details>
        </div>

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

        <div class="lb-tools">
            <label class="lb-search">
                <x-lucide name="search" style="width:17px;height:17px" />
                <input type="text" x-model="query" placeholder="Tìm học sinh, tỉnh thành hoặc danh hiệu…" aria-label="Tìm học sinh, tỉnh thành hoặc danh hiệu" autocomplete="off">
                <button type="button" x-show="query" x-cloak aria-label="Xóa tìm kiếm" @click="query = ''"><x-lucide name="x" style="width:15px;height:15px" /></button>
            </label>
        </div>
    </section>

    @if (count($rows) > 0)
        {{-- ══════ GƯƠNG MẶT DẪN ĐẦU ══════ --}}
        <section class="lb-podium-section" aria-labelledby="lb-top-title" x-show="showPodium" x-cloak>
            <div class="lb-section-heading">
                <div><span class="lb-section-icon"><x-lucide name="trophy" style="width:17px;height:17px" /></span><h2 id="lb-top-title">Gương mặt dẫn đầu</h2></div>
                <span>Top 3 · {{ $scopeLabel }}</span>
            </div>
            <div class="lb-podium">
                <template x-for="p in podium" :key="p.rank">
                    <article class="lb-podium-card" :class="'lb-place-' + p.rank">
                        <x-lucide name="trophy" class="lb-podium-watermark" stroke-width="1.2" x-show="p.rank === 1" />
                        <x-lucide name="medal" class="lb-podium-watermark" stroke-width="1.2" x-show="p.rank !== 1" />
                        <div class="lb-podium-heading">
                            <span class="lb-place-label">
                                <x-lucide name="crown" style="width:14px;height:14px" x-show="p.rank === 1" />
                                <x-lucide name="medal" style="width:14px;height:14px" x-show="p.rank !== 1" />
                                Hạng <span x-text="p.rank"></span>
                            </span>
                            <span class="lb-podium-title" x-text="p.badge"></span>
                        </div>
                        <div class="lb-podium-person">
                            <div class="lb-podium-avatar">
                                <span class="lb-avatar lb-avatar-large" :class="avatarClass(p)" aria-hidden="true">
                                    <x-lucide name="user-round" x-show="isAnon(p)" />
                                    <span x-show="!isAnon(p)" x-text="p.initials"></span>
                                </span>
                                <span x-text="p.rank"></span>
                            </div>
                            <div class="lb-podium-identity">
                                <h3 x-text="display(p)"></h3>
                                <p x-text="p.sub"></p>
                            </div>
                        </div>
                        <div class="lb-podium-bottom">
                            <div class="lb-podium-score"><strong x-text="fmt(p.score)"></strong><span>điểm</span></div>
                            <div class="lb-podium-stats">
                                <span><x-lucide name="check-circle-2" style="width:13px;height:13px" /><span x-text="p.ac + ' bài AC'"></span></span>
                                <span><x-lucide name="flame" style="width:13px;height:13px" /><span x-text="p.streak + ' ngày'"></span></span>
                            </div>
                        </div>
                    </article>
                </template>
            </div>
        </section>
    @endif

    {{-- ══════ THỨ HẠNG HỌC SINH ══════ --}}
    <section class="lb-board" aria-labelledby="lb-board-title">
        <div class="lb-board-heading">
            <div>
                <h2 id="lb-board-title">Thứ hạng học sinh</h2>
                <p role="status" aria-live="polite">
                    {{ $scopeLabel }}@if ($scope === 'contest' && $contest && $contest['title']) <span>·</span> {{ $contest['title'] }}@endif
                    @if ($scope === 'class' && !empty($className)) <span>·</span> {{ $className }}@endif
                    <span>·</span> <b class="lb-count" x-text="filtered.length + ' học sinh' + (query.trim() ? ' phù hợp' : '')">{{ count($rows) }} học sinh</b>
                    @if ($you)<span>·</span> <b class="lb-you-pill">Bạn: hạng #{{ $you['rank'] }}/{{ $nf($you['total']) }}</b>@endif
                </p>
            </div>
            <span class="lb-score-hint"><x-lucide name="arrow-down-right" style="width:14px;height:14px" />Tổng điểm từ cao xuống thấp</span>
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
                                                <span x-show="!isAnon(p)" x-text="p.initials"></span>
                                            </span>
                                            <div class="lb-student-info">
                                                <div class="lb-student-name">
                                                    <button type="button" :aria-expanded="expanded === p.rank" :aria-controls="'lb-detail-' + p.rank" @click="toggle(p.rank)" x-text="display(p)"></button>
                                                    <span class="lb-you" x-show="p.isYou">Bạn</span>
                                                    <span class="lb-skill-badge" :class="badgeClass(p)" x-text="p.badge"></span>
                                                </div>
                                                <p x-text="p.sub"></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="lb-points-cell" data-label="Tổng điểm"><strong x-text="fmt(p.score)"></strong><span>điểm</span></td>
                                    <td class="lb-ac-cell" data-label="Bài đã giải"><strong x-text="p.ac"></strong><span>AC</span></td>
                                    <td class="lb-accuracy-cell" data-label="Tỷ lệ đúng"><span x-text="p.accuracy + '%'"></span><div class="lb-accuracy-track" aria-hidden="true"><span :style="'width:' + p.accuracy + '%'"></span></div></td>
                                    <td class="lb-streak-cell"><span><x-lucide name="flame" style="width:14px;height:14px" /><span x-text="p.streak"></span><small>ngày</small></span></td>
                                    <td class="lb-action-cell">
                                        <button type="button" :aria-label="(expanded === p.rank ? 'Ẩn chi tiết ' : 'Xem chi tiết ') + display(p)" :aria-expanded="expanded === p.rank" :aria-controls="'lb-detail-' + p.rank" @click="toggle(p.rank)"><x-lucide name="chevron-down" style="width:17px;height:17px" /></button>
                                    </td>
                                </tr>
                                <tr :id="'lb-detail-' + p.rank" class="lb-detail-row" x-show="expanded === p.rank" x-cloak>
                                    <td colspan="7">
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
                        Hiển thị <strong x-text="(start + 1) + '–' + Math.min(start + pageSize, filtered.length)"></strong> / <span x-text="filtered.length"></span> học sinh
                        <label><select aria-label="Số học sinh mỗi trang" x-model.number="pageSize"><option value="5">5 / trang</option><option value="10">10 / trang</option></select></label>
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
                        <h3>Chưa có cuộc thi nào công bố kết quả</h3>
                        <p>Bảng xếp hạng cuộc thi chỉ hiện sau khi ban tổ chức công bố kết quả. Bạn có thể xem bảng toàn thời gian trong lúc chờ.</p>
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
            <span>{{ $updated ? 'Cập nhật '.$updated : '' }}</span>
        </div>
    </section>
</div>
</div>
@endsection

@push('scripts')
    @include('partials.leaderboard-page-script')
@endpush
