{{--
    SỬA 30/10 (khách: "thêm tab Nhật ký — admin, giáo viên xem được toàn bộ khảo sát / cuộc thi; học sinh chỉ xem của riêng mình;
    in đúc nhật ký làm bài bên Luyện tập để biết ai xem Hướng dẫn, ai mở tab mới, ai xem Bài mẫu…").

    Tab "Nhật ký" của một kỳ. Dữ liệu tải (JSON) lúc mở tab từ route competitions.activity — App\Services\Contest\
    ContestActivityLogService::forRound(): ban tổ chức nhận MỌI lượt, người khác CHỈ nhận lượt của mình (máy chủ lọc, không phải
    giao diện). CSS riêng tiền tố cal- (máy chủ không build lại Tailwind).
--}}
@php
    $calManager = (bool) ($manager ?? false);
    $calGroups = [
        'tab' => ['Rời tab / cửa sổ', 'external-link'],
        'screenshot' => ['Chụp / quay màn hình', 'camera'],
        'guideOpen' => ['Xem Hướng dẫn', 'book-open'],
        'sampleOpen' => ['Xem Bài mẫu', 'code'],
        'other' => ['Dấu hiệu khác', 'circle-alert'],
    ];
@endphp
<style>
    .cal { color: #123b68; }
    .cal-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
    .cal-head h3 { font-size: 13px; font-weight: 650; margin: 0; }
    .cal-head p { margin: 4px 0 0; font-size: 12px; line-height: 1.6; color: #7e93a3; max-width: 760px; }
    .cal-btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #d6e3ec; background: #fff; color: #126f91; border-radius: 9px; padding: 7px 11px; font-size: 12px; font-weight: 650; cursor: pointer; }
    .cal-btn:hover { background: #eef6fa; }
    .cal-btn svg { width: 14px; height: 14px; }
    .cal-sum { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; margin-bottom: 12px; }
    .cal-sum div { border: 1px solid #e3ecf2; background: #f8fbfd; border-radius: 11px; padding: 9px 11px; }
    .cal-sum span { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #61798b; }
    .cal-sum span svg { width: 13px; height: 13px; }
    .cal-sum b { display: block; margin-top: 3px; font-size: 17px; font-weight: 800; color: #123b68; }
    .cal-sum .is-warn b { color: #b45309; }
    .cal-tools { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
    .cal-tools input, .cal-tools select { height: 36px; border: 1px solid #d6e3ec; border-radius: 9px; padding: 0 10px; font-size: 12px; color: #123b68; background: #fff; }
    .cal-tools input { flex: 1 1 220px; min-width: 0; }
    .cal-scroll { overflow-x: auto; border: 1px solid #e3ecf2; border-radius: 12px; background: #fff; }
    .cal-table { width: 100%; min-width: 860px; border-collapse: collapse; font-size: 12px; }
    .cal-table th { text-align: left; font-size: 11px; font-weight: 700; color: #61798b; background: #f5f9fb; padding: 9px 10px; border-bottom: 1px solid #e3ecf2; white-space: nowrap; }
    .cal-table td { padding: 10px; border-bottom: 1px solid #edf2f6; vertical-align: top; }
    .cal-table tr.is-open > td { background: #f8fbfd; }
    .cal-name { font-weight: 700; color: #123b68; }
    .cal-sub { margin-top: 2px; font-size: 11px; color: #7e93a3; overflow-wrap: anywhere; }
    .cal-me { display: inline-block; margin-left: 5px; padding: 1px 7px; border-radius: 999px; background: #e0f2fe; color: #075985; font-size: 10px; font-weight: 700; vertical-align: 1px; }
    .cal-doing { display: inline-block; padding: 1px 7px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 10px; font-weight: 700; }
    .cal-chips { display: flex; flex-wrap: wrap; gap: 4px; }
    .cal-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; border-radius: 999px; font-size: 11px; font-weight: 650; background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; white-space: nowrap; }
    .cal-chip svg { width: 12px; height: 12px; }
    .cal-chip.is-guide { background: #eef2ff; color: #3730a3; border-color: #c7d2fe; }
    .cal-chip.is-shot { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .cal-clean { display: inline-flex; align-items: center; gap: 4px; color: #15803d; font-size: 11px; font-weight: 650; }
    .cal-clean svg { width: 13px; height: 13px; }
    .cal-pen { margin-top: 3px; font-size: 11px; color: #b45309; font-weight: 650; }
    .cal-open { display: inline-flex; align-items: center; gap: 4px; border: 0; background: none; color: #126f91; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; padding: 0; }
    .cal-open svg { width: 14px; height: 14px; }
    .cal-tl { padding: 4px 2px 2px; }
    .cal-tl-filters { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 8px; }
    .cal-tl-filters button { border: 1px solid #d6e3ec; background: #fff; color: #456a83; border-radius: 999px; padding: 3px 10px; font-size: 11px; font-weight: 650; cursor: pointer; }
    .cal-tl-filters button[aria-pressed="true"] { background: #126f91; border-color: #126f91; color: #fff; }
    .cal-tl ol { list-style: none; margin: 0; padding: 0; max-height: 360px; overflow-y: auto; border-left: 2px solid #dbe7ef; }
    .cal-tl li { position: relative; padding: 6px 8px 6px 16px; }
    .cal-tl li::before { content: ""; position: absolute; left: -6px; top: 11px; width: 10px; height: 10px; border-radius: 999px; background: #cbd9e3; border: 2px solid #fff; }
    .cal-tl li.is-signal::before { background: #f97316; }
    .cal-tl li.is-guide::before { background: #6366f1; }
    .cal-tl li.is-shot::before { background: #dc2626; }
    .cal-tl-time { font-size: 11px; color: #7e93a3; font-variant-numeric: tabular-nums; }
    .cal-tl-title { font-weight: 700; color: #123b68; }
    .cal-tl-detail { font-size: 11px; color: #61798b; }
    .cal-tl-q { display: inline-block; margin-left: 4px; padding: 0 6px; border-radius: 6px; background: #eef4f8; color: #456a83; font-size: 10.5px; font-weight: 650; }
    .cal-empty { padding: 26px 14px; text-align: center; color: #7e93a3; font-size: 12px; }
    .cal-empty b { display: block; color: #123b68; font-size: 13px; margin-bottom: 4px; }
    .cal-pg { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-top: 10px; font-size: 11.5px; color: #61798b; }
    .cal-pg div { display: flex; gap: 5px; }
    .cal-pg button { min-width: 32px; height: 30px; border: 1px solid #d6e3ec; background: #fff; border-radius: 8px; color: #126f91; font-weight: 700; cursor: pointer; }
    .cal-pg button:disabled { opacity: .4; cursor: default; }
    .cal-note { margin-top: 10px; font-size: 11px; line-height: 1.6; color: #7e93a3; }
</style>

<div class="cal" x-data="contestActivityLog({{ Js::from(['url' => $round['activityUrl'], 'manager' => $calManager]) }})" x-effect="if (tab === 'activity') load()">
    <div class="cal-head">
        <div>
            <h3>{{ $calManager ? 'Nhật ký làm bài' : 'Nhật ký làm bài của tôi' }}</h3>
            <p>
                @if ($calManager)
                    Mọi lượt làm bài của học sinh ở {{ $round['label'] }}: ai rời tab / mở cửa sổ khác, chụp màn hình, xem Hướng dẫn, xem Bài mẫu… kèm thời điểm và câu đang làm.
                @else
                    Các lượt làm bài của bạn ở {{ $round['label'] }} — chỉ bạn và ban tổ chức xem được.
                @endif
            </p>
        </div>
        <button type="button" class="cal-btn" @click="load(true)" :disabled="loading"><x-lucide name="refresh-cw" /><span x-text="loading ? 'Đang tải…' : 'Tải lại'"></span></button>
    </div>

    <template x-if="error">
        <div class="cal-empty"><b>Chưa tải được nhật ký</b><span x-text="error"></span></div>
    </template>
    <template x-if="loading && !loaded">
        <div class="cal-empty">Đang tải nhật ký…</div>
    </template>

    <div x-show="loaded && !error" x-cloak>
        {{-- Tổng quan --}}
        <div class="cal-sum">
            <div><span><x-lucide name="history" />Lượt làm bài</span><b x-text="rows.length"></b></div>
            <div :class="flaggedCount ? 'is-warn' : ''"><span><x-lucide name="circle-alert" />Lượt có dấu hiệu</span><b x-text="flaggedCount"></b></div>
            @foreach ($calGroups as $key => [$label, $icon])
                @if ($key !== 'other')
                    <div :class="total('{{ $key }}') ? 'is-warn' : ''"><span><x-lucide :name="$icon" />{{ $label }}</span><b x-text="total('{{ $key }}')"></b></div>
                @endif
            @endforeach
        </div>

        <div class="cal-tools">
            @if ($calManager)
                <input type="search" x-model="q" placeholder="Tìm theo tên, tài khoản hoặc tên đề…" aria-label="Tìm học sinh">
            @endif
            <select x-model="filter" aria-label="Lọc theo dấu hiệu">
                <option value="all">Tất cả lượt</option>
                <option value="flagged">Có dấu hiệu</option>
                <option value="clean">Không có dấu hiệu</option>
                @foreach ($calGroups as $key => [$label, $icon])
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
                <option value="doing">Đang làm (chưa nộp)</option>
            </select>
            <select x-model="sort" aria-label="Sắp xếp">
                <option value="recent">Mới nộp trước</option>
                <option value="signals">Nhiều dấu hiệu nhất</option>
                @if ($calManager)<option value="name">Theo tên A → Z</option>@endif
            </select>
        </div>

        <template x-if="!filtered.length">
            <div class="cal-empty">
                <b x-text="rows.length ? 'Không có lượt nào khớp bộ lọc' : 'Chưa có lượt làm bài nào'"></b>
                <span x-text="rows.length ? 'Thử đổi bộ lọc hoặc từ khoá.' : 'Nhật ký xuất hiện sau khi học sinh vào làm và nộp bài.'"></span>
            </div>
        </template>

        <div class="cal-scroll" x-show="filtered.length">
            <table class="cal-table">
                <thead>
                    <tr>
                        <th>{{ $calManager ? 'Học sinh' : 'Lượt làm' }}</th>
                        <th>Đề</th>
                        <th>Thời gian</th>
                        <th>Điểm</th>
                        <th>Dấu hiệu</th>
                        <th></th>
                    </tr>
                </thead>
                {{-- Mỗi lượt là một <tbody> (dòng chính + dòng nhật ký mở rộng). --}}
                    <template x-for="r in pageRows" :key="r.id">
                        <tbody>
                            <tr :class="openId === r.id ? 'is-open' : ''">
                                <td>
                                    <div class="cal-name"><span x-text="r.name"></span><span class="cal-me" x-show="r.mine && manager">Bạn</span></div>
                                    <div class="cal-sub" x-text="r.account" x-show="r.account"></div>
                                </td>
                                <td><div x-text="r.paper || '—'"></div></td>
                                <td>
                                    <template x-if="r.state === 'doing'"><span class="cal-doing">Đang làm</span></template>
                                    <div class="cal-sub" x-show="r.startedAt">Bắt đầu: <span x-text="r.startedAt"></span></div>
                                    <div class="cal-sub" x-show="r.submittedAt">Nộp: <span x-text="r.submittedAt"></span></div>
                                    <div class="cal-sub" x-show="r.duration">Làm trong <span x-text="r.duration"></span></div>
                                </td>
                                <td>
                                    <div class="cal-name" x-text="r.state === 'doing' ? '—' : (r.pending ? 'Đang chấm' : fmt(r.score) + (r.maxScore ? ' / ' + fmt(r.maxScore) : ''))"></div>
                                    <div class="cal-pen" x-show="r.penaltyPercent > 0" title="Chụp màn hình trừ 30%, xem Hướng dẫn / Bài mẫu trừ 20% điểm của câu đang làm lúc đó">Đã trừ điểm theo nhật ký</div>
                                </td>
                                <td>
                                    <div class="cal-chips" x-show="r.signalTotal">
                                        @foreach ($calGroups as $key => [$label, $icon])
                                            <span class="cal-chip {{ in_array($key, ['guideOpen', 'sampleOpen'], true) ? 'is-guide' : ($key === 'screenshot' ? 'is-shot' : '') }}" x-show="r.signals['{{ $key }}']"><x-lucide :name="$icon" />{{ $label }} <b x-text="r.signals['{{ $key }}']"></b></span>
                                        @endforeach
                                    </div>
                                    <span class="cal-clean" x-show="!r.signalTotal && r.state !== 'doing'"><x-lucide name="check" />Không có dấu hiệu</span>
                                    <span class="cal-sub" x-show="r.state === 'doing'">Nhật ký gửi lên khi nộp bài</span>
                                </td>
                                <td style="text-align:right">
                                    <button type="button" class="cal-open" x-show="r.eventCount" @click="toggle(r.id)" :aria-expanded="openId === r.id ? 'true' : 'false'">
                                        <span x-text="(openId === r.id ? 'Ẩn' : 'Xem') + ' ' + r.eventCount + ' mốc'"></span>
                                        <span x-show="openId !== r.id"><x-lucide name="chevron-down" /></span><span x-show="openId === r.id"><x-lucide name="chevron-up" /></span>
                                    </button>
                                </td>
                            </tr>
                            <tr x-show="openId === r.id" class="is-open">
                                <td colspan="6">
                                    <div class="cal-tl">
                                        <div class="cal-tl-filters" role="group" aria-label="Lọc mốc hoạt động">
                                            <button type="button" :aria-pressed="tlFilter === 'all' ? 'true' : 'false'" @click="tlFilter = 'all'">Tất cả <b x-text="r.events.length"></b></button>
                                            <button type="button" :aria-pressed="tlFilter === 'signal' ? 'true' : 'false'" @click="tlFilter = 'signal'" x-show="r.signalTotal">Chỉ dấu hiệu <b x-text="r.signalTotal"></b></button>
                                            @foreach ($calGroups as $key => [$label, $icon])
                                                <button type="button" x-show="r.signals['{{ $key }}']" :aria-pressed="tlFilter === '{{ $key }}' ? 'true' : 'false'" @click="tlFilter = '{{ $key }}'">{{ $label }} <b x-text="r.signals['{{ $key }}']"></b></button>
                                            @endforeach
                                        </div>
                                        <p class="cal-sub" x-show="r.eventCount > r.events.length">Chỉ hiện <span x-text="r.events.length"></span> mốc mới nhất trong tổng số <span x-text="r.eventCount"></span>.</p>
                                        <ol>
                                            <template x-for="e in timeline(r)" :key="e.id + e.time">
                                                <li :class="e.group === 'guideOpen' || e.group === 'sampleOpen' ? 'is-guide' : (e.group === 'screenshot' ? 'is-shot' : (e.kind === 'signal' ? 'is-signal' : ''))">
                                                    <div class="cal-tl-time"><span x-text="e.time || ''"></span> <span x-text="e.date || ''"></span></div>
                                                    <div class="cal-tl-title"><span x-text="e.title"></span><span class="cal-tl-q" x-show="e.question" x-text="e.question"></span></div>
                                                    <div class="cal-tl-detail" x-show="e.detail" x-text="e.detail"></div>
                                                </li>
                                            </template>
                                        </ol>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </template>
            </table>
        </div>

        <div class="cal-pg" x-show="filtered.length > PER">
            <span>Trang <b x-text="page"></b> / <b x-text="pages"></b> · <span x-text="filtered.length"></span> lượt</span>
            <div>
                <button type="button" @click="page = Math.max(1, page - 1)" :disabled="page <= 1" aria-label="Trang trước">‹</button>
                <button type="button" @click="page = Math.min(pages, page + 1)" :disabled="page >= pages" aria-label="Trang sau">›</button>
            </div>
        </div>

        <p class="cal-note">
            Nhật ký do trình duyệt ghi trong lúc làm bài và gửi lên khi nộp bài, nên lượt đang làm chưa có nhật ký. Đề dạng PDF chưa ghi nhật ký.
            <span x-show="truncated">Chỉ hiện 500 lượt mới nhất.</span>
        </p>
    </div>
</div>

@once
<script>
    // SỬA 30/10 — tab Nhật ký của kỳ thi / khảo sát. Dữ liệu (đã lọc quyền ở máy chủ) tải 1 lần khi mở tab; bấm "Tải lại" để lấy mới.
    function contestActivityLog(cfg) {
        return {
            url: cfg.url, manager: !!cfg.manager, PER: 20,
            loading: false, loaded: false, error: '', rows: [], truncated: false,
            q: '', filter: 'all', sort: 'recent', page: 1, openId: null, tlFilter: 'all',
            init() {
                ['q', 'filter', 'sort'].forEach((k) => this.$watch(k, () => { this.page = 1; this.openId = null; }));
            },
            load(force) {
                if (this.loading || (this.loaded && !force) || !this.url) return;
                this.loading = true; this.error = '';
                fetch(this.url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((r) => r.ok ? r.json() : Promise.reject(r.status))
                    .then((j) => { this.rows = Array.isArray(j.rows) ? j.rows : []; this.truncated = !!j.truncated; this.loaded = true; })
                    .catch((s) => { this.error = s === 401 || s === 419 ? 'Phiên đăng nhập đã hết hạn — tải lại trang.' : 'Lỗi kết nối hoặc máy chủ — bấm Tải lại để thử lại.'; })
                    .finally(() => { this.loading = false; });
            },
            norm(s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[đĐ]/g, 'd').toLowerCase(); },
            get flaggedCount() { return this.rows.filter((r) => r.signalTotal > 0).length; },
            total(key) { return this.rows.reduce((n, r) => n + ((r.signals && r.signals[key]) || 0), 0); },
            get filtered() {
                const q = this.norm(this.q.trim());
                let list = this.rows.filter((r) => {
                    if (q && !this.norm(r.name + ' ' + r.account + ' ' + r.paper).includes(q)) return false;
                    switch (this.filter) {
                        case 'all': return true;
                        case 'flagged': return r.signalTotal > 0;
                        case 'clean': return r.signalTotal === 0 && r.state !== 'doing';
                        case 'doing': return r.state === 'doing';
                        default: return (r.signals[this.filter] || 0) > 0;
                    }
                });
                if (this.sort === 'signals') list = list.slice().sort((a, b) => (b.signalTotal - a.signalTotal) || (b.submittedTs - a.submittedTs));
                if (this.sort === 'name') list = list.slice().sort((a, b) => a.name.localeCompare(b.name, 'vi'));
                return list;
            },
            get pages() { return Math.max(1, Math.ceil(this.filtered.length / this.PER)); },
            get pageRows() { const p = Math.min(this.page, this.pages); return this.filtered.slice((p - 1) * this.PER, p * this.PER); },
            toggle(id) { this.openId = this.openId === id ? null : id; this.tlFilter = 'all'; },
            timeline(r) {
                const f = this.tlFilter;
                if (f === 'all') return r.events;
                if (f === 'signal') return r.events.filter((e) => e.kind === 'signal' || ['tab', 'screenshot', 'guideOpen', 'sampleOpen', 'other'].includes(e.group));
                return r.events.filter((e) => e.group === f);
            },
            fmt(n) { return n === null || n === undefined ? '—' : String(Math.round(Number(n) * 100) / 100).replace('.', ','); },
        };
    }
</script>
@endonce
