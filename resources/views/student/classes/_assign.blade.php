{{-- SỬA 7/11 (khách: "làm lại khu học sinh cho đẹp, tiện dùng") — tab "Bài tập / Đề" trong trang lớp của học sinh: bài / đề giáo viên
     của lớp giao (bài của lớp + đề / bài luyện tập giao từ tab Bài tập / Đề bên giáo viên), kèm hạn nộp, trạng thái, điểm và nút làm
     bài. Dữ liệu: StudentWorkspaceService::assignments($user, $classId) — cùng nguồn trang "Bài & đề được giao". CSS tiền tố sca-. --}}
@php
    $sca = $classAssign ?? ['rows' => [], 'counts' => []];
    $scaRows = $sca['rows'] ?? [];
    $scaCounts = $sca['counts'] ?? [];
    $scaNow = now()->getTimestamp();
    $scaGroup = fn ($r) => in_array($r['state'], ['new', 'active'], true) ? 'todo' : ($r['state'] === 'submitted' ? 'wait' : 'done');
    $scaIn = function (?int $ts) use ($scaNow) {
        if (! $ts) {
            return null;
        }
        $d = \Illuminate\Support\Carbon::createFromTimestamp($ts, config('app.timezone'));

        return $ts > $scaNow
            ? 'còn '.$d->copy()->locale('vi')->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE, false, 1)
            : 'quá hạn '.$d->copy()->locale('vi')->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE, false, 1);
    };
    $scaTodo = count(array_filter($scaRows, fn ($r) => $scaGroup($r) === 'todo'));
    $scaWait = count(array_filter($scaRows, fn ($r) => $scaGroup($r) === 'wait'));
    $scaDone = count(array_filter($scaRows, fn ($r) => $scaGroup($r) === 'done'));
    $scaOver = count(array_filter($scaRows, fn ($r) => $scaGroup($r) === 'todo' && $r['overdue']));
@endphp

