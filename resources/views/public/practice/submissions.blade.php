@extends('layouts.guest')

@php
    $isProblems = $mode === 'problems';
    $pageTitle = $isProblems ? 'Lịch sử làm bài' : 'Lịch sử làm đề';
@endphp

@section('title', $pageTitle.' · Luyện tập')
@section('meta-description', 'Danh sách các lượt nộp bài luyện tập trên Ôn Thi 360.')

@section('content')
{{-- SỬA 23/10 (khách: "nút Lịch sử làm bài / Lịch sử làm đề, bấm vào ra danh sách bài nộp như mẫu — UI đẹp, bắt mắt") —
     DANH SÁCH BÀI NỘP dùng chung cho 2 chế độ ($mode = problems | exams). Dữ liệu: App\Services\Public\SubmissionListService.
     Lọc / tìm / phân trang bằng tham số trên đường dẫn (form GET) nên chia sẻ được link, bấm Quay lại của trình duyệt vẫn đúng.
     CSS riêng tiền tố shx- (máy chủ không build lại Tailwind). --}}
@php
    $route = $isProblems ? 'practice.submissions.problems' : 'practice.submissions.exams';
    $tabQuery = array_filter(['all' => $filters['all'] ? 1 : null]);
    $hasFilter = $filters['status'] !== '' || $filters['q'] !== '';
    $from = $page->firstItem() ?? 0;
    $to = $page->lastItem() ?? 0;
    $last = $page->lastPage();
    $cur = $page->currentPage();
    $window = collect(range(max(1, $cur - 2), min($last, $cur + 2)));
    $badgeIcon = ['ac' => 'check-circle', 'partial' => 'trending-up', 'wa' => 'x', 'tle' => 'clock', 'rte' => 'x', 'mle' => 'x', 'ce' => 'code-2', 'pending' => 'clock'];
@endphp

