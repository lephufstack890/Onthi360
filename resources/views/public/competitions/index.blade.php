@extends('layouts.guest')

@section('title', 'Cuộc thi & Khảo sát năng lực')
@section('meta-description', 'Cuộc thi và khảo sát năng lực trên Ôn Thi 360 — xem các kỳ thi, đăng ký tham gia hoặc vào khảo sát bằng mật khẩu, theo dõi kết quả của bạn.')

@push('head')
    {{-- SỬA 24/10 — CSS riêng của trang cuộc thi (chép từ source UI mới), không build lại Tailwind. --}}
    <link rel="stylesheet" href="{{ asset('css/contest-hub.css') }}?v={{ @filemtime(public_path('css/contest-hub.css')) }}">
@endpush

@section('content')
@php
    /*
     * SỬA 24/10 (khách: "làm lại trang cuộc thi theo UI mới") — dựng theo ContestsPage.jsx của source mới:
     * hero 2 con số, bộ lọc (tên / lộ trình / loại sự kiện), lưới thẻ 4 cột. Dữ liệu: ContestHubService::catalogData().
     * Lọc ngay trên trình duyệt (danh sách ≤ 200 sự kiện), không tải lại trang.
     */
    $cards = $cards ?? [];
    $roadmaps = $roadmaps ?? [];
    $filterRows = array_map(fn ($c) => ['id' => $c['id'], 'type' => $c['type'], 'roadmap' => $c['roadmap']['id'] ?? null, 'title' => $c['title']], $cards);
@endphp

