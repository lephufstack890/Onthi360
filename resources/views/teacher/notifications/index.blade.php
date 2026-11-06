@extends('layouts.teacher')

@section('title', 'Hộp thư')
@section('page-title', 'Hộp thư')

@section('content')
    {{--
      SỬA 6/11 (khách: "bỏ tab Thông báo lớp, chỉ để Hộp thư — thiết kế lại đẹp, bố cục hài hoà, có phân trang") — trang chỉ còn
      Hộp thư: đầu khung (số chưa đọc, lọc Tất cả / Chưa đọc, Đánh dấu đã đọc tất cả), danh sách gom theo ngày, mỗi thư bấm để mở
      (tự đánh dấu đã đọc), nút ✓ đánh dấu đã đọc từng thư, phân trang 10 thư / trang (máy chủ). Gửi thông báo cho lớp: vào lớp →
      tab "Thông báo". Bản cũ (có tab Thông báo lớp) lưu ở _to_delete_claude/teacher-inbox/. CSS tiền tố tib-.
    --}}
    @php
        $items = $items ?? [];
        $unreadCount = (int) ($unreadCount ?? 0);
        $totalCount = (int) ($totalCount ?? 0);
        $filter = $filter ?? 'all';
        $groups = collect($items)->groupBy('group');
        $cur = $page->currentPage();
        $last = $page->lastPage();
        $pageNums = $last <= 7 ? range(1, $last) : collect([1, $cur - 1, $cur, $cur + 1, $last])->filter(fn ($x) => $x >= 1 && $x <= $last)->unique()->sort()->values()->all();
        $tibToneBg = ['success' => '#E7F7EE', 'warning' => '#FFF4DE', 'danger' => '#FEECEC', 'info' => '#EAF2FF', 'neutral' => '#EEF2F7'];
    @endphp

    <div class="tw-dashboard" data-section="TEACHER-INBOX">
        @include('teacher._hero')

        <section class="tib">
            <header class="tib-head">
                <span class="tib-hic"><x-lucide name="bell" />@if ($unreadCount > 0)<i>{{ $unreadCount > 99 ? '99+' : $unreadCount }}</i>@endif</span>
                <div class="tib-htx">
                    <h2>Hộp thư</h2>
                    <p>{{ $unreadCount > 0 ? "Bạn có {$unreadCount} thông báo chưa đọc." : 'Bạn đã xem hết thông báo — tuyệt vời!' }}</p>
                </div>
                <div class="tib-tools">
                    <nav class="tib-seg" aria-label="Lọc thông báo">
                        <a href="{{ route('teacher.notifications.index') }}" @class(['on' => $filter === 'all'])>Tất cả<em>{{ $totalCount }}</em></a>
                        <a href="{{ route('teacher.notifications.index', ['loc' => 'unread']) }}" @class(['on' => $filter === 'unread'])>Chưa đọc<em @class(['hot' => $unreadCount > 0])>{{ $unreadCount }}</em></a>
                    </nav>
                    @if ($unreadCount > 0)
                        <form method="POST" action="{{ route('notifications.readAll') }}">
                            @csrf
                            <button type="submit" class="tib-allread"><x-lucide name="check-circle-2" /><span>Đọc tất cả</span></button>
                        </form>
                    @endif
                </div>
            </header>

            <div class="tib-tip"><x-lucide name="megaphone" /><span>Muốn gửi thông báo cho học sinh? Vào <a href="{{ route('teacher.classes.index') }}">Lớp học</a> → chọn lớp → tab <b>Thông báo</b>.</span></div>

            @if (empty($items))
                <div class="tib-empty">
                    <span><x-lucide name="{{ $filter === 'unread' ? 'check-circle-2' : 'bell' }}" /></span>
                    <b>{{ $filter === 'unread' ? 'Không còn thông báo chưa đọc' : 'Chưa có thông báo nào' }}</b>
                    <p>{{ $filter === 'unread' ? 'Bạn đã đọc hết mọi thông báo.' : 'Thông báo từ hệ thống, lớp học và quản trị sẽ hiện ở đây.' }}</p>
                    @if ($filter === 'unread' && $totalCount > 0)<a href="{{ route('teacher.notifications.index') }}">Xem tất cả thông báo</a>@endif
                </div>
            @else
                <div class="tib-list">
                    @foreach ($groups as $label => $rows)
                        <div class="tib-day">{{ $label }}</div>
                        @foreach ($rows as $n)
                            <article @class(['tib-row', 'unread' => ! $n['read']])>
                                <a class="tib-main" href="{{ route('notifications.read', $n['id']) }}">
                                    <span class="tib-ic" style="background: {{ $tibToneBg[$n['tone']] ?? $tibToneBg['neutral'] }}">{{ $n['icon'] }}</span>
                                    <span class="tib-tx">
                                        <span class="tib-t"><b>{{ $n['title'] }}</b>@if (! $n['read'])<em>Mới</em>@endif</span>
                                        @if ($n['text'] !== '' && $n['text'] !== $n['title'])<span class="tib-p">{{ $n['text'] }}</span>@endif
                                        <span class="tib-meta">
                                            <span title="{{ $n['at'] }}"><x-lucide name="clock-3" />{{ $n['ago'] }}</span>
                                            @if ($n['from'] !== '')<span><x-lucide name="user-round" />{{ $n['from'] }}</span>@endif
                                        </span>
                                    </span>
                                </a>
                                <div class="tib-act">
                                    @if (! $n['read'])
                                        <form method="POST" action="{{ route('notifications.markRead', $n['id']) }}">
                                            @csrf
                                            <button type="submit" class="tib-mark" title="Đánh dấu đã đọc" aria-label="Đánh dấu đã đọc"><x-lucide name="check" /></button>
                                        </form>
                                    @endif
                                    <a class="tib-go" href="{{ route('notifications.read', $n['id']) }}" aria-label="Mở thông báo"><x-lucide name="chevron-right" /></a>
                                </div>
                            </article>
                        @endforeach
                    @endforeach
                </div>

                @if ($last > 1)
                    <div class="tib-pager">
                        <span class="tib-range">Hiển thị {{ $page->firstItem() }}–{{ $page->lastItem() }} / {{ $page->total() }} thông báo</span>
                        <nav class="tib-pgs" aria-label="Phân trang hộp thư">
                            @if ($cur > 1)<a class="tib-pg" href="{{ $page->url($cur - 1) }}" aria-label="Trang trước"><x-lucide name="chevron-left" /></a>@else<span class="tib-pg off"><x-lucide name="chevron-left" /></span>@endif
                            @php $prev = null; @endphp
                            @foreach ($pageNums as $num)
                                @if ($prev !== null && $num - $prev > 1)<span class="tib-pg dots">…</span>@endif
                                <a @class(['tib-pg', 'on' => $num === $cur]) href="{{ $page->url($num) }}" @if ($num === $cur) aria-current="page" @endif>{{ $num }}</a>
                                @php $prev = $num; @endphp
                            @endforeach
                            @if ($cur < $last)<a class="tib-pg" href="{{ $page->url($cur + 1) }}" aria-label="Trang sau"><x-lucide name="chevron-right" /></a>@else<span class="tib-pg off"><x-lucide name="chevron-right" /></span>@endif
                        </nav>
                    </div>
                @endif
            @endif
        </section>
    </div>

    <style>
        .tib { padding: 20px; border: 1px solid #E3ECF3; border-radius: 20px; background: #fff; box-shadow: 0 6px 20px rgba(15, 42, 74, .05); }
        .tib-head { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 16px; }
        .tib-hic { position: relative; display: grid; place-items: center; width: 48px; height: 48px; flex: none; border-radius: 15px; background: linear-gradient(135deg, #1F8A63, #176B50); color: #fff; box-shadow: 0 10px 22px rgba(23, 107, 80, .28); }
        .tib-hic svg { width: 22px; height: 22px; }
        .tib-hic i { position: absolute; top: -6px; right: -6px; min-width: 20px; height: 20px; padding: 0 5px; border: 2px solid #fff; border-radius: 999px; background: #EF4444; font-style: normal; font-size: 10.5px; font-weight: 800; line-height: 16px; text-align: center; }
        .tib-htx { flex: 1 1 220px; min-width: 0; }
        .tib-htx h2 { margin: 0; font-size: 17px; font-weight: 800; color: #0F2F57; }
        .tib-htx p { margin: 2px 0 0; font-size: 12.5px; color: #64748B; }
        .tib-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .tib-seg { display: inline-flex; gap: 4px; padding: 4px; border-radius: 13px; background: #EEF3F6; }
        .tib-seg a { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 14px; border-radius: 10px; font-size: 12.5px; font-weight: 700; color: #4B6476; text-decoration: none; transition: all .15s; }
        .tib-seg a:hover { color: #0F2F57; }
        .tib-seg a.on { background: #fff; color: #0F2F57; box-shadow: 0 2px 8px rgba(15, 42, 74, .1); }
        .tib-seg em { min-width: 20px; padding: 1px 7px; border-radius: 999px; background: rgba(255, 255, 255, .85); font-style: normal; font-size: 11px; font-weight: 800; color: #64748B; text-align: center; }
        .tib-seg a.on em { background: #E8F3EE; color: #176B50; }
        .tib-seg em.hot { background: #FEE2E2; color: #DC2626; }
        .tib-allread { display: inline-flex; align-items: center; gap: 6px; height: 42px; padding: 0 14px; border: 1px solid #CDE5DA; border-radius: 12px; background: #F1FAF5; font: inherit; font-size: 12.5px; font-weight: 800; color: #176B50; cursor: pointer; transition: all .15s; }
        .tib-allread svg { width: 16px; height: 16px; }
        .tib-allread:hover { background: #176B50; border-color: #176B50; color: #fff; }
        .tib-tip { display: flex; align-items: flex-start; gap: 8px; margin: 16px 0 6px; padding: 10px 12px; border-radius: 12px; background: #F5F9FF; border: 1px solid #E0EBFA; font-size: 12.5px; line-height: 1.5; color: #46607A; }
        .tib-tip svg { width: 15px; height: 15px; flex: none; margin-top: 2px; color: #EA580C; }
        .tib-tip a { color: #1D4ED8; font-weight: 800; text-decoration: none; } .tib-tip a:hover { text-decoration: underline; }
        .tib-list { display: grid; gap: 8px; }
        .tib-day { margin: 10px 2px 0; font-size: 11.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #94A3B8; }
        .tib-row { position: relative; display: flex; align-items: center; gap: 8px; padding: 4px 8px 4px 4px; border: 1px solid #E8EEF4; border-radius: 16px; background: #fff; transition: border-color .15s, box-shadow .15s, background .15s; }
        .tib-row:hover { border-color: #C9DCEA; box-shadow: 0 8px 20px rgba(15, 42, 74, .07); }
        .tib-row.unread { border-color: #CFE7DC; background: linear-gradient(90deg, #F2FBF6, #FFFFFF 55%); }
        .tib-row.unread::before { content: ''; position: absolute; left: -1px; top: 14px; bottom: 14px; width: 4px; border-radius: 0 4px 4px 0; background: #1F8A63; }
        .tib-main { display: flex; align-items: flex-start; gap: 12px; flex: 1; min-width: 0; padding: 10px 6px 10px 12px; color: inherit; text-decoration: none; }
        .tib-ic { display: grid; place-items: center; width: 42px; height: 42px; flex: none; border-radius: 13px; font-size: 19px; }
        .tib-tx { display: grid; gap: 3px; flex: 1; min-width: 0; }
        .tib-t { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .tib-t b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13.5px; font-weight: 700; color: #1E293B; }
        .tib-row.unread .tib-t b { font-weight: 800; color: #0F172A; }
        .tib-t em { flex: none; padding: 1px 8px; border-radius: 999px; background: #1F8A63; font-style: normal; font-size: 10.5px; font-weight: 800; color: #fff; }
        .tib-p { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 12.5px; line-height: 1.55; color: #5B6F82; word-break: break-word; }
        .tib-meta { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 2px; }
        .tib-meta span { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: #94A3B8; }
        .tib-meta svg { width: 13px; height: 13px; }
        .tib-act { display: flex; align-items: center; gap: 6px; flex: none; }
        .tib-act form { margin: 0; }
        .tib-mark, .tib-go { display: grid; place-items: center; width: 34px; height: 34px; border: 1px solid #E2E8F0; border-radius: 10px; background: #fff; color: #64748B; cursor: pointer; text-decoration: none; transition: all .15s; }
        .tib-mark svg, .tib-go svg { width: 16px; height: 16px; }
        .tib-mark:hover { border-color: #A7D7B8; background: #E6F4EA; color: #1F7A55; }
        .tib-go { border-color: transparent; background: none; }
        .tib-row:hover .tib-go { color: #176B50; }
        .tib-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; margin-top: 12px; padding: 40px 16px; border: 1px dashed #D5E1EA; border-radius: 16px; background: #FAFCFD; text-align: center; }
        .tib-empty > span { display: grid; place-items: center; width: 54px; height: 54px; border-radius: 16px; background: #E8F3EE; color: #176B50; }
        .tib-empty > span svg { width: 24px; height: 24px; }
        .tib-empty b { margin-top: 4px; font-size: 14px; color: #0F2F57; }
        .tib-empty p { margin: 0; font-size: 12.5px; color: #7B8EA0; }
        .tib-empty a { margin-top: 4px; font-size: 12.5px; font-weight: 800; color: #176B50; }
        .tib-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #EEF2F7; }
        .tib-range { font-size: 12px; font-weight: 600; color: #94A3B8; }
        .tib-pgs { display: flex; flex-wrap: wrap; gap: 6px; }
        .tib-pg { display: grid; place-items: center; min-width: 36px; height: 36px; padding: 0 8px; border: 1px solid #DCE6F0; border-radius: 10px; background: #fff; font-size: 13px; font-weight: 700; color: #475569; text-decoration: none; transition: all .15s; }
        .tib-pg svg { width: 16px; height: 16px; }
        a.tib-pg:hover { border-color: #9CCBB4; color: #176B50; }
        .tib-pg.on { border-color: #176B50; background: #176B50; color: #fff; box-shadow: 0 4px 12px rgba(23, 107, 80, .25); }
        .tib-pg.off { opacity: .4; } .tib-pg.dots { border-color: transparent; background: none; }
        @media (max-width: 640px) {
            .tib { padding: 14px; }
            .tib-tools { width: 100%; } .tib-seg { flex: 1; } .tib-seg a { flex: 1; justify-content: center; }
            .tib-allread span { display: none; } .tib-allread { width: 42px; justify-content: center; padding: 0; }
            .tib-main { gap: 10px; padding: 10px 4px 10px 10px; } .tib-ic { width: 38px; height: 38px; font-size: 17px; }
            .tib-go { display: none; }
            .tib-pager { justify-content: center; } .tib-range { width: 100%; text-align: center; }
            .tib-pgs { flex-wrap: nowrap; gap: 4px; } .tib-pg { min-width: 32px; height: 34px; padding: 0 6px; }
        }
    </style>
@endsection