<style>
    .shx { --shx-ink: #12304F; --shx-muted: #64809A; --shx-line: #E2ECF3; --shx-card: #fff; --shx-soft: #F6FAFD; display: flex; flex-direction: column; gap: 16px; }
    .shx a { text-decoration: none; }
    /* Hero */
    .shx-hero { position: relative; overflow: hidden; display: grid; gap: 18px; padding: 22px 24px; border-radius: 26px; color: #fff;
        background: radial-gradient(120% 140% at 100% 0%, rgba(56, 189, 248, .35) 0%, rgba(56, 189, 248, 0) 55%), linear-gradient(115deg, #0B3E7A 0%, #0F5FA8 52%, #1489C9 100%);
        box-shadow: 0 14px 40px rgba(11, 62, 122, .18); }
    .shx-hero::after { content: ''; position: absolute; right: -60px; bottom: -90px; width: 280px; height: 280px; border-radius: 999px; background: rgba(255,255,255,.07); }
    @media (min-width: 1024px) { .shx-hero { grid-template-columns: minmax(0, 1fr) auto; align-items: center; padding: 26px 30px; } }
    .shx-back { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border: 1px solid rgba(255,255,255,.25); border-radius: 999px; background: rgba(255,255,255,.12); font-size: 11.5px; font-weight: 700; color: #E3F2FF; backdrop-filter: blur(6px); }
    .shx-back:hover { background: rgba(255,255,255,.2); }
    .shx-back svg { width: 13px; height: 13px; }
    .shx-title { display: flex; align-items: center; gap: 12px; margin: 12px 0 0; color: #fff !important; font-size: 26px; font-weight: 900; letter-spacing: -.01em; line-height: 1.2; }
    .shx-title-ic { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 14px; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.25); }
    .shx-title-ic svg { width: 22px; height: 22px; }
    .shx-sub { margin: 8px 0 0; max-width: 620px; font-size: 13px; line-height: 1.6; color: #CFE6F8; }
    .shx-stats { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .shx-stat { min-width: 120px; padding: 12px 14px; border-radius: 18px; border: 1px solid rgba(255,255,255,.22); background: rgba(255,255,255,.12); backdrop-filter: blur(8px); }
    .shx-stat-l { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: #CFE6F8; }
    .shx-stat-l svg { width: 14px; height: 14px; }
    .shx-stat.is-green .shx-stat-l svg { color: #6EE7B7; } .shx-stat.is-amber .shx-stat-l svg { color: #FCD34D; } .shx-stat.is-blue .shx-stat-l svg { color: #93C5FD; }
    .shx-stat-v { margin-top: 4px; font-size: 24px; font-weight: 900; line-height: 1.1; }
    /* Tabs + toolbar */
    .shx-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px; border: 1px solid var(--shx-line); border-radius: 22px; background: var(--shx-card); box-shadow: 0 4px 16px rgba(52, 91, 120, .05); }
    .shx-tabs { display: inline-flex; gap: 4px; padding: 4px; border-radius: 16px; background: var(--shx-soft); border: 1px solid var(--shx-line); }
    .shx-tab { display: inline-flex; align-items: center; gap: 7px; min-height: 38px; padding: 0 14px; border-radius: 12px; font-size: 12.5px; font-weight: 800; color: var(--shx-muted); }
    .shx-tab svg { width: 15px; height: 15px; }
    .shx-tab:hover { color: var(--shx-ink); background: #fff; }
    .shx-tab.is-on { color: #fff; background: linear-gradient(120deg, #0F5FA8, #1489C9); box-shadow: 0 6px 14px rgba(15, 95, 168, .25); }
    .shx-form { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .shx-select, .shx-input { min-height: 40px; border: 1px solid var(--shx-line); border-radius: 12px; background: #fff; padding: 0 12px; font-size: 12.5px; font-weight: 600; color: var(--shx-ink); outline: none; }
    .shx-select:focus, .shx-input:focus { border-color: #7CB8E6; box-shadow: 0 0 0 3px rgba(20, 137, 201, .12); }
    .shx-search { position: relative; }
    .shx-search svg { position: absolute; left: 11px; top: 50%; width: 15px; height: 15px; transform: translateY(-50%); color: #8AA3B8; pointer-events: none; }
    .shx-search .shx-input { width: 260px; max-width: 100%; padding-left: 33px; }
    .shx-seg { display: inline-flex; padding: 3px; border-radius: 12px; background: var(--shx-soft); border: 1px solid var(--shx-line); }
    .shx-seg a { display: inline-flex; align-items: center; gap: 5px; min-height: 32px; padding: 0 11px; border-radius: 9px; font-size: 12px; font-weight: 800; color: var(--shx-muted); }
    .shx-seg a svg { width: 13px; height: 13px; }
    .shx-seg a.is-on { background: #fff; color: #0F5FA8; box-shadow: 0 2px 8px rgba(52, 91, 120, .12); }
    .shx-btn { display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 0 15px; border: 0; border-radius: 12px; background: #12304F; font-size: 12.5px; font-weight: 800; color: #fff; cursor: pointer; }
    .shx-btn:hover { background: #0B2440; }
    .shx-btn svg { width: 14px; height: 14px; }
    .shx-clear { font-size: 12px; font-weight: 700; color: #0F6FB3; }
    /* Bảng */
    .shx-card { overflow: hidden; border: 1px solid var(--shx-line); border-radius: 22px; background: var(--shx-card); box-shadow: 0 4px 16px rgba(52, 91, 120, .05); }
    .shx-table { width: 100%; border-collapse: collapse; }
    .shx-table th { padding: 13px 14px; background: var(--shx-soft); border-bottom: 1px solid var(--shx-line); font-size: 11px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: var(--shx-muted); text-align: left; white-space: nowrap; }
    .shx-table td { padding: 13px 14px; border-bottom: 1px solid #EEF4F8; font-size: 13px; color: var(--shx-ink); vertical-align: middle; }
    .shx-table tr:last-child td { border-bottom: 0; }
    .shx-table tbody tr { transition: background-color .12s; }
    .shx-table tbody tr:hover { background: #F8FBFE; }
    .shx-table tbody tr.is-mine { background: linear-gradient(90deg, rgba(20,137,201,.06), rgba(20,137,201,0) 40%); }
    .shx-c { text-align: center !important; }
    .shx-date b { display: block; font-size: 13px; font-weight: 800; }
    .shx-date span { font-size: 11.5px; color: var(--shx-muted); font-variant-numeric: tabular-nums; }
    .shx-who { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .shx-av { display: grid; place-items: center; flex: none; width: 34px; height: 34px; border-radius: 12px; background: linear-gradient(135deg, #DCEEFB, #EAF7F1); font-size: 13px; font-weight: 900; color: #0F5FA8; }
    .shx-who b { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 800; color: #1D4ED8; }
    .shx-who small { display: block; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; color: var(--shx-muted); }
    .shx-me { padding: 1px 7px; border-radius: 999px; background: #E0F2FE; font-size: 10px; font-weight: 800; color: #0369A1; }
    .shx-badge { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 9px; font-size: 12px; font-weight: 800; white-space: nowrap; color: #fff; box-shadow: 0 3px 8px rgba(0,0,0,.08); }
    .shx-badge svg { width: 13px; height: 13px; }
    .shx-badge.is-ac { background: #22A06B; } .shx-badge.is-partial { background: #2D7FF9; } .shx-badge.is-wa { background: #E5484D; }
    .shx-badge.is-tle { background: #8E4EC6; } .shx-badge.is-rte, .shx-badge.is-mle { background: #D9480F; } .shx-badge.is-ce { background: #F2A33A; } .shx-badge.is-pending { background: #8DA2B5; }
    .shx-tests { display: block; margin-top: 4px; font-size: 11px; font-weight: 700; color: var(--shx-muted); }
    .shx-task a { display: inline-flex; align-items: center; gap: 5px; font-weight: 800; color: #0F6FB3; }
    .shx-task a:hover { color: #0B4F86; text-decoration: underline; text-underline-offset: 2px; }
    .shx-task a svg { width: 12px; height: 12px; opacity: .5; }
    .shx-task code { display: block; margin-top: 2px; font-size: 10.5px; color: #8AA3B8; }
    .shx-num { font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .shx-dim { color: #A9BAC8; }
    .shx-lang { display: inline-block; padding: 3px 9px; border-radius: 8px; background: var(--shx-soft); border: 1px solid var(--shx-line); font-size: 11.5px; font-weight: 800; color: #35506A; white-space: nowrap; }
    .shx-score b { font-size: 14px; font-weight: 900; font-variant-numeric: tabular-nums; }
    .shx-meter { width: 90px; height: 6px; margin-top: 6px; border-radius: 999px; background: #E8F0F6; overflow: hidden; }
    .shx-meter i { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #2D7FF9, #22A06B); }
    .shx-log { display: inline-grid; place-items: center; width: 32px; height: 32px; border-radius: 10px; border: 1px solid var(--shx-line); background: #fff; color: #5C7A94; }
    .shx-log:hover { color: #0F5FA8; border-color: #B8D8F0; background: #F2F9FF; }
    .shx-log svg { width: 15px; height: 15px; }
    /* Trống + phân trang */
    .shx-empty { padding: 56px 20px; text-align: center; }
    .shx-empty-ic { display: grid; place-items: center; width: 60px; height: 60px; margin: 0 auto; border-radius: 20px; background: #EEF6FC; color: #5AA3D6; }
    .shx-empty-ic svg { width: 28px; height: 28px; }
    .shx-empty h3 { margin: 14px 0 0; font-size: 15px; font-weight: 900; color: var(--shx-ink); }
    .shx-empty p { margin: 6px 0 0; font-size: 12.5px; color: var(--shx-muted); }
    .shx-empty .shx-btn { margin-top: 16px; }
    .shx-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px; border-top: 1px solid var(--shx-line); background: #FBFDFE; }
    .shx-pager > span { font-size: 12px; color: var(--shx-muted); }
    .shx-pager b { color: var(--shx-ink); }
    .shx-pages { display: flex; gap: 5px; }
    .shx-pages a, .shx-pages span { display: inline-grid; place-items: center; min-width: 34px; height: 34px; padding: 0 8px; border-radius: 10px; border: 1px solid var(--shx-line); background: #fff; font-size: 12px; font-weight: 800; color: #35506A; }
    .shx-pages a:hover { border-color: #B8D8F0; color: #0F5FA8; }
    .shx-pages .is-on { background: #0F5FA8; border-color: #0F5FA8; color: #fff; }
    .shx-pages .is-off { opacity: .4; }
    .shx-pages svg { width: 15px; height: 15px; }
    /* Điện thoại: mỗi dòng thành 1 thẻ */
    @media (max-width: 900px) {
        .shx-hero { padding: 18px; } .shx-title { font-size: 21px; }
        .shx-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } .shx-stat { min-width: 0; padding: 10px; } .shx-stat-v { font-size: 19px; }
        .shx-search .shx-input { width: 100%; } .shx-search, .shx-form { width: 100%; }
        /* Mỗi lượt nộp thành 1 thẻ gọn: người nộp + trạng thái / tên bài / số liệu chạy + giờ nộp + nhật ký. */
        .shx-table thead { display: none; }
        .shx-table, .shx-table tbody { display: block; width: 100%; }
        .shx-table tr { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; padding: 14px; border-bottom: 1px solid #EEF4F8; }
        .shx-table td { display: block; padding: 0; border: 0; }
        .shx-c { text-align: left !important; }
        .shx-td-who { order: 1; flex: 1 1 auto; min-width: 0; }
        .shx-td-st { order: 2; }
        .shx-td-st .shx-tests { display: inline; margin: 0 0 0 6px; }
        .shx-td-task { order: 3; flex-basis: 100%; }
        .shx-td-met { order: 4; font-size: 12px; }
        .shx-td-met::before { content: attr(data-l) ' '; font-size: 11px; font-weight: 600; color: var(--shx-muted); }
        .shx-td-date { order: 5; flex: 1 1 auto; display: flex !important; gap: 6px; align-items: baseline; }
        .shx-td-date b, .shx-td-date span { display: inline; font-size: 11.5px; }
        .shx-td-date b { font-weight: 700; color: var(--shx-muted); }
        .shx-td-log { order: 6; margin-left: auto; }
        .shx-td-met + .shx-td-date { margin-top: 2px; }
        .shx-who small { max-width: 180px; }
    }
    /* Nền tối */
    html.theme-dark .shx { --shx-ink: #E2ECF5; --shx-muted: #93A9BC; --shx-line: #25445C; --shx-card: #12283A; --shx-soft: #173246; }
    html.theme-dark .shx-select, html.theme-dark .shx-input, html.theme-dark .shx-log, html.theme-dark .shx-pages a, html.theme-dark .shx-pages span, html.theme-dark .shx-seg a.is-on { background: #12283A; color: #E2ECF5; }
    html.theme-dark .shx-table tbody tr:hover, html.theme-dark .shx-pager { background: #15304A; }
    html.theme-dark .shx-table td { border-color: #1E3A50; }
    html.theme-dark .shx-who b, html.theme-dark .shx-task a { color: #8FD3F7; }
    html.theme-dark .shx-btn { background: #1F6FB2; }
</style>

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div class="shx">

    {{-- ═══ Hero ═══ --}}
    <section class="shx-hero">
        <div style="position:relative;z-index:1">
            <a class="shx-back" href="{{ route('practice.index', $isProblems ? [] : ['tab' => 'de-thi']) }}"><x-lucide name="arrow-left" /> Quay lại Luyện tập</a>
            <h1 class="shx-title"><span class="shx-title-ic"><x-lucide name="history" /></span>{{ $pageTitle }}</h1>
            <p class="shx-sub">
                @if ($filters['all'])
                    {{ $isAdmin ? 'Tất cả lượt nộp trên hệ thống' : 'Lượt nộp của bạn và các học sinh bạn đã giao bài' }} — mới nhất ở trên cùng.
                @else
                    Các lượt nộp {{ $isProblems ? 'bài tập chuyên đề' : 'đề thi luyện tập' }} của bạn — mới nhất ở trên cùng. Bấm tên {{ $isProblems ? 'bài' : 'đề' }} để mở lại.
                @endif
            </p>
        </div>
        <div class="shx-stats">
            @foreach ($stats as $s)
                <div class="shx-stat is-{{ $s['tone'] }}">
                    <div class="shx-stat-l"><x-lucide :name="$s['icon']" />{{ $s['label'] }}</div>
                    <div class="shx-stat-v">{{ is_int($s['value']) ? number_format($s['value'], 0, ',', '.') : $s['value'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ═══ Tab + bộ lọc ═══ --}}
    <div class="shx-bar">
        <nav class="shx-tabs" aria-label="Loại lịch sử">
            <a href="{{ route('practice.submissions.problems', $tabQuery) }}" class="shx-tab {{ $isProblems ? 'is-on' : '' }}" @if ($isProblems) aria-current="page" @endif><x-lucide name="code-2" />Bài tập chuyên đề</a>
            <a href="{{ route('practice.submissions.exams', $tabQuery) }}" class="shx-tab {{ $isProblems ? '' : 'is-on' }}" @unless ($isProblems) aria-current="page" @endunless><x-lucide name="file-text" />Đề thi luyện tập</a>
        </nav>

        <form method="GET" action="{{ route($route) }}" class="shx-form">
            @if ($filters['all']) <input type="hidden" name="all" value="1"> @endif
            {{-- SỬA 23/10 (khách: "bỏ ô Mọi trạng thái") — bỏ ô lọc trạng thái; bộ lọc ?status= vẫn nhận nếu có trong đường dẫn. --}}
            @if ($canSeeOthers)
                @php $keep = array_filter(['status' => $filters['status'] ?: null, 'q' => $filters['q'] ?: null]); @endphp
                <div class="shx-seg" role="group" aria-label="Phạm vi">
                    <a href="{{ route($route, $keep) }}" class="{{ $filters['all'] ? '' : 'is-on' }}">Của tôi</a>
                    <a href="{{ route($route, $keep + ['all' => 1]) }}" class="{{ $filters['all'] ? 'is-on' : '' }}" title="{{ $isAdmin ? 'Mọi người dùng' : 'Bạn và học sinh bạn đã giao bài' }}"><x-lucide name="users" />{{ $isAdmin ? 'Tất cả' : 'Học sinh của tôi' }}</a>
                </div>
            @endif
            <label class="shx-search">
                <x-lucide name="search" />
                <input type="search" name="q" value="{{ $filters['q'] }}" class="shx-input" maxlength="100"
                       placeholder="{{ $filters['all'] ? 'Tên tài khoản, tên / mã '.($isProblems ? 'bài' : 'đề') : 'Tên hoặc mã '.($isProblems ? 'bài' : 'đề') }}">
            </label>
            <button type="submit" class="shx-btn"><x-lucide name="filter" />Lọc</button>
            @if ($hasFilter)
                <a href="{{ route($route, $tabQuery) }}" class="shx-clear">Xoá lọc</a>
            @endif
        </form>
    </div>

    {{-- ═══ Danh sách ═══ --}}
    <div class="shx-card">
        @if ($rows === [])
            <div class="shx-empty">
                <div class="shx-empty-ic"><x-lucide name="inbox" /></div>
                <h3>{{ $hasFilter ? 'Không có lượt nộp nào khớp bộ lọc' : 'Chưa có lượt nộp nào' }}</h3>
                <p>{{ $hasFilter ? 'Thử bỏ bớt điều kiện lọc hoặc tìm từ khoá khác.' : ($isProblems ? 'Làm một bài tập chuyên đề rồi bấm Nộp bài, lượt nộp sẽ hiện ở đây.' : 'Làm một đề thi luyện tập rồi bấm Nộp bài, lượt nộp sẽ hiện ở đây.') }}</p>
                <a class="shx-btn" href="{{ $hasFilter ? route($route, $tabQuery) : route('practice.index', $isProblems ? [] : ['tab' => 'de-thi']) }}">
                    {{ $hasFilter ? 'Xoá bộ lọc' : ($isProblems ? 'Đi làm bài' : 'Đi làm đề') }}
                </a>
            </div>
        @else
            <table class="shx-table">
                <thead>
                    <tr>
                        <th>Thời gian nộp</th>
                        <th>Tài khoản</th>
                        <th class="shx-c">{{ $isProblems ? 'Trạng thái' : 'Kết quả' }}</th>
                        <th>{{ $isProblems ? 'Bài tập' : 'Đề thi' }}</th>
                        @if ($isProblems)
                            <th class="shx-c">Ngôn ngữ</th>
                        @else
                            <th>Điểm</th>
                            <th class="shx-c">Thời gian làm</th>
                        @endif
                        <th class="shx-c">Nhật ký</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr class="{{ $r['mine'] && $filters['all'] ? 'is-mine' : '' }}">
                            <td data-l="Nộp lúc" class="shx-date shx-td-date"><b>{{ $r['at']?->format('d/m/Y') }}</b><span>{{ $r['at']?->format('H:i:s') }}</span></td>
                            <td data-l="Tài khoản" class="shx-td-who">
                                <div class="shx-who">
                                    <span class="shx-av">{{ mb_strtoupper(mb_substr($r['name'], 0, 1)) }}</span>
                                    <span style="min-width:0">
                                        <b>{{ $r['name'] }} @if ($r['mine'])<span class="shx-me">Bạn</span>@endif</b>
                                        @if ($canSeeOthers && $r['account'] !== '')<small>{{ $r['account'] }}</small>@endif
                                    </span>
                                </div>
                            </td>
                            <td data-l="{{ $isProblems ? 'Trạng thái' : 'Kết quả' }}" class="shx-c shx-td-st">
                                <span class="shx-badge is-{{ $r['statusKey'] }}"><x-lucide :name="$badgeIcon[$r['statusKey']] ?? 'clock'" />{{ $r['statusLabel'] }}</span>
                                @if ($isProblems && $r['tests'] && $r['statusKey'] !== 'pending')<span class="shx-tests">{{ $r['tests'] }} test</span>@endif
                            </td>
                            <td data-l="{{ $isProblems ? 'Bài tập' : 'Đề thi' }}" class="shx-task shx-td-task">
                                @if ($r['href'])
                                    <a href="{{ $r['href'] }}" target="_blank" rel="noopener" title="Mở {{ $r['title'] }} (tab mới)">{{ $r['title'] }}<x-lucide name="external-link" /></a>
                                @else
                                    <span class="shx-dim">{{ $r['title'] }}</span>
                                @endif
                                @if ($r['code'])<code>{{ $r['code'] }}</code>@endif
                            </td>
                            @if ($isProblems)
                                {{-- SỬA 23/10 (khách: "bỏ cột thời gian và bộ nhớ") — 2 cột đã bỏ khỏi bảng. --}}
                                <td data-l="Ngôn ngữ" class="shx-c shx-td-met"><span class="shx-lang">{{ $r['language'] }}</span></td>
                            @else
                                <td data-l="Điểm" class="shx-score shx-td-met">
                                    @if ($r['score'] !== null)
                                        <b>{{ $r['score'] }}</b>
                                        @if ($r['ratio'] !== null)<div class="shx-meter"><i style="width: {{ $r['ratio'] }}%"></i></div>@endif
                                    @else
                                        <span class="shx-dim">--</span>
                                    @endif
                                </td>
                                <td data-l="Thời gian làm" class="shx-c shx-num shx-td-met">{!! $r['duration'] ? e($r['duration']) : '<span class="shx-dim">--</span>' !!}</td>
                            @endif
                            <td data-l="Nhật ký" class="shx-c shx-td-log">
                                @if ($r['historyHref'])
                                    <a class="shx-log" href="{{ $r['historyHref'] }}" title="Nhật ký nộp {{ $isProblems ? 'bài' : 'đề' }} này"><x-lucide name="scroll-text" /></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="shx-pager">
                <span>Hiển thị <b>{{ number_format($from, 0, ',', '.') }}–{{ number_format($to, 0, ',', '.') }}</b> / {{ number_format($page->total(), 0, ',', '.') }} lượt nộp</span>
                @if ($last > 1)
                    <nav class="shx-pages" aria-label="Phân trang">
                        @if ($page->onFirstPage())
                            <span class="is-off" aria-hidden="true"><x-lucide name="chevron-left" /></span>
                        @else
                            <a href="{{ $page->previousPageUrl() }}" aria-label="Trang trước"><x-lucide name="chevron-left" /></a>
                        @endif
                        @if ($window->first() > 1)
                            <a href="{{ $page->url(1) }}">1</a>
                            @if ($window->first() > 2)<span class="is-off">…</span>@endif
                        @endif
                        @foreach ($window as $n)
                            @if ($n === $cur)
                                <span class="is-on" aria-current="page">{{ $n }}</span>
                            @else
                                <a href="{{ $page->url($n) }}">{{ $n }}</a>
                            @endif
                        @endforeach
                        @if ($window->last() < $last)
                            @if ($window->last() < $last - 1)<span class="is-off">…</span>@endif
                            <a href="{{ $page->url($last) }}">{{ $last }}</a>
                        @endif
                        @if ($page->hasMorePages())
                            <a href="{{ $page->nextPageUrl() }}" aria-label="Trang sau"><x-lucide name="chevron-right" /></a>
                        @else
                            <span class="is-off" aria-hidden="true"><x-lucide name="chevron-right" /></span>
                        @endif
                    </nav>
                @endif
            </div>
        @endif
    </div>
</div>
</div>
@endsection
