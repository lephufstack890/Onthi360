@extends('layouts.guest')

@section('title', 'Nhật ký làm đề · '.$subject['title'])
@section('meta-description', 'Bảng xếp hạng và nhật ký làm đề '.$subject['title'].' trên Ôn Thi 360.')

@section('content')
@include('partials.practice-history-style')
{{-- ═══════════════ NHẬT KÝ LÀM ĐỀ ═══════════════
     SỬA 9/10 (khách: "click vào nhật ký làm đề thì ra trang như hình 3 — UI trong source mới, làm logic
     trang này luôn") — dựng theo education-main/src/components/ExamHistoryPage.jsx + examHistory.css:
     thanh đầu, "Tổng quan kết quả" (4 ô), "Thống kê từng bài", bộ lọc (Hiện các lần nộp / Chỉ bài làm
     của tôi / tìm kiếm), bảng xếp hạng ma trận điểm từng bài, "Xem bài", phân trang, ghi chú.

     LOGIC (chép từ utils/examHistory.js): gom lượt nộp theo người; lượt tốt nhất = lượt đã chấm xong có
     điểm cao nhất (bằng điểm thì lượt nộp sớm hơn); xếp hạng theo lượt tốt nhất, bằng điểm đồng hạng;
     thống kê TB/điểm cao nhất tính trên lượt tốt nhất của mỗi người.

     QUYỀN XEM: bảng điểm công khai cho người đã đăng nhập (tài khoản người khác bị che bớt); chỉ admin,
     giáo viên đã giao đề cho học sinh đó và chính người nộp mới mở được "Xem bài" (máy chủ chỉ gửi nội
     dung bài làm cho những lượt đó). Dữ liệu do Public\PracticeHistoryService::examLogData cấp. --}}
@php
    $isAdmin = $role === 'admin';
    $roleLabel = $isAdmin ? 'Quản trị viên' : 'Nhật ký của bạn';
    $examPayload = [
        'subject' => $subject,
        'rows' => $rows,
        'role' => $role,
        'truncated' => $truncated,
        'myRank' => $myRank ?? null,
    ];
    $metaParts = array_filter([
        $subject['code'],
        count($subject['questions']) ? count($subject['questions']).' bài' : 'Chưa lưu số bài',
        rtrim(rtrim(number_format((float) $subject['maxScore'], 2, ',', '.'), '0'), ',').' điểm',
        $subject['duration'],
    ]);
@endphp

<style>
.exam-history .submission-main { min-width:0; }
.exam-history .submission-context>span { flex-wrap:wrap; }
.exam-history-overview .submission-overview-content { padding:20px; }
.exam-history-overview .submission-stats { width:100%; max-width:none; margin:0; }
.exam-history-overview-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px; }
.exam-history-overview-heading .submission-overview-heading { margin:0; max-width:none; }
.exam-history .submission-overview .submission-stats strong { font-size:20px; white-space:normal; }
.exam-history .submission-overview .submission-stats>div { min-width:0; padding:12px; }
.exam-history-chart { padding:14px 16px 16px; margin-bottom:16px; border:1px solid rgb(190 214 230 / .65); border-radius:14px; background:linear-gradient(115deg,rgb(255 255 255 / .78),rgb(222 239 246 / .52),rgb(237 249 245 / .65)); backdrop-filter:blur(10px); }
.exam-history-chart-heading { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:5px 16px; margin-bottom:14px; }
.exam-history-chart-heading h2 { display:flex; align-items:center; gap:6px; font-size:13px; }
.exam-history-chart-heading p,.exam-history-chart-empty { color:var(--history-muted); font-size:11px; }
.exam-history-chart-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px 28px; }
.exam-history-chart-item { min-width:0; }
.exam-history-chart-label { display:flex; justify-content:space-between; align-items:baseline; gap:6px; margin-bottom:6px; font-size:11px; }
.exam-history-chart-label>span { white-space:nowrap; }
.exam-history-chart-label strong { color:var(--history-action); font-size:12px; font-variant-numeric:tabular-nums; }
.exam-history-chart-label small { font-size:11px; font-weight:500; color:var(--history-muted); }
.exam-history-chart-track { height:7px; overflow:hidden; border-radius:4px; background:rgb(132 169 192 / .18); }
.exam-history-chart-track>span { display:block; height:100%; border-radius:4px; background:linear-gradient(90deg,#3f95b2,#63b5bc); }
.exam-history-switches { display:flex; align-items:center; flex-wrap:wrap; gap:20px; }
.exam-history-switches .submission-mine-toggle { font-size:11px; }
.exam-history-switches .submission-switch-track { flex-shrink:0; }
.exam-history .submission-filter-topline { flex-wrap:wrap; }
.exam-history .submission-filters { align-items:center; }
.exam-history .submission-search { max-width:430px; }
.exam-history-ranking-rule { flex:1; align-self:center; text-align:right; font-size:11px; color:var(--history-muted); }
.exam-ranking-table { min-width:750px; }
.exam-ranking-table .exam-history-rank { width:64px; min-width:64px; text-align:center; font-weight:700; }
.exam-ranking-table .exam-person-column { min-width:190px; max-width:230px; white-space:normal; }
.exam-ranking-table .submission-person strong { display:block; overflow-wrap:anywhere; }
.exam-ranking-table .exam-score-cell { min-height:42px; }
.exam-ranking-table div.exam-score-cell:hover { background:transparent; }
.exam-ranking-table .exam-total-column>small { display:block; }
.exam-ranking-table .exam-total-column>strong { color:var(--history-heading); }
.exam-ranking-table .result-pending>strong { font-size:12px; color:#586ab0; }
.exam-ranking-table tbody tr:nth-child(even) { background:transparent; }
.exam-ranking-table tbody:nth-last-child(2)>tr:last-child td { border-bottom:0; }
.exam-ranking-table tbody>tr:last-child td { border-bottom:1px solid var(--history-divider); }
.exam-ranking-table tbody:nth-last-child(2)>tr:last-child td { border-bottom:0; }
.exam-history-own { background:var(--history-green-soft) !important; }
.exam-history-attempt { background:#f5f9fc !important; }
.exam-ranking-table .exam-history-attempt>td { padding-top:8px; padding-bottom:8px; }
.exam-ranking-table .exam-history-attempt .exam-person-column { padding-left:24px; }
.exam-history-attempt time,.exam-history-attempt-id { display:block; color:var(--history-muted); font-size:11px; }
.exam-history-attempt-id { max-width:175px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:10px; }
.exam-history-attempt .exam-person-column>strong { font-size:11px; }
.exam-history-badge { display:inline-flex; margin-left:6px; border-radius:5px; background:#e2eff5; color:var(--history-action); padding:2px 5px; font-size:10px; font-weight:600; vertical-align:middle; }
.exam-history-expand { display:flex; align-items:center; gap:3px; min-height:30px; margin-top:2px; color:var(--history-action); font-size:11px; }
.exam-history-private { display:inline-flex; gap:5px; align-items:center; color:var(--history-muted); font-size:11px; white-space:nowrap; }
.exam-history .submission-table-hint { display:block; margin:0 0 10px; color:var(--history-muted); font-size:11px; }
.exam-history .submission-storage-note { margin-top:10px; }
/* Ma trận điểm (submissionHistory.css 189–307) */
.submission-history .result-none { --result-color:var(--history-muted); --result-soft:#edf1f4; }
.exam-matrix-scroll { position:relative; }
.exam-matrix { border-collapse:separate; border-spacing:0; }
.exam-matrix th,.exam-matrix td { padding:12px 10px; }
.exam-matrix :is(th,td):not(:last-child) { border-right:1px solid var(--history-border); }
.exam-matrix thead th:not(:last-child) { border-right-color:#BCD0E1; }
.exam-matrix td { font-variant-numeric:tabular-nums; }
.exam-matrix th:first-child,.exam-matrix td:first-child { padding-left:16px; }
.exam-matrix th:last-child,.exam-matrix td:last-child { padding-right:16px; }
.exam-question-column { min-width:70px; text-align:center; }
.exam-question-column>span,.exam-question-column>small { display:block; }
.exam-question-column>small { color:#45657D; font-size:10px; font-weight:500; margin-top:3px; text-transform:none; letter-spacing:normal; }
.exam-score-cell { display:flex; flex-direction:column; justify-content:center; gap:5px; align-items:center; width:100%; min-height:48px; padding:5px 7px; border-radius:8px; color:var(--result-color); background:transparent; transition:background .15s; }
.exam-score-cell:hover { background:var(--result-soft); }
.exam-score-cell strong { font-size:13px; font-weight:700; }
.exam-score-cell span { padding:2px 6px; border-radius:5px; font-size:10px; font-weight:600; line-height:1.4; background:var(--result-soft); }
.exam-matrix .exam-total-column { text-align:center; min-width:94px; }
.exam-total-column>strong { display:block; color:var(--result-color,var(--history-heading)); font-size:15px; }
.exam-total-column small { font-size:10px; color:var(--history-muted); font-weight:400; }
.exam-total-column>span { display:block; margin-top:4px; color:var(--result-color,var(--history-body)); font-size:10px; }
.exam-submission-dialog { padding:0; margin:auto; width:min(1060px,calc(100vw - 32px)); height:min(880px,calc(100dvh - 32px)); max-width:none; max-height:calc(100dvh - 32px); border:1px solid var(--history-border); border-radius:12px; color:var(--history-body); background:#fff; font-family:inherit; font-size:12px; overflow:hidden; box-shadow:0 24px 90px #112e4c40; }
.exam-submission-dialog:not([open]) { display:none; }
.exam-submission-dialog::backdrop { background:#10233388; backdrop-filter:blur(3px); }
.exam-submission-dialog * { box-sizing:border-box; }
.exam-dialog-shell { display:flex; flex-direction:column; height:100%; min-height:0; }
.exam-dialog-header,.exam-dialog-footer { flex-shrink:0; }
.exam-dialog-header { display:flex; align-items:center; gap:20px; padding:18px 20px; background:#DDEEF4; border-top:4px solid var(--history-action); border-bottom:1px solid #B6D1DF; }
.exam-dialog-header>div:first-child { flex:1; min-width:0; }
.exam-dialog-header p { font-size:11px; font-weight:600; color:var(--history-muted); margin-bottom:4px; }
.exam-dialog-header h2 { font-size:16px; line-height:24px; font-weight:700; color:var(--history-heading); }
.exam-dialog-header>div>span { display:block; font-size:11px; margin-top:4px; }
.exam-dialog-total { flex-shrink:0; text-align:right; padding-left:20px; border-left:1px solid #B6D1DF; }
.exam-dialog-total>strong { font-size:22px; color:var(--history-action); }
.exam-dialog-total small { font-size:12px; color:var(--history-muted); font-weight:400; }
.exam-dialog-close { display:grid; place-items:center; flex-shrink:0; width:36px; height:36px; border-radius:8px; border:1px solid #D6E3EF; background:#fff; color:#365B7A; }
.exam-dialog-close:hover { background:var(--history-hover); border-color:#9DC8D7; }
.exam-dialog-layout { display:grid; grid-template-columns:180px minmax(0,1fr); min-height:0; flex:1; overflow:hidden; }
.exam-dialog-questions { padding:12px; background:#EEF4FA; border-right:1px solid #C5D9E6; overflow:auto; }
.exam-dialog-nav-label { margin:2px 0 12px; color:var(--history-heading); font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
.exam-dialog-questions button { display:flex; align-items:center; justify-content:space-between; width:100%; gap:10px; padding:10px 8px; margin-bottom:7px; border-radius:8px; text-align:left; border:1px solid #D6E3EF; background:#fff; }
.exam-dialog-questions button[aria-pressed=true] { border-color:#9DC8D7; background:var(--history-hover); box-shadow:inset 3px 0 var(--history-action); }
.exam-dialog-questions button:hover { background:var(--history-hover); }
.exam-dialog-questions b { font-size:12px; color:var(--history-body); font-weight:600; }
.exam-dialog-questions button>span>small { display:block; color:var(--result-color); font-size:10px; margin-top:3px; }
.exam-dialog-questions button>strong { font-size:12px; color:var(--result-color); }
.exam-dialog-questions strong small { display:block; color:var(--history-muted); font-size:10px; font-weight:400; text-align:right; }
.exam-dialog-content { display:flex; flex:1; flex-direction:column; min-width:0; min-height:0; overflow:hidden; background:#fff; }
.exam-question-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:18px 20px 10px; flex-shrink:0; }
.exam-question-heading p { color:var(--history-muted); font-size:11px; margin-bottom:5px; }
.exam-question-heading h3 { color:var(--history-heading); font-size:13px; font-weight:700; line-height:1.6; }
.exam-question-heading .submission-result-badge { white-space:nowrap; }
.exam-question-meta { display:flex; flex-wrap:wrap; gap:16px; padding:0 20px 16px; font-size:11px; color:var(--history-muted); flex-shrink:0; }
.exam-question-meta>span { display:flex; align-items:center; gap:5px; }
.exam-question-meta strong { color:var(--result-color); font-size:12px; }
.exam-dialog-panel { flex:1; min-height:0; padding:16px 20px; overflow:auto; scrollbar-gutter:stable; overscroll-behavior:contain; background:#F4F8FB; border-top:1px solid #C5D9E6; }
.exam-dialog-panel>.submission-code { background:#fff; border-color:#C5D9E6; max-height:none; white-space:pre-wrap; overflow-wrap:anywhere; }
.exam-answer-label { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:6px; margin-bottom:12px; font-size:12px; color:var(--history-body); }
.exam-answer-label span { color:var(--history-muted); font-size:11px; }
.exam-dialog-empty { padding:25px 0; font-size:12px; color:var(--history-muted); line-height:1.8; }
.exam-dialog-footer { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:12px 20px; border-top:1px solid #C5D9E6; background:#fff; font-size:11px; }
.exam-dialog-footer>div { display:flex; gap:7px; }
@media(max-width:900px) {
  .exam-history-chart-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px 24px; }
  .exam-history-overview .submission-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .exam-history-switches { gap:14px; }
}
@media(max-width:700px) {
  .exam-submission-dialog { width:calc(100vw - 16px); height:calc(100dvh - 16px); max-height:calc(100dvh - 16px); }
  .exam-dialog-header { gap:10px; padding:12px; align-items:flex-start; }
  .exam-dialog-header h2 { font-size:14px; }
  .exam-dialog-header p { font-size:10px; }
  .exam-dialog-total { display:none; }
  .exam-dialog-layout { display:flex; flex-direction:column; }
  .exam-dialog-questions { display:flex; gap:5px; flex-shrink:0; padding:8px; border-right:0; border-bottom:1px solid var(--history-border); }
  .exam-dialog-nav-label { display:none; }
  .exam-dialog-questions button { min-width:102px; width:auto; margin:0; }
  .exam-question-heading { padding:14px 12px 10px; gap:8px; }
  .exam-question-meta { padding-inline:12px; gap:8px; }
  .exam-dialog-panel { padding:14px 12px; }
  .exam-dialog-footer { padding:10px 12px; }
}
@media(max-width:600px) {
  .exam-history-overview .submission-overview-content { padding:14px; }
  .exam-history-overview-heading { flex-wrap:wrap; gap:10px; }
  .exam-history .submission-overview .submission-stats strong { font-size:17px; }
  .exam-history .submission-overview .submission-stats>div { padding:10px; gap:8px; align-items:flex-start; }
  .exam-history .submission-overview .stat-icon { display:none; }
  .exam-history-chart { padding:12px; }
  .exam-history-chart-grid { gap:14px 18px; }
  .exam-history-switches { width:100%; flex-direction:column; align-items:stretch; gap:0; }
  .exam-history-switches .submission-mine-toggle { justify-content:space-between; min-height:44px; font-size:12px; }
  .exam-history .submission-search { min-width:0; max-width:none; flex-basis:100%; }
  .exam-history-ranking-rule { flex-basis:100%; text-align:left; }
  .exam-ranking-table .exam-person-column { position:sticky; left:0; z-index:1; min-width:156px; max-width:170px; background:#fff; box-shadow:2px 0 0 var(--history-border); }
  .exam-ranking-table thead .exam-person-column { background:#deebf5; z-index:2; }
  .exam-history-own .exam-person-column { background:var(--history-green-soft); }
  .exam-history-attempt .exam-person-column { background:#f5f9fc; }
  .exam-ranking-table .exam-history-rank { width:48px; min-width:48px; }
  .exam-ranking-table .exam-history-attempt .exam-person-column { padding-left:15px; }
  .exam-history-attempt-id { max-width:140px; }
}
@media(pointer:coarse) { .exam-history :is(.submission-view,.exam-history-expand,.exam-score-cell,.submission-button) { min-height:44px; } }
</style>

<div class="submission-history exam-history" x-data="onthiExamLog({{ Js::from($examPayload) }})">
    <header class="submission-topbar">
        <div class="submission-topbar-inner">
            <a class="submission-button submission-back" href="{{ $subject['backHref'] }}">
                <x-lucide name="arrow-left" class="h-4 w-4" /><span>Luyện tập</span>
            </a>
            <div class="submission-heading">
                <p>NHẬT KÝ LÀM ĐỀ</p>
                <h1>{{ $subject['title'] }}</h1>
            </div>
            <span class="submission-role"><x-lucide name="shield-check" class="h-4 w-4" />{{ $roleLabel }}</span>
        </div>
    </header>

    <main class="submission-main">
        <div class="submission-context">
            <span><x-lucide name="clipboard-list" class="h-4 w-4" />{{ implode(' · ', $metaParts) }}</span>
        </div>

        <section class="submission-overview exam-history-overview" aria-label="Tổng quan kết quả đề">
            <img class="submission-overview-image" src="{{ asset('assets/hero-practice.jpg') }}" alt="" decoding="async">
            <div class="submission-overview-content">
                <div class="exam-history-overview-heading">
                    <div class="submission-overview-heading">
                        <h2>Tổng quan kết quả</h2>
                        <p>Toàn đề · Tất cả thời gian</p>
                    </div>
                </div>
                <div class="submission-stats">
                    <div><span class="stat-icon"><x-lucide name="users" class="h-4 w-4" /></span><div><strong x-text="stats.people"></strong><p>Người đã nộp</p></div></div>
                    <div><span class="stat-icon"><x-lucide name="clipboard-list" class="h-4 w-4" /></span><div><strong x-text="stats.attempts"></strong><p>Lượt nộp · <span x-text="stats.pending"></span> chờ chấm</p></div></div>
                    <div><span class="stat-icon"><x-lucide name="trophy" class="h-4 w-4" /></span><div><strong><span x-text="fmt(stats.high)"></span> / <span x-text="fmt(maxScore)"></span></strong><p>Điểm cao nhất</p></div></div>
                    <div><span class="stat-icon"><x-lucide name="award" class="h-4 w-4" /></span><div><strong><span x-text="fmt(stats.average)"></span> / <span x-text="fmt(maxScore)"></span></strong><p>TB lượt tốt nhất · <span x-text="stats.ranked"></span> người</p></div></div>
                </div>
            </div>
        </section>

        <section class="exam-history-chart" aria-label="Thống kê từng bài">
            <div class="exam-history-chart-heading">
                <h2><x-lucide name="bar-chart-3" class="h-4 w-4" />Thống kê từng bài</h2>
                <p>Điểm trung bình / tối đa · Lượt tốt nhất của mỗi người</p>
            </div>
            <template x-if="stats.questions.length">
                <div class="exam-history-chart-grid">
                    <template x-for="(q, index) in stats.questions" :key="q.id">
                        <div class="exam-history-chart-item" :title="q.title + ' · ' + q.count + ' người có điểm'">
                            <div class="exam-history-chart-label"><span x-text="'Bài ' + (index + 1)"></span><strong><span x-text="fmt(q.average)"></span><small> / <span x-text="fmt(q.points)"></span></small></strong></div>
                            <div class="exam-history-chart-track" role="img" :aria-label="'Bài ' + (index + 1) + ': trung bình ' + fmt(q.average) + ' trên ' + fmt(q.points) + ' điểm, ' + q.count + ' người'"><span :style="'width:' + barWidth(q) + '%'"></span></div>
                        </div>
                    </template>
                </div>
            </template>
            <p class="exam-history-chart-empty" x-show="!stats.questions.length" x-cloak>Chưa có điểm từng bài.</p>
        </section>

        <section class="submission-filter-panel" aria-label="Bộ lọc xếp hạng đề">
            <div class="submission-filter-topline">
                <h2>Bảng xếp hạng &amp; lượt nộp</h2>
                <div class="exam-history-switches">
                    <button type="button" role="switch" class="submission-mine-toggle" :aria-checked="allExpanded" @click="toggleAll()"><span>Hiện các lần nộp</span><span class="submission-switch-track" aria-hidden="true"><span></span></span></button>
                    @if ($isAdmin)
                    <button type="button" role="switch" class="submission-mine-toggle" :aria-checked="mine" @click="mine = !mine"><span>Chỉ bài làm của tôi</span><span class="submission-switch-track" aria-hidden="true"><span></span></span></button>
                    @endif
                </div>
            </div>
            <div class="submission-filters">
                <label class="submission-search">
                    <x-lucide name="search" class="h-4 w-4" />
                    <input aria-label="Tìm người làm đề" placeholder="Tìm tên, tài khoản hoặc mã lượt nộp…" x-model="query">
                </label>
                <p class="exam-history-ranking-rule">Xếp theo lượt tốt nhất · Điểm bằng nhau đồng hạng{{ $isAdmin ? '' : ' · Hạng tính trên toàn đề' }}</p>
                <button type="button" class="submission-reset" x-show="query || mine" x-cloak @click="query = ''; mine = false"><x-lucide name="x" class="h-3.5 w-3.5" />Xóa bộ lọc</button>
            </div>
        </section>

        <div class="submission-filter-summary" role="status">
            <span>Hiển thị <strong x-text="filtered.length"></strong> / <span x-text="ranking.length"></span> người<span x-show="mine" x-cloak> · Giữ thứ hạng toàn đề</span></span>
            <span>
                Điểm từng bài thuộc cùng một lượt nộp.
                @if ($truncated)
                    Chỉ hiện {{ number_format(count($rows)) }} lượt nộp mới nhất.
                @endif
            </span>
        </div>

        <template x-if="visible.length">
            <div>
                <p class="submission-table-hint">Cuộn ngang để xem đủ điểm từng bài. Mở số lần nộp dưới tên để xem lịch sử điểm.</p>
                <div class="submission-table-scroll exam-matrix-scroll" tabindex="0" role="region" aria-label="Bảng xếp hạng đề, cuộn ngang để xem các bài">
                    <table class="submission-table exam-matrix exam-ranking-table">
                        <caption class="sr-only">Xếp hạng và điểm từng bài của {{ $subject['title'] }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="exam-history-rank">Hạng</th>
                                <th scope="col" class="exam-person-column">Người làm / Lần nộp</th>
                                <template x-for="(q, index) in questions" :key="'h' + q.id">
                                    <th scope="col" class="exam-question-column" :title="q.title"><span x-text="'Bài ' + (index + 1)"></span><small>/<span x-text="fmt(q.points)"></span> điểm</small></th>
                                </template>
                                <th scope="col" class="exam-total-column">Tổng điểm<small>/<span x-text="fmt(maxScore)"></span> điểm</small></th>
                                <th scope="col">Bài làm</th>
                            </tr>
                        </thead>
                        {{-- Mỗi hàng hiển thị (người làm hoặc một lần nộp) nằm trong một <tbody> riêng để x-for chỉ cần một phần tử gốc. --}}
                        <template x-for="it in tableItems" :key="it.key">
                            <tbody :data-participant="it.row.key">
                                <template x-if="it.kind === 'main'">
                                <tr class="submission-row" :class="it.row.attempts.some((a) => a.mine) ? 'exam-history-own' : ''">
                                    <td class="exam-history-rank" x-text="it.row.rank ? '#' + it.row.rank : '—'"></td>
                                    <td class="exam-person-column">
                                        <div class="submission-person">
                                            <strong><span x-text="it.row.latest.submitter || 'Chưa xác định'"></span><span class="exam-history-badge" x-show="it.row.attempts.some((a) => a.mine)" x-cloak>Bạn</span></strong>
                                            <span x-text="it.row.latest.account || 'Chưa xác định tài khoản'"></span>
                                        </div>
                                        <button type="button" class="exam-history-expand" :aria-expanded="isExpanded(it.row)"
                                                :aria-label="(isExpanded(it.row) ? 'Thu gọn ' : 'Hiện ') + shownAttempts(it.row).length + ' lần nộp của ' + it.row.latest.submitter"
                                                @click="toggleRow(it.row)">
                                            <x-lucide name="chevron-right" class="h-3 w-3" x-show="!isExpanded(it.row)" />
                                            <x-lucide name="chevron-down" class="h-3 w-3" x-show="isExpanded(it.row)" x-cloak />
                                            <span x-text="shownAttempts(it.row).length + ' lần nộp'"></span>
                                        </button>
                                    </td>
                                    <template x-for="(q, qi) in questions" :key="'c' + it.row.key + q.id">
                                        <td class="exam-question-column">
                                            <template x-if="(it.row.best || it.row.latest).canOpen">
                                                <button type="button" class="exam-score-cell" :class="'result-' + cellOf(it.row.best || it.row.latest, q.id).status"
                                                        aria-haspopup="dialog"
                                                        :aria-label="'Xem bài ' + (qi + 1) + ' của ' + it.row.latest.submitter + ': ' + statusLabel(cellOf(it.row.best || it.row.latest, q.id).status)"
                                                        @click="openRecord(it.row.best || it.row.latest, q.id)">
                                                    <strong x-text="cellScore(cellOf(it.row.best || it.row.latest, q.id))"></strong><span x-text="statusLabel(cellOf(it.row.best || it.row.latest, q.id).status)"></span>
                                                </button>
                                            </template>
                                            <template x-if="!(it.row.best || it.row.latest).canOpen">
                                                <div class="exam-score-cell" :class="'result-' + cellOf(it.row.best || it.row.latest, q.id).status">
                                                    <strong x-text="cellScore(cellOf(it.row.best || it.row.latest, q.id))"></strong><span x-text="statusLabel(cellOf(it.row.best || it.row.latest, q.id).status)"></span>
                                                </div>
                                            </template>
                                        </td>
                                    </template>
                                    <td class="exam-total-column" :class="hasFinal(it.row.best || it.row.latest) ? 'result-partial' : 'result-pending'">
                                        <strong>
                                            <template x-if="hasFinal(it.row.best || it.row.latest)"><span><span x-text="fmt((it.row.best || it.row.latest).score)"></span><small>/<span x-text="fmt((it.row.best || it.row.latest).maxScore)"></span></small></span></template>
                                            <template x-if="!hasFinal(it.row.best || it.row.latest)"><span>Chờ chấm</span></template>
                                        </strong>
                                        <span x-text="it.row.rank ? 'Tốt nhất' : 'Chưa xếp hạng'"></span>
                                    </td>
                                    <td>
                                        <template x-if="(it.row.best || it.row.latest).canOpen">
                                            <button type="button" class="submission-view" aria-haspopup="dialog" :aria-label="'Xem lượt nộp ' + (it.row.best || it.row.latest).id + ' của ' + it.row.latest.submitter" @click="openRecord(it.row.best || it.row.latest)">Xem bài<x-lucide name="chevron-right" class="h-3.5 w-3.5" /></button>
                                        </template>
                                        <template x-if="!(it.row.best || it.row.latest).canOpen">
                                            <span class="exam-history-private"><x-lucide name="lock-keyhole" class="h-3.5 w-3.5" />Riêng tư</span>
                                        </template>
                                    </td>
                                </tr>
                                </template>
                                <template x-if="it.kind === 'attempt'">
                                        <tr class="exam-history-attempt">
                                            <td></td>
                                            <td class="exam-person-column">
                                                <strong x-text="'Lần ' + attemptNumber(it.row, it.attempt)"></strong>
                                                <span class="exam-history-badge" x-show="it.row.rank && it.row.best && it.attempt.id === it.row.best.id" x-cloak>Xếp hạng</span>
                                                <time x-text="it.attempt.submittedAt"></time>
                                                <span class="exam-history-attempt-id" :title="it.attempt.id" x-text="it.attempt.id"></span>
                                            </td>
                                            <template x-for="(q, qi) in questions" :key="'a' + it.attempt.id + q.id">
                                                <td class="exam-question-column">
                                                    <template x-if="it.attempt.canOpen">
                                                        <button type="button" class="exam-score-cell" :class="'result-' + cellOf(it.attempt, q.id).status" aria-haspopup="dialog"
                                                                :aria-label="'Xem bài ' + (qi + 1) + ' của ' + it.attempt.submitter + ': ' + statusLabel(cellOf(it.attempt, q.id).status)"
                                                                @click="openRecord(it.attempt, q.id)">
                                                            <strong x-text="cellScore(cellOf(it.attempt, q.id))"></strong><span x-text="statusLabel(cellOf(it.attempt, q.id).status)"></span>
                                                        </button>
                                                    </template>
                                                    <template x-if="!it.attempt.canOpen">
                                                        <div class="exam-score-cell" :class="'result-' + cellOf(it.attempt, q.id).status">
                                                            <strong x-text="cellScore(cellOf(it.attempt, q.id))"></strong><span x-text="statusLabel(cellOf(it.attempt, q.id).status)"></span>
                                                        </div>
                                                    </template>
                                                </td>
                                            </template>
                                            <td class="exam-total-column" :class="hasFinal(it.attempt) ? 'result-partial' : 'result-pending'">
                                                <strong>
                                                    <template x-if="hasFinal(it.attempt)"><span><span x-text="fmt(it.attempt.score)"></span><small>/<span x-text="fmt(it.attempt.maxScore)"></span></small></span></template>
                                                    <template x-if="!hasFinal(it.attempt)"><span>Chờ chấm</span></template>
                                                </strong>
                                            </td>
                                            <td>
                                                <template x-if="it.attempt.canOpen">
                                                    <button type="button" class="submission-view" aria-haspopup="dialog" :aria-label="'Xem lượt nộp ' + it.attempt.id + ' của ' + it.attempt.submitter" @click="openRecord(it.attempt)">Xem bài<x-lucide name="chevron-right" class="h-3.5 w-3.5" /></button>
                                                </template>
                                                <template x-if="!it.attempt.canOpen">
                                                    <span class="exam-history-private"><x-lucide name="lock-keyhole" class="h-3.5 w-3.5" />Riêng tư</span>
                                                </template>
                                            </td>
                                        </tr>
                                    
                                </template>
                            </tbody>
                        </template>
                    </table>
                </div>
            </div>
        </template>

        <div class="submission-empty" x-show="!visible.length" x-cloak>
            <x-lucide name="clipboard-list" />
            <h2 x-text="records.length ? 'Không có người làm phù hợp' : 'Chưa có lượt nộp đề'"></h2>
            <p x-text="mine ? 'Bạn chưa có lượt nộp khớp với bộ lọc.' : 'Các lượt nộp sẽ xuất hiện tại đây sau khi làm đề.'"></p>
        </div>

        <nav class="submission-pagination" aria-label="Phân trang xếp hạng" x-show="filtered.length > 0" x-cloak>
            <div class="submission-page-info">
                <span><strong x-text="rangeLabel"></strong> / <span x-text="filtered.length"></span> người</span>
                <label>Số dòng
                    <select aria-label="Số dòng mỗi trang" x-model.number="pageSize" @change="page = 1; expanded = []">
                        <option value="10">10</option><option value="20">20</option><option value="50">50</option>
                    </select>
                </label>
            </div>
            <div class="submission-page-controls">
                <span class="submission-page-count">Trang <span x-text="currentPage"></span> / <span x-text="totalPages"></span></span>
                <button type="button" aria-label="Trang trước" :disabled="currentPage === 1" @click="goTo(currentPage - 1)"><x-lucide name="chevron-left" class="h-3.5 w-3.5" /></button>
                <template x-for="(n, i) in pageNumbers" :key="'pg' + n">
                    <span style="display:contents">
                        <span class="submission-page-gap" aria-hidden="true" x-show="i > 0 && n - pageNumbers[i - 1] > 1">…</span>
                        <button type="button" :aria-label="'Trang ' + n" :aria-current="n === currentPage ? 'page' : null" @click="goTo(n)" x-text="n"></button>
                    </span>
                </template>
                <button type="button" aria-label="Trang sau" :disabled="currentPage === totalPages" @click="goTo(currentPage + 1)"><x-lucide name="chevron-right" class="h-3.5 w-3.5" /></button>
            </div>
        </nav>

        <p class="submission-storage-note">
            @if ($isAdmin)
                Quản trị viên xem được lượt nộp và bài làm của mọi người làm đề này.
            @else
                Bạn chỉ xem được lượt nộp và bài làm của chính mình. Thứ hạng được tính trên toàn bộ người làm đề.
            @endif
        </p>

        {{-- Hộp thoại "Xem bài" --}}
        <dialog class="exam-submission-dialog" x-ref="dlg" aria-labelledby="exam-dialog-title" @close="selection = null" @click.self="closeDialog()">
            <template x-if="selRecord">
                <div class="exam-dialog-shell">
                    <header class="exam-dialog-header">
                        <div>
                            <p>Bài làm đã nộp</p>
                            <h2 id="exam-dialog-title" x-text="selRecord.submitter"></h2>
                            <span><span x-text="selRecord.account || 'Chưa xác định tài khoản'"></span> · <span x-text="selRecord.submittedAt"></span></span>
                            <span x-show="selRecord.duration" x-cloak>Thời gian làm: <span x-text="selRecord.duration"></span></span>
                        </div>
                        <div class="exam-dialog-total">
                            <strong x-text="selRecord.pending ? 'Chờ chấm' : fmt(selRecord.score)"></strong><small x-show="!selRecord.pending" x-cloak> / <span x-text="fmt(selRecord.maxScore)"></span></small>
                        </div>
                        <button type="button" class="exam-dialog-close" aria-label="Đóng" @click="closeDialog()"><x-lucide name="x" class="h-4 w-4" /></button>
                    </header>
                    <div class="exam-dialog-layout">
                        <nav class="exam-dialog-questions" aria-label="Danh sách bài">
                            <p class="exam-dialog-nav-label">Các bài trong đề</p>
                            <template x-for="(q, index) in questions" :key="'n' + q.id">
                                <button type="button" :aria-pressed="selection.colId === q.id" :class="'result-' + cellOf(selRecord, q.id).status" @click="selection.colId = q.id">
                                    <span><b x-text="'Bài ' + (index + 1)"></b><small x-text="statusLabel(cellOf(selRecord, q.id).status)"></small></span>
                                    <strong><span x-text="cellScore(cellOf(selRecord, q.id))"></span><small>/<span x-text="fmt(q.points)"></span></small></strong>
                                </button>
                            </template>
                        </nav>
                        <section class="exam-dialog-content" x-show="selQuestion" x-cloak>
                            <template x-if="selQuestion">
                                <div style="display:flex;flex-direction:column;min-height:0;flex:1">
                                    <div class="exam-question-heading">
                                        <div>
                                            <p x-text="'Bài ' + (selIndex + 1)"></p>
                                            <h3 x-text="selQuestion.title"></h3>
                                        </div>
                                        <span class="submission-result-badge" :class="'result-' + selCell.status"><span aria-hidden="true"></span><b x-text="statusLabel(selCell.status)" style="font-weight:700"></b></span>
                                    </div>
                                    <div class="exam-question-meta">
                                        <span>Điểm: <strong><span x-text="cellScore(selCell)"></span> / <span x-text="fmt(selQuestion.points)"></span></strong></span>
                                        <span x-show="selDetail && selDetail.language" x-cloak>Ngôn ngữ: <strong x-text="selDetail && selDetail.language"></strong></span>
                                        <span x-show="selDetail && selDetail.tests" x-cloak><strong x-text="selDetail && selDetail.tests"></strong></span>
                                    </div>
                                    <div class="exam-dialog-panel">
                                        <template x-if="selDetail && selDetail.response">
                                            <div>
                                                <div class="exam-answer-label"><strong>Bài làm đã nộp</strong><span x-text="selRecord.submittedAt"></span></div>
                                                <pre class="submission-code" x-text="selDetail.response"></pre>
                                            </div>
                                        </template>
                                        <template x-if="!(selDetail && selDetail.response)">
                                            <p class="exam-dialog-empty" x-text="selCell.status === 'none' ? 'Người làm chưa nộp nội dung cho bài này.' : 'Phần này không lưu nội dung bài làm riêng.'"></p>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </section>
                    </div>
                    <footer class="exam-dialog-footer">
                        <span x-text="'Lượt nộp ' + selRecord.id"></span>
                        <div>
                            <button type="button" class="submission-button" :disabled="selIndex <= 0" @click="step(-1)"><x-lucide name="chevron-left" class="h-3.5 w-3.5" />Bài trước</button>
                            <button type="button" class="submission-button" :disabled="selIndex >= questions.length - 1" @click="step(1)">Bài sau<x-lucide name="chevron-right" class="h-3.5 w-3.5" /></button>
                            <button type="button" class="submission-button" @click="closeDialog()">Đóng</button>
                        </div>
                    </footer>
                </div>
            </template>
        </dialog>
    </main>
</div>
@endsection

@push('scripts')
<script>
    /*
     * SỬA 9/10 — trạng thái trang Nhật ký làm đề. Phần xếp hạng/thống kê chép từ
     * education-main/src/utils/examHistory.js (rankExamParticipants, summarizeExamHistory, examHasFinalScore).
     */
    function onthiExamLog(config) {
        const statusLabels = { ac: 'AC', partial: 'Một phần', wa: 'WA', pending: 'Chờ chấm', none: 'Chưa làm' };
        const none = { score: null, max: null, status: 'none' };
        return {
            records: config.rows || [],
            questions: (config.subject && config.subject.questions) || [],
            maxScore: Number(config.subject && config.subject.maxScore) || 0,
            role: config.role,
            myRank: config.myRank || null,
            query: '',
            mine: false,
            showAttempts: false,
            expanded: [],
            page: 1,
            pageSize: 10,
            selection: null,

            init() {
                ['query', 'mine'].forEach((key) => this.$watch(key, () => { this.page = 1; this.expanded = []; }));
            },

            // ── Định dạng ──
            fmt(v) {
                if (v === null || v === undefined || Number.isNaN(Number(v))) { return '—'; }
                return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(v));
            },
            statusLabel(s) { return statusLabels[s] || statusLabels.none; },
            cellOf(record, colId) { return (record.items && record.items[colId]) || none; },
            cellScore(cell) {
                return cell.status === 'pending' || cell.score === null || cell.score === undefined ? '—' : this.fmt(cell.score);
            },
            barWidth(q) {
                return q.points > 0 ? Math.min(100, Math.max(0, (q.average || 0) / q.points * 100)) : 0;
            },
            totalLabel(record) { return this.hasFinal(record) ? '' : 'Chờ chấm'; },

            // ── Logic xếp hạng (examHistory.js) ──
            hasFinal(r) {
                if (!r || r.pending || r.score === null || r.score === undefined || !(r.maxScore > 0)) { return false; }
                return !Object.values(r.items || {}).some((it) => it.status === 'pending');
            },
            rounded(r) { return Math.round(r.score * 100); },
            get ranking() {
                const groups = new Map();
                this.records.forEach((r) => {
                    const key = 'id:' + r.userId;
                    if (!groups.has(key)) { groups.set(key, { key, attempts: [] }); }
                    groups.get(key).attempts.push(r);
                });
                const rows = [...groups.values()].map((g) => {
                    const attempts = [...g.attempts].sort((a, b) => b.submittedTs - a.submittedTs || String(a.id).localeCompare(String(b.id)));
                    const graded = attempts.filter((r) => this.hasFinal(r))
                        .sort((a, b) => this.rounded(b) - this.rounded(a) || a.submittedTs - b.submittedTs || String(a.id).localeCompare(String(b.id)));
                    return { ...g, attempts, best: graded[0] || null, latest: attempts[0], rank: null };
                }).sort((a, b) => Number(!!b.best) - Number(!!a.best)
                    || (a.best && b.best ? this.rounded(b.best) - this.rounded(a.best) || a.best.submittedTs - b.best.submittedTs : 0)
                    || a.key.localeCompare(b.key));
                let previous = null;
                let rank = 0;
                rows.forEach((row, index) => {
                    if (!row.best) { return; }
                    const score = this.rounded(row.best);
                    if (score !== previous) { rank = index + 1; }
                    row.rank = rank;
                    previous = score;
                });
                // Người không phải admin chỉ có lượt của mình → dùng hạng toàn đề do máy chủ tính.
                if (this.role !== 'admin') {
                    rows.forEach((row) => { row.rank = row.best ? this.myRank : null; });
                }
                return rows;
            },
            get stats() {
                const rows = this.ranking;
                const best = rows.filter((r) => r.rank != null).map((r) => r.best);
                const pending = this.records.filter((r) => !this.hasFinal(r)).length;
                return {
                    people: rows.length,
                    attempts: this.records.length,
                    pending,
                    ranked: best.length,
                    high: best.length ? Math.max(...best.map((r) => r.score)) : null,
                    average: best.length ? best.reduce((s, r) => s + r.score, 0) / best.length : null,
                    questions: this.questions.map((q) => {
                        const items = best.map((r) => (r.items || {})[q.id]).filter((it) => it && it.score !== null && it.score !== undefined && Number.isFinite(Number(it.score)));
                        return { ...q, count: items.length, average: items.length ? items.reduce((s, it) => s + Number(it.score), 0) / items.length : null };
                    }),
                };
            },

            // ── Lọc / phân trang ──
            get filtered() {
                const q = this.query.trim().toLocaleLowerCase('vi');
                return this.ranking.filter((row) =>
                    (!this.mine || row.attempts.some((r) => r.mine))
                    && (!q || row.attempts.some((r) => (r.submitter + ' ' + (r.account || '') + ' ' + r.id).toLocaleLowerCase('vi').includes(q))));
            },
            get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.pageSize)); },
            get currentPage() { return Math.min(this.page, this.totalPages); },
            get visible() {
                const start = (this.currentPage - 1) * this.pageSize;
                return this.filtered.slice(start, start + this.pageSize);
            },
            get rangeLabel() {
                if (!this.filtered.length) { return '0'; }
                const start = (this.currentPage - 1) * this.pageSize + 1;
                return start + '–' + Math.min(this.currentPage * this.pageSize, this.filtered.length);
            },
            get pageNumbers() {
                const total = this.totalPages;
                const start = Math.max(1, Math.min(this.currentPage - 1, total - 2));
                const set = new Set([1, total]);
                for (let i = 0; i < Math.min(3, total); i++) { set.add(start + i); }
                return [...set].sort((a, b) => a - b);
            },
            goTo(n) { this.page = Math.min(Math.max(1, n), this.totalPages); this.expanded = []; },

            // ── Mở rộng các lần nộp ──
            get allExpanded() {
                return this.showAttempts || (this.visible.length > 0 && this.visible.every((r) => this.expanded.includes(r.key)));
            },
            isExpanded(row) { return !!row && (this.showAttempts || this.expanded.includes(row.key)); },
            toggleAll() { this.showAttempts = !this.allExpanded; this.expanded = []; },
            toggleRow(row) {
                if (this.showAttempts) {
                    this.showAttempts = false;
                    this.expanded = this.visible.filter((r) => r.key !== row.key).map((r) => r.key);
                } else if (this.expanded.includes(row.key)) {
                    this.expanded = this.expanded.filter((k) => k !== row.key);
                } else {
                    this.expanded = [...this.expanded, row.key];
                }
            },
            shownAttempts(row) {
                if (!row || !row.attempts) { return []; }
                return this.mine ? row.attempts.filter((a) => a.mine) : row.attempts;
            },
            // Danh sách phẳng các hàng của trang hiện tại: hàng người làm, kèm các hàng "lần nộp" nếu đang mở.
            get tableItems() {
                const out = [];
                this.visible.forEach((row) => {
                    out.push({ key: 'm:' + row.key, kind: 'main', row, attempt: null });
                    if (this.isExpanded(row)) {
                        this.shownAttempts(row).forEach((attempt) => out.push({ key: 'a:' + attempt.id, kind: 'attempt', row, attempt }));
                    }
                });
                return out;
            },
            attemptNumber(row, attempt) { return row && row.attempts ? row.attempts.length - row.attempts.indexOf(attempt) : 0; },

            // ── Hộp thoại "Xem bài" ──
            get selRecord() { return this.selection ? this.records.find((r) => r.id === this.selection.id && r.canOpen) || null : null; },
            get selIndex() { return this.selection ? this.questions.findIndex((q) => q.id === this.selection.colId) : -1; },
            get selQuestion() { return this.selIndex >= 0 ? this.questions[this.selIndex] : null; },
            get selCell() { return this.selRecord && this.selQuestion ? this.cellOf(this.selRecord, this.selQuestion.id) : none; },
            get selDetail() { return this.selRecord && this.selQuestion && this.selRecord.detail ? this.selRecord.detail[this.selQuestion.id] || null : null; },
            openRecord(record, colId) {
                if (!record.canOpen) { return; }
                this.selection = { id: record.id, colId: colId || (this.questions[0] && this.questions[0].id) || null };
                this.$nextTick(() => { if (this.$refs.dlg && !this.$refs.dlg.open) { this.$refs.dlg.showModal(); } });
            },
            closeDialog() { if (this.$refs.dlg && this.$refs.dlg.open) { this.$refs.dlg.close(); } this.selection = null; },
            step(delta) {
                const next = this.questions[this.selIndex + delta];
                if (next) { this.selection.colId = next.id; }
            },
        };
    }
</script>
@endpush