<section class="sca" x-data="{ f: '{{ $scaTodo > 0 ? 'todo' : 'all' }}' }">
    <header class="sca-head">
        <span class="sca-ic"><x-lucide name="clipboard-list" /></span>
        <div class="sca-ht">
            <h2>Bài tập / Đề của lớp</h2>
            <p>Thầy cô giao cho lớp — làm và nộp trước hạn nhé. Điểm hiện ngay khi chấm xong.</p>
        </div>
    </header>

    <div class="sca-stats">
        <button type="button" class="sca-stat todo" :class="{ on: f === 'todo' }" @click="f = 'todo'">
            <b>{{ $scaTodo }}</b><span>Cần làm</span>@if ($scaOver > 0)<em>{{ $scaOver }} quá hạn</em>@endif
        </button>
        <button type="button" class="sca-stat wait" :class="{ on: f === 'wait' }" @click="f = 'wait'"><b>{{ $scaWait }}</b><span>Chờ chấm</span></button>
        <button type="button" class="sca-stat done" :class="{ on: f === 'done' }" @click="f = 'done'"><b>{{ $scaDone }}</b><span>Đã xong</span></button>
        <button type="button" class="sca-stat all" :class="{ on: f === 'all' }" @click="f = 'all'"><b>{{ count($scaRows) }}</b><span>Tất cả</span></button>
    </div>

    @if ($scaRows === [])
        <div class="sca-empty"><x-lucide name="sparkles" /><b>Lớp chưa có bài nào được giao</b><span>Khi thầy cô giao bài, em nhận thông báo và bài hiện ở đây.</span></div>
    @else
        <div class="sca-list">
            @foreach ($scaRows as $r)
                @php
                    $g = $scaGroup($r);
                    $isExam = $r['kind'] === 'exam';
                    $in = $g === 'todo' ? $scaIn($r['deadlineTs'] ?? null) : null;
                    $soon = $g === 'todo' && ! $r['overdue'] && ! empty($r['deadlineTs']) && $r['deadlineTs'] - $scaNow < 86400;
                    [$pill, $tone] = match (true) {
                        $g === 'todo' && $r['overdue'] => ['Quá hạn', 'red'],
                        $r['state'] === 'active' => ['Đang làm', 'blue'],
                        $g === 'todo' => ['Chưa làm', 'amber'],
                        $g === 'wait' => ['Chờ chấm', 'violet'],
                        default => ['Hoàn thành', 'green'],
                    };
                @endphp
                <article class="sca-card {{ $g }} {{ $r['overdue'] && $g === 'todo' ? 'over' : '' }}" x-show="f === 'all' || f === '{{ $g }}'">
                    <span class="sca-kind {{ $isExam ? 'exam' : 'code' }}"><x-lucide :name="$isExam ? 'file-check-2' : 'code-2'" /></span>
                    <div class="sca-main">
                        <div class="sca-top">
                            <h3>{{ $r['title'] }}</h3>
                            <span class="sca-pill {{ $tone }}">{{ $pill }}</span>
                            @if ($r['late'])<span class="sca-pill gray">Nộp muộn</span>@endif
                        </div>
                        <p class="sca-meta">
                            <span>{{ $isExam ? 'Đề' : 'Bài lập trình' }}{{ $r['meta'] ? ' · '.$r['meta'] : '' }}</span>
                            <span>· {{ $r['teacher'] }}</span>
                        </p>
                        <div class="sca-when">
                            @if ($r['deadline'])
                                <span class="{{ $g === 'todo' && $r['overdue'] ? 'red' : ($soon ? 'amber' : '') }}"><x-lucide name="clock" />Hạn {{ $r['deadline'] }}@if ($in) <em>({{ $in }})</em>@endif</span>
                            @else
                                <span><x-lucide name="clock" />Không giới hạn thời gian</span>
                            @endif
                            @if ($r['submittedAt'])<span><x-lucide name="check" />Nộp lúc {{ $r['submittedAt'] }}</span>@endif
                        </div>
                        @if (! empty($r['feedback']))
                            <p class="sca-fb"><x-lucide name="message-square-text" /><span><b>Thầy cô nhận xét:</b> {{ $r['feedback'] }}</span></p>
                        @endif
                    </div>
                    <div class="sca-side">
                        @if ($g === 'done' && ($r['result'] || $r['verdict']))
                            <span class="sca-score">{{ $r['result'] ?: $r['verdict'] }}</span>
                        @endif
                        @if ($r['url'])
                            <a href="{{ $r['url'] }}" data-stu-full class="sca-btn {{ $g === 'todo' ? 'pri' : '' }}">
                                @if ($g === 'todo'){{ $r['state'] === 'active' ? 'Làm tiếp' : 'Làm bài' }}<x-lucide name="arrow-right" />
                                @elseif ($g === 'wait')Xem bài đã nộp
                                @else Xem lại
                                @endif
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
            <p class="sca-none" x-show="f !== 'all' && ! document.querySelector('.sca-card.' + f)" x-cloak>Không có bài nào trong mục này.</p>
        </div>
    @endif
</section>