<div class="materials-page contests-catalog-page cc-page"
     x-data="contestCatalog({{ Js::from(['rows' => $filterRows, 'type' => $initialType ?? 'all']) }})">

    @if ($errors->any())
        <div role="alert" class="cc-alert">{{ $errors->first() }}</div>
    @endif

    {{-- ══ Hero ══ --}}
    <header class="material-hero cc-hero">
        <img src="{{ asset('assets/hero-contests.jpg') }}" alt="" class="cc-hero-bg">
        <div class="cc-hero-inner">
            <div class="cc-hero-copy">
                <span class="cc-kicker"><x-lucide name="trophy" />Không gian thi Ôn Thi 360</span>
                <h1>Cuộc thi &amp; Khảo sát năng lực</h1>
                <p>Chọn cuộc thi phù hợp, xem các kỳ thi và theo dõi kết quả của bạn.</p>
                <div class="cc-hero-tags">
                    <span><x-lucide name="user-check" />Cuộc thi · Duyệt đăng ký · 1 lượt/kỳ</span>
                    <span><x-lucide name="key-round" />Khảo sát · Mật khẩu · Nhiều lượt</span>
                </div>
            </div>
            <div class="cc-hero-stats">
                <div><b>{{ $contestCount ?? 0 }}</b><span>Cuộc thi</span></div>
                <div><b class="is-amber">{{ $surveyCount ?? 0 }}</b><span>Khảo sát năng lực</span></div>
            </div>
        </div>
    </header>

    {{-- ══ Bộ lọc ══ --}}
    <section aria-label="Bộ lọc cuộc thi" class="cc-filter">
        <div class="contest-catalog-filter-grid">
            <label class="contest-catalog-search" style="min-width:0">
                <span class="cc-label">Tìm theo tên</span>
                <span class="cc-search-wrap">
                    <x-lucide name="search" />
                    <input type="search" class="material-input pl-9" aria-label="Tìm cuộc thi hoặc khảo sát" placeholder="Nhập tên cuộc thi hoặc khảo sát…" x-model="query">
                </span>
            </label>
            <label style="min-width:0">
                <span class="cc-label">Lộ trình</span>
                <select class="material-input" aria-label="Lộ trình" x-model="roadmap">
                    <option value="all">Tất cả lộ trình</option>
                    @foreach ($roadmaps as $r)
                        <option value="{{ $r['id'] }}">{{ $r['title'] }}</option>
                    @endforeach
                </select>
            </label>
            <label style="min-width:0">
                <span class="cc-label">Loại sự kiện</span>
                <select class="material-input" aria-label="Loại sự kiện" x-model="type">
                    <option value="all">Tất cả loại</option>
                    <option value="contest">Cuộc thi</option>
                    <option value="survey">Khảo sát năng lực</option>
                </select>
            </label>
        </div>
        <button type="button" class="material-text-button cc-reset" x-show="hasFilters" x-cloak @click="reset()"><x-lucide name="rotate-ccw" />Xóa bộ lọc</button>
    </section>

    {{-- ══ Danh sách ══ --}}
    <section aria-label="Danh sách cuộc thi và khảo sát" style="display:flex;flex-direction:column;gap:16px;min-width:0;scroll-margin-top:72px" x-ref="list">
        <div class="cc-list-head">
            <div>
                <h2 x-text="type === 'survey' ? 'Danh sách khảo sát năng lực' : (type === 'contest' ? 'Danh sách cuộc thi' : 'Danh sách cuộc thi & khảo sát')">Danh sách cuộc thi &amp; khảo sát</h2>
                <p role="status"><span x-text="visibleCount">{{ count($cards) }}</span> / {{ count($cards) }} sự kiện phù hợp<span x-show="totalPages > 1" x-cloak> · Hiển thị <b x-text="rangeLabel"></b></span> · Chọn sự kiện để xem các kỳ thi.</p>
            </div>
        </div>

        <div class="cc-grid">
            @foreach ($cards as $c)
                @php
                    $stateClass = match ($c['state']) { 'current' => 'material-status-opened', 'upcoming' => 'material-status-todo', default => 'material-status-neutral' };
                    $regClass = match ($c['registration']['tone']) { 'opened' => 'material-status-opened', 'todo' => 'material-status-todo', default => 'material-status-neutral' };
                @endphp
                <article class="material-card contest-event-card" x-show="isVisible({{ $c['id'] }})">
                    <a href="{{ $c['url'] }}" class="material-card-cover cc-card-cover" aria-label="Xem {{ $c['isSurvey'] ? 'khảo sát' : 'cuộc thi' }}: {{ $c['title'] }}">
                        <img src="{{ $c['image'] }}" alt="" loading="lazy" decoding="async">
                        <span class="material-status {{ $stateClass }}">{{ $c['stateLabel'] }}</span>
                    </a>
                    <div class="cc-card-body">
                        <div class="contest-type-badge {{ $c['isSurvey'] ? 'is-survey' : 'is-contest' }}">
                            <x-lucide :name="$c['isSurvey'] ? 'key-round' : 'trophy'" style="width:14px;height:14px" />{{ $c['isSurvey'] ? 'Khảo sát năng lực' : 'Cuộc thi' }}
                        </div>
                        <h3><a href="{{ $c['url'] }}">{{ $c['title'] }}</a></h3>
                        @if ($c['edition'] !== '')<p class="cc-edition">{{ $c['edition'] }}</p>@endif
                        @if ($c['roadmap'])
                            <a class="contest-roadmap" href="{{ $c['roadmap']['href'] }}"><span>Lộ trình học</span><b>{{ $c['roadmap']['title'] }}<x-lucide name="chevron-right" style="width:13px;height:13px" /></b></a>
                        @endif
                        <p class="cc-desc">{{ $c['description'] }}</p>
                        <p class="cc-organizer"><x-lucide name="shield-check" />{{ $c['organizer'] }}</p>
                        <div class="cc-facts">
                            <div><x-lucide name="list-checks" /><b>{{ $c['roundsCount'] }} {{ $c['isSurvey'] ? 'kỳ khảo sát' : 'kỳ thi' }}</b><small>Cấu trúc</small></div>
                            <div><x-lucide name="clock-3" /><b>{{ $c['duration'] }}</b><small>Mỗi kỳ thi</small></div>
                            <div><x-lucide name="rotate-ccw" class="is-green" /><b>{{ $c['isSurvey'] ? 'Nhiều lần' : '1 lần' }}</b><small>Lượt nộp / kỳ</small></div>
                        </div>
                        <div class="cc-dates">
                            <p><span><x-lucide name="calendar-days" />Bắt đầu</span><b>{{ $c['startLabel'] }}</b></p>
                            <p><span><x-lucide name="clock-3" />Kết thúc</span><b>{{ $c['endLabel'] }}</b></p>
                        </div>
                        <div class="cc-card-foot">
                            <span class="material-status {{ $regClass }}"><x-lucide :name="$c['registration']['icon']" />{{ $c['registration']['label'] }}</span>
                            <p>{{ $c['isSurvey'] ? 'Nhập mật khẩu · Lưu kết quả từng lượt' : 'Giáo viên / admin duyệt · Có xếp hạng' }}</p>
                            <a href="{{ $c['url'] }}" class="material-primary contest-catalog-open"><x-lucide name="list-checks" />{{ $c['isSurvey'] ? 'Xem các kỳ khảo sát' : 'Xem các kỳ thi' }}<x-lucide name="chevron-right" /></a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- SỬA 25/10 (khách: "quá 5 mục thì phân trang, giống phân trang Đề thi luyện tập") — cùng giao diện / cách rút gọn số
             trang với partials.practice-catalog (pageItems). --}}
        <nav aria-label="Phân trang cuộc thi và khảo sát" x-show="totalPages > 1" x-cloak
             class="flex flex-col items-center justify-between gap-2 rounded-xl border border-[#DDEAF0] bg-[#F8FAFB] p-2 sm:flex-row">
            <span class="text-[11px] text-[#607A90]">Trang <b class="text-[#45657D]" x-text="page"></b> / <span x-text="totalPages"></span></span>
            <div class="flex items-center gap-1.5">
                <button type="button" aria-label="Trang trước" :disabled="page === 1" @click="goPage(page - 1)"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                    <x-lucide name="chevron-left" class="h-4 w-4" />
                </button>
                <template x-for="it in pageItems" :key="'cp' + it.key">
                    <span style="display:contents">
                        <span x-show="it.gap" class="grid h-9 min-w-6 place-items-center px-0.5 text-[13px] font-extrabold tracking-widest text-[#8BA0AF]" aria-hidden="true">…</span>
                        <button type="button" x-show="!it.gap" :aria-label="'Trang ' + it.n" :aria-current="page === it.n ? 'page' : null" @click="goPage(it.n)"
                                class="grid h-9 min-w-9 place-items-center rounded-lg px-2 text-[11px] font-extrabold transition"
                                :class="page === it.n ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#45657D] hover:bg-white'"
                                x-text="it.n"></button>
                    </span>
                </template>
                <button type="button" aria-label="Trang sau" :disabled="page === totalPages" @click="goPage(page + 1)"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                    <x-lucide name="chevron-right" class="h-4 w-4" />
                </button>
            </div>
        </nav>

        <div class="cc-empty" x-show="visibleCount === 0" @if (count($cards) > 0) x-cloak @endif>
            <x-lucide name="search" />
            <h3>{{ count($cards) ? 'Chưa tìm thấy sự kiện phù hợp' : 'Chưa có cuộc thi hoặc khảo sát nào' }}</h3>
            <p>{{ count($cards) ? 'Thử tên khác hoặc thay đổi bộ lọc.' : 'Các sự kiện mới sẽ xuất hiện ở đây khi ban tổ chức công bố.' }}</p>
            @if (count($cards))
                <button type="button" class="material-text-button" style="margin-top:12px" @click="reset()">Đặt lại tất cả</button>
            @endif
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
    // SỬA 24/10 — lọc danh sách theo tên (bỏ dấu), lộ trình, loại sự kiện (bản mẫu: ContestsPage.jsx).
    function contestCatalog(init) {
        const normalize = (v) => String(v || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase();
        const rows = (init.rows || []).map((r) => ({ ...r, key: normalize(r.title) }));
        const PAGE_SIZE = 5; // SỬA 25/10 — quá 5 sự kiện thì phân trang
        return {
            query: '',
            roadmap: 'all',
            type: init.type || 'all',
            pageIndex: 1,
            init() {
                // Đổi bộ lọc / từ khoá thì về trang 1.
                ['query', 'roadmap', 'type'].forEach((k) => this.$watch(k, () => { this.pageIndex = 1; }));
            },
            get hasFilters() { return this.query !== '' || this.roadmap !== 'all' || this.type !== 'all'; },
            match(r) {
                return (this.type === 'all' || r.type === this.type)
                    && (this.roadmap === 'all' || String(r.roadmap) === String(this.roadmap))
                    && r.key.includes(normalize(this.query.trim()));
            },
            get matchedIds() { return rows.filter((r) => this.match(r)).map((r) => r.id); },
            get visibleCount() { return this.matchedIds.length; },
            get totalPages() { return Math.max(1, Math.ceil(this.visibleCount / PAGE_SIZE)); },
            get page() { return Math.min(this.pageIndex, this.totalPages); },
            get pageIds() { const start = (this.page - 1) * PAGE_SIZE; return this.matchedIds.slice(start, start + PAGE_SIZE); },
            get rangeLabel() { const start = (this.page - 1) * PAGE_SIZE; return (start + 1) + '–' + Math.min(start + PAGE_SIZE, this.visibleCount); },
            isVisible(id) { return this.pageIds.includes(id); },
            goPage(n) {
                this.pageIndex = Math.min(Math.max(1, n), this.totalPages);
                this.$nextTick(() => this.$refs.list && this.$refs.list.scrollIntoView({ block: 'start', behavior: 'smooth' }));
            },
            // Dãy số trang rút gọn — giống pageItems() của trang Luyện tập (partials.practice-page-script).
            get pageItems() {
                const total = this.totalPages, current = this.page, items = [];
                const add = (n) => items.push({ key: 'n' + n, n, gap: false });
                const gap = (k) => items.push({ key: k, n: 0, gap: true });
                if (total <= 7) { for (let i = 1; i <= total; i++) add(i); return items; }
                if (current <= 4) { for (let i = 1; i <= 6; i++) add(i); gap('gr'); add(total); }
                else if (current >= total - 3) { add(1); gap('gl'); for (let i = total - 5; i <= total; i++) add(i); }
                else { add(1); gap('gl'); for (let i = current - 1; i <= current + 1; i++) add(i); gap('gr'); add(total); }
                return items;
            },
            reset() { this.query = ''; this.roadmap = 'all'; this.type = 'all'; },
        };
    }
</script>
@endpush
