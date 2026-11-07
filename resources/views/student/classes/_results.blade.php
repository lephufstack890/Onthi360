{{-- SỬA 7/11 (khách: "làm lại khu học sinh cho đẹp, tiện dùng") — tab "Kết quả học tập" trong trang lớp của học sinh: đúng số liệu
     thầy cô thấy ở bảng điểm lớp (điểm thang 10 — thầy cô chấm đè thì theo điểm chấm, nhận xét từng bài, nhận xét chung), chỉ của
     CHÍNH em, kèm chuyên cần. Dữ liệu: Student\ClassRoomService::myResults(). CSS tiền tố scr-. --}}
@php
    $scr = $myResults ?? ['items' => [], 'att' => []];
    $scrItems = $scr['items'] ?? [];
    $scrAtt = $scr['att'] ?? ['present' => 0, 'late' => 0, 'excused' => 0, 'absent' => 0, 'sessions' => 0];
    $scrMarked = $scrAtt['present'] + $scrAtt['late'] + $scrAtt['excused'] + $scrAtt['absent'];
    $scrFmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1, ',', ''), '0'), ',');
    $scrTone = fn ($v) => $v === null ? 'none' : ($v >= 8 ? 'ok' : ($v >= 6.5 ? 'good' : ($v >= 5 ? 'mid' : 'low')));
    $scrState = [
        'graded' => ['Đã chấm', 'green'], 'grading' => ['Chờ chấm', 'violet'], 'todo' => ['Chưa làm', 'amber'],
        'missed' => ['Không nộp', 'red'], 'none' => ['—', 'gray'],
    ];
@endphp

<section class="scr">
    <header class="scr-head">
        <span class="scr-ic"><x-lucide name="bar-chart-3" /></span>
        <div>
            <h2>Kết quả học tập</h2>
            <p>Điểm từng bài theo thang 10 và nhận xét của thầy cô — cập nhật ngay khi thầy cô chấm.</p>
        </div>
    </header>

    @if (! ($scr['inClass'] ?? false))
        <div class="scr-empty"><x-lucide name="users" /><b>Chỉ học sinh của lớp mới có bảng kết quả</b></div>
    @else
        <div class="scr-stats">
            <div class="scr-stat main {{ $scrTone($scr['avg']) }}">
                <span>Điểm trung bình</span>
                <b>{{ $scrFmt($scr['avg']) }}<small>/10</small></b>
                @if ($scr['rank'])<em>{{ $scr['rank'][0] }}</em>@else<i>Chưa có điểm</i>@endif
            </div>
            <div class="scr-stat">
                <span>Bài đã nộp</span>
                <b>{{ $scr['submitted'] }}<small>/{{ $scr['total'] }}</small></b>
                <i>{{ $scr['total'] > 0 ? round($scr['submitted'] / $scr['total'] * 100).'% số bài được giao' : 'Chưa có bài được giao' }}</i>
            </div>
            <div class="scr-stat">
                <span>Chuyên cần</span>
                <b>{{ $scr['attRate'] !== null ? $scr['attRate'].'%' : '—' }}</b>
                <i>{{ $scrAtt['present'] + $scrAtt['late'] }}/{{ $scrMarked }} buổi đã điểm danh có mặt</i>
            </div>
            <div class="scr-stat">
                <span>Trung bình cả lớp</span>
                <b>{{ $scrFmt($scr['classAvg']) }}<small>/10</small></b>
                <i>Để em so sánh với các bạn</i>
            </div>
        </div>

        @if ($scr['note'] !== '')
            <div class="scr-note">
                <x-lucide name="message-square-text" />
                <div><b>Thầy cô nhận xét chung</b><p>{{ $scr['note'] }}</p></div>
            </div>
        @endif

        <div class="scr-grid">
            <div class="scr-card">
                <h3>Điểm từng bài</h3>
                @forelse ($scrItems as $it)
                    @php [$lb, $tn] = $scrState[$it['state']] ?? ['—', 'gray']; @endphp
                    <div class="scr-row">
                        <span class="scr-sc {{ $scrTone($it['score']) }}">{{ $scrFmt($it['score']) }}</span>
                        <div class="scr-rm">
                            <div class="scr-rt">
                                <b>{{ $it['title'] }}</b>
                                <span class="scr-pill {{ $tn }}">{{ $lb }}</span>
                                @if ($it['override'])<span class="scr-pill gray" title="Thầy cô đã chấm / sửa điểm">Thầy cô chấm</span>@endif
                            </div>
                            @if ($it['due'])<small>Hạn nộp {{ $it['due'] }}</small>@endif
                            @if ($it['comment'] !== '')<p class="scr-cm"><x-lucide name="message-circle" />{{ $it['comment'] }}</p>@endif
                        </div>
                        @if ($it['url'])
                            <a href="{{ $it['url'] }}" data-stu-full class="scr-go {{ $it['state'] === 'todo' ? 'pri' : '' }}">{{ $it['state'] === 'todo' ? 'Làm bài' : 'Xem bài' }}</a>
                        @endif
                    </div>
                @empty
                    <div class="scr-empty sm"><x-lucide name="clipboard-list" /><span>Lớp chưa có bài được giao để tính điểm.</span></div>
                @endforelse
            </div>

            <div class="scr-card">
                <h3>Điểm danh</h3>
                <div class="scr-att">
                    <div class="ok"><b>{{ $scrAtt['present'] }}</b><span>Có mặt</span></div>
                    <div class="late"><b>{{ $scrAtt['late'] }}</b><span>Đi muộn</span></div>
                    <div class="ex"><b>{{ $scrAtt['excused'] }}</b><span>Vắng có phép</span></div>
                    <div class="no"><b>{{ $scrAtt['absent'] }}</b><span>Vắng</span></div>
                </div>
                <p class="scr-hint">{{ $scrAtt['sessions'] }} buổi đã diễn ra · {{ max(0, $scrAtt['sessions'] - $scrMarked) }} buổi chưa điểm danh.</p>
                <a href="{{ route('student.classes.show', ['class' => $classRoom->id, 'tab' => 'schedule']) }}" class="scr-link">Xem lịch học & điểm danh từng buổi <x-lucide name="arrow-right" /></a>
            </div>
        </div>
    @endif