<style>
    .sca { padding: 18px; border: 1px solid #DDEAF0; border-radius: 20px; background: #fff; box-shadow: 0 6px 18px rgba(15, 42, 74, .04); }
    .sca-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .sca-ic { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 12px; background: #FFF1E6; color: #EA580C; }
    .sca-ic svg { width: 19px; height: 19px; }
    .sca-ht h2 { margin: 0; font-size: 15px; font-weight: 700; color: #123B68; }
    .sca-ht p { margin: 2px 0 0; font-size: 12px; color: #61798B; }
    .sca-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-bottom: 14px; }
    @media (max-width: 640px) { .sca-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .sca-stat { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 1px; padding: 10px 12px; border: 1px solid #E5EDF3; border-radius: 14px; background: #F8FBFD; font: inherit; text-align: left; cursor: pointer; transition: all .15s; }
    .sca-stat b { font-size: 20px; line-height: 1.1; font-weight: 800; color: #123B68; }
    .sca-stat span { font-size: 12px; font-weight: 600; color: #61798B; }
    .sca-stat em { position: absolute; top: 8px; right: 8px; padding: 1px 7px; border-radius: 999px; background: #FEE2E2; color: #DC2626; font-style: normal; font-size: 11px; font-weight: 700; }
    .sca-stat:hover { border-color: #B9D4E3; }
    .sca-stat.on { border-color: #126F91; background: #EAF5F8; box-shadow: 0 0 0 3px rgba(18, 111, 145, .1); }
    .sca-stat.todo b { color: #C2410C; } .sca-stat.wait b { color: #6D28D9; } .sca-stat.done b { color: #15803D; }
    .sca-list { display: grid; gap: 10px; }
    .sca-card { display: flex; gap: 12px; padding: 14px; border: 1px solid #E5EDF3; border-radius: 16px; background: #fff; transition: border-color .15s, box-shadow .15s; }
    .sca-card:hover { border-color: #C9DEE8; box-shadow: 0 6px 16px rgba(15, 42, 74, .06); }
    .sca-card.todo { border-left: 4px solid #F59E0B; } .sca-card.over { border-left-color: #EF4444; background: #FFFBFB; }
    .sca-card.wait { border-left: 4px solid #8B5CF6; } .sca-card.done { border-left: 4px solid #22C55E; }
    .sca-kind { display: grid; place-items: center; width: 38px; height: 38px; flex: none; border-radius: 12px; }
    .sca-kind svg { width: 18px; height: 18px; } .sca-kind.exam { background: #EEF4FF; color: #2563EB; } .sca-kind.code { background: #ECFDF5; color: #059669; }
    .sca-main { flex: 1; min-width: 0; }
    .sca-top { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; }
    .sca-top h3 { margin: 0; font-size: 14px; font-weight: 700; color: #123B68; }
    .sca-pill { padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .sca-pill.red { background: #FEE2E2; color: #DC2626; } .sca-pill.amber { background: #FEF3C7; color: #B45309; } .sca-pill.blue { background: #DBEAFE; color: #1D4ED8; }
    .sca-pill.violet { background: #EDE9FE; color: #6D28D9; } .sca-pill.green { background: #DCFCE7; color: #15803D; } .sca-pill.gray { background: #F1F5F9; color: #64748B; }
    .sca-meta { display: flex; flex-wrap: wrap; gap: 4px; margin: 3px 0 0; font-size: 12px; color: #61798B; }
    .sca-when { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 6px; font-size: 12px; color: #466278; }
    .sca-when span { display: inline-flex; align-items: center; gap: 5px; } .sca-when svg { width: 14px; height: 14px; color: #7890A0; }
    .sca-when em { font-style: normal; color: #61798B; }
    .sca-when .red, .sca-when .red svg, .sca-when .red em { color: #DC2626; font-weight: 600; } .sca-when .amber, .sca-when .amber svg, .sca-when .amber em { color: #B45309; font-weight: 600; }
    .sca-fb { display: flex; gap: 6px; margin: 8px 0 0; padding: 8px 10px; border-radius: 10px; background: #F5F3FF; font-size: 12px; line-height: 1.5; color: #4C1D95; }
    .sca-fb svg { width: 14px; height: 14px; flex: none; margin-top: 2px; } .sca-fb b { font-weight: 700; }
    .sca-side { display: flex; flex-direction: column; align-items: flex-end; justify-content: center; gap: 8px; flex: none; }
    .sca-score { padding: 4px 10px; border-radius: 10px; background: #DCFCE7; font-size: 14px; font-weight: 800; color: #15803D; }
    .sca-btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border: 1px solid #CFE0EA; border-radius: 11px; background: #fff; font-size: 13px; font-weight: 700; color: #126F91; text-decoration: none; white-space: nowrap; transition: all .15s; }
    .sca-btn svg { width: 15px; height: 15px; } .sca-btn:hover { border-color: #126F91; background: #F4F9FC; }
    .sca-btn.pri { border: 0; background: linear-gradient(135deg, #126F91, #1688B0); color: #fff; box-shadow: 0 6px 14px rgba(18, 111, 145, .25); }
    .sca-btn.pri:hover { background: linear-gradient(135deg, #0F5F7C, #126F91); }
    @media (max-width: 560px) { .sca-card { flex-wrap: wrap; } .sca-side { width: 100%; flex-direction: row; justify-content: space-between; } }
    .sca-empty { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 34px 12px; border: 1px dashed #D5E1EA; border-radius: 14px; text-align: center; }
    .sca-empty svg { width: 26px; height: 26px; color: #9DC8D7; } .sca-empty b { font-size: 14px; color: #123B68; } .sca-empty span { font-size: 12px; color: #61798B; }
    .sca-none { margin: 4px 0 0; padding: 18px; border: 1px dashed #D5E1EA; border-radius: 12px; text-align: center; font-size: 12px; color: #61798B; }
</style>