</section>

<style>
    .scr { padding: 18px; border: 1px solid #DDEAF0; border-radius: 20px; background: #fff; box-shadow: 0 6px 18px rgba(15, 42, 74, .04); }
    .scr-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .scr-head h2 { margin: 0; font-size: 15px; font-weight: 700; color: #123B68; }
    .scr-head p { margin: 2px 0 0; font-size: 12px; color: #61798B; }
    .scr-ic { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 12px; background: #E8F3EE; color: #176B50; }
    .scr-ic svg { width: 19px; height: 19px; }
    .scr-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    @media (max-width: 900px) { .scr-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .scr-stat { display: flex; flex-direction: column; gap: 2px; padding: 12px 14px; border: 1px solid #E5EDF3; border-radius: 16px; background: #F8FBFD; }
    .scr-stat span { font-size: 12px; font-weight: 600; color: #61798B; }
    .scr-stat b { font-size: 24px; line-height: 1.15; font-weight: 800; color: #123B68; } .scr-stat b small { font-size: 13px; font-weight: 600; color: #7890A0; }
    .scr-stat i { font-style: normal; font-size: 12px; color: #7890A0; }
    .scr-stat em { align-self: flex-start; padding: 2px 9px; border-radius: 999px; font-style: normal; font-size: 12px; font-weight: 700; background: rgba(255, 255, 255, .7); }
    .scr-stat.main { border: 0; color: #fff; background: linear-gradient(135deg, #126F91, #1E8BB3); }
    .scr-stat.main.ok { background: linear-gradient(135deg, #15803D, #22A55B); } .scr-stat.main.good { background: linear-gradient(135deg, #1D4ED8, #3B82F6); }
    .scr-stat.main.mid { background: linear-gradient(135deg, #B45309, #D97706); } .scr-stat.main.low { background: linear-gradient(135deg, #B91C1C, #DC2626); }
    .scr-stat.main span, .scr-stat.main b, .scr-stat.main b small, .scr-stat.main i { color: #fff; } .scr-stat.main i { opacity: .85; } .scr-stat.main em { color: #123B68; }
    .scr-note { display: flex; gap: 10px; margin-top: 12px; padding: 12px 14px; border: 1px solid #DDD6FE; border-radius: 16px; background: #F5F3FF; }
    .scr-note svg { width: 18px; height: 18px; flex: none; margin-top: 1px; color: #7C3AED; }
    .scr-note b { font-size: 13px; color: #4C1D95; } .scr-note p { margin: 3px 0 0; font-size: 13px; line-height: 1.6; color: #3B2A6B; white-space: pre-line; }
    .scr-grid { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 12px; margin-top: 12px; }
    @media (max-width: 1000px) { .scr-grid { grid-template-columns: minmax(0, 1fr); } }
    .scr-card { padding: 14px; border: 1px solid #E5EDF3; border-radius: 16px; background: #fff; align-self: start; }
    .scr-card h3 { margin: 0 0 10px; font-size: 14px; font-weight: 700; color: #123B68; }
    .scr-row { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-top: 1px solid #EEF3F7; }
    .scr-row:first-of-type { border-top: 0; padding-top: 2px; }
    .scr-sc { display: grid; place-items: center; width: 46px; height: 40px; flex: none; border-radius: 12px; font-size: 15px; font-weight: 800; }
    .scr-sc.ok { background: #DCFCE7; color: #15803D; } .scr-sc.good { background: #DBEAFE; color: #1D4ED8; } .scr-sc.mid { background: #FEF3C7; color: #B45309; }
    .scr-sc.low { background: #FEE2E2; color: #DC2626; } .scr-sc.none { background: #F1F5F9; color: #94A3B8; }
    .scr-rm { flex: 1; min-width: 0; }
    .scr-rt { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; } .scr-rt b { font-size: 13px; font-weight: 700; color: #123B68; }
    .scr-rm small { display: block; margin-top: 2px; font-size: 12px; color: #7890A0; }
    .scr-pill { padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .scr-pill.green { background: #DCFCE7; color: #15803D; } .scr-pill.violet { background: #EDE9FE; color: #6D28D9; } .scr-pill.amber { background: #FEF3C7; color: #B45309; }
    .scr-pill.red { background: #FEE2E2; color: #DC2626; } .scr-pill.gray { background: #F1F5F9; color: #64748B; }
    .scr-cm { display: flex; gap: 6px; margin: 6px 0 0; padding: 7px 10px; border-radius: 10px; background: #F5F3FF; font-size: 12px; line-height: 1.5; color: #4C1D95; }
    .scr-cm svg { width: 14px; height: 14px; flex: none; margin-top: 2px; }
    .scr-go { flex: none; align-self: center; padding: 7px 12px; border: 1px solid #CFE0EA; border-radius: 10px; font-size: 12px; font-weight: 700; color: #126F91; text-decoration: none; white-space: nowrap; }
    .scr-go:hover { border-color: #126F91; background: #F4F9FC; }
    .scr-go.pri { border: 0; background: #126F91; color: #fff; } .scr-go.pri:hover { background: #0F5F7C; }
    .scr-att { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .scr-att div { display: flex; flex-direction: column; padding: 10px; border-radius: 12px; }
    .scr-att b { font-size: 20px; font-weight: 800; line-height: 1.1; } .scr-att span { font-size: 12px; font-weight: 600; }
    .scr-att .ok { background: #DCFCE7; color: #15803D; } .scr-att .late { background: #DBEAFE; color: #1D4ED8; }
    .scr-att .ex { background: #FEF3C7; color: #B45309; } .scr-att .no { background: #FEE2E2; color: #DC2626; }
    .scr-hint { margin: 10px 0 0; font-size: 12px; color: #61798B; }
    .scr-link { display: inline-flex; align-items: center; gap: 5px; margin-top: 8px; font-size: 12px; font-weight: 700; color: #126F91; text-decoration: none; }
    .scr-link svg { width: 14px; height: 14px; } .scr-link:hover { text-decoration: underline; }
    .scr-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 30px 12px; border: 1px dashed #D5E1EA; border-radius: 14px; text-align: center; font-size: 12px; color: #61798B; }
    .scr-empty svg { width: 24px; height: 24px; color: #9DC8D7; } .scr-empty b { font-size: 14px; color: #123B68; }
    .scr-empty.sm { padding: 18px 10px; }
</style>
