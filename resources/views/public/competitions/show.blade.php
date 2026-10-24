@extends('layouts.guest')

@section('title', $contest['title'])
@section('meta-description', \Illuminate\Support\Str::limit($contest['description'], 155))

@push('head')
    {{-- SỬA 24/10 — CSS riêng của phòng thi (chép từ source UI mới: contests.css, contestRoom.css …). --}}
    <link rel="stylesheet" href="{{ asset('css/contest-hub.css') }}?v={{ @filemtime(public_path('css/contest-hub.css')) }}">
@endpush

@section('content')
@php
    /*
     * SỬA 24/10 (khách: "làm lại trang cuộc thi theo UI mới … khảo sát nhập mật khẩu, cuộc thi đăng ký như bình thường")
     * PHÒNG THI — dựng theo ContestRoomPage.jsx của source mới. Một trang cho mọi người:
     *   khách      : xem thông tin, nút "Đăng nhập để tham gia"
     *   học sinh   : cuộc thi → Đăng ký → chờ duyệt → Vào thi (nhận mã đề, nộp 1 lần)
     *                khảo sát → Bắt đầu khảo sát → nhập mật khẩu → làm bài (nhiều lượt)
     *   ban tổ chức: (admin / giáo viên tổ chức) thêm tab "Học sinh", nút in bảng xếp hạng gửi phụ huynh
     * Dữ liệu + luật: App\Services\Contest\ContestHubService::roomData(). Nút vào thi gửi về
     * student.competitions.enter (ContestEntryService) — kiểm lại toàn bộ ở máy chủ, giao diện chỉ hiển thị.
     */
    $survey = $contest['isSurvey'];
    $round = $round ?? null;
    $manager = $viewer['isManager'];
    $entryFailed = session('contest_entry_failed') && $errors->has('entry');
    $notice = session('contest_notice')
        ?? (session('status') === 'competition-join-requested' ? 'Đã gửi đăng ký. Giáo viên hoặc ban tổ chức sẽ xét duyệt.' : null);
    $otherErrors = collect($errors->getMessages())->except('entry')->flatten()->first();
    $pageCfg = [
        'tab' => $activeTab ?? 'overview',
        'tabs' => array_column($tabs ?? [], 'id'),
        'serverNow' => $serverNowMs,
        'target' => $round['countdownTargetMs'] ?? null,
        'contestId' => $contest['id'],
        'openEntry' => $entryFailed,
        // SỬA 24/10 — đề học sinh chọn (khảo sát nhiều đề).
        'selectedPaper' => ($doingPaperId ?? null) ?? ($myPaperId ?? null) ?? (($paperList ?? [])[0]['id'] ?? null),
        'paperLabels' => collect($paperList ?? [])->pluck('label', 'id')->all(),
        'selectedForm' => $assignedForm ?? (($round['assignmentMode'] ?? 'random') === 'choice' ? 1 : null),
        'searchUrl' => $participants['searchUrl'] ?? null,
        // SỬA 24/10 — hộp thoại Thêm / Sửa kỳ thi (ban tổ chức).
        'roundEditor' => $roundEditor ?? null,
        'roundOpen' => session('round_dialog'),
        'roundOld' => session('round_dialog') ? [
            'title' => old('title'), 'startsAt' => old('starts_at'), 'endsAt' => old('ends_at'), 'assessmentId' => (string) old('assessment_id', ''),
            'formCount' => (string) old('form_count', '1'), 'assignmentMode' => old('assignment_mode', 'random'),
        ] : null,
    ];
@endphp

<div class="contest-ui contest-hub {{ $survey ? 'is-survey' : '' }}" x-data="contestRoom({{ Js::from($pageCfg) }})" x-init="init()">
    <header class="hub-header">
        <div class="arena-topbar">
            <a class="hub-back" href="{{ $catalogUrl }}" aria-label="Quay lại danh sách cuộc thi"><x-lucide name="arrow-left" />Cuộc thi &amp; khảo sát</a>
            <span class="arena-breadcrumb">/ <span>{{ $survey ? 'Khảo sát năng lực' : 'Chi tiết cuộc thi' }}</span></span>
            <button type="button" class="hub-bell" aria-label="Mở thông báo ban tổ chức" :aria-expanded="announcementOpen" aria-controls="hub-announcement" @click="announcementOpen = !announcementOpen">
                <x-lucide name="bell" /><span>Thông báo</span><b x-show="!announcementRead">1</b>
            </button>
        </div>
    </header>

    <div class="arena-container">
        <section class="arena-hero" aria-labelledby="arena-title">
            <img class="arena-header-backdrop" src="{{ asset('assets/hero-contests.jpg') }}" alt="" aria-hidden="true">
            <img class="arena-cover" src="{{ $contest['image'] }}" alt="">
            <div class="arena-hero-copy">
                <div class="arena-kicker">
                    <span><x-lucide :name="$survey ? 'key-round' : 'trophy'" />{{ $survey ? 'Khảo sát năng lực' : 'Cuộc thi' }}</span>
                    <span class="contest-badge {{ $contest['state'] }}">{{ $contest['stateLabel'] }}</span>
                    @if ($contest['editionLabel'] !== '')<small>{{ $contest['editionLabel'] }}</small>@endif
                </div>
                <h1 id="arena-title">{{ $contest['title'] }}</h1>
                @if ($contest['roadmap'])
                    <a class="hub-roadmap-link" href="{{ $contest['roadmap']['href'] }}">Lộ trình: {{ $contest['roadmap']['title'] }}<x-lucide name="arrow-right" /></a>
                @endif
                <div class="arena-meta">
                    <span><x-lucide name="shield-check" />{{ $contest['organizer'] }}</span>
                    <span><x-lucide name="calendar-days" />{{ $contest['dateRange'] }}</span>
                </div>
            </div>
        </section>

        @if ($manager)
            {{-- Ban tổ chức: nhắc mật khẩu khảo sát + lối sang trang quản trị. --}}
            <div class="hub-manager-bar">
                <span><b>Bạn đang xem với quyền ban tổ chức.</b></span>
                @if ($survey)
                    @php $pw = $competition->entryPassword(); @endphp
                    <span class="hub-pass">Mật khẩu khảo sát:
                        @if ($pw !== null)
                            <code x-data="{ show: false }" @click="show = !show" style="cursor:pointer" :title="show ? '' : 'Bấm để hiện'"><span x-show="show" x-cloak>{{ $pw }}</span><span x-show="!show">••••••</span></code>
                        @else
                            <code>Chưa đặt</code>
                        @endif
                    </span>
                @endif
                @if ($adminUrl)
                    <a href="{{ $adminUrl }}"><x-lucide name="settings" />Quản trị cuộc thi (kỳ thi, đề, mật khẩu)</a>
                @endif
            </div>
        @endif

        <section id="hub-announcement" class="hub-announcement" aria-label="Thông báo ban tổ chức" x-show="announcementOpen" x-cloak>
            <x-lucide name="megaphone" />
            <div>
                <h3>{{ $announcement['title'] ?? ($survey ? 'Nhận mật khẩu trước khi khảo sát' : 'Hoàn tất đăng ký trước khi vào thi') }}</h3>
                <p>{{ $announcement['text'] ?? '' }}</p>
                <small>{{ $contest['organizer'] }} · Hướng dẫn tham gia</small>
            </div>
            <button type="button" @click="markRead()">Đã đọc</button>
        </section>

        @if ($notice)
            <div role="status" class="contest-notice" x-data="{ open: true }" x-show="open"><x-lucide name="info" /><span>{{ $notice }}</span><button type="button" aria-label="Đóng thông báo" @click="open = false"><x-lucide name="x" /></button></div>
        @endif
        @if ($otherErrors || ($errors->has('entry') && ! $entryFailed))
            <div role="alert" class="contest-notice" style="background:#fff3f2;border-color:#f3cccc;color:#a33b3b" x-data="{ open: true }" x-show="open"><x-lucide name="info" /><span>{{ $otherErrors ?: $errors->first('entry') }}</span><button type="button" aria-label="Đóng thông báo" @click="open = false"><x-lucide name="x" /></button></div>
        @endif

        <div class="arena-section-heading">
            <h2>Các kỳ thi <span>{{ count($rounds) }}</span></h2>
            <div class="hub-heading-right">
                <p>{{ $survey ? 'Nhập mật khẩu · Có thể làm nhiều lần' : 'Đăng ký một lần · Duyệt cho toàn cuộc thi' }}</p>
                @if (! empty($roundEditor))
                    {{-- SỬA 24/10 — ban tổ chức thêm kỳ thi ngay trong phòng thi. --}}
                    <button type="button" class="hub-add-round" @click="openRound(null)"><x-lucide name="plus" />Thêm kỳ thi</button>
                @endif
            </div>
        </div>

        @if ($round === null)
            <div class="contest-empty"><x-lucide name="list-checks" /><h3>{{ $action['title'] }}</h3><p>{{ $action['text'] }}</p>
                @if (! empty($roundEditor))
                    <button type="button" class="hub-add-round" @click="openRound(null)"><x-lucide name="plus" />Thêm kỳ thi đầu tiên</button>
                @endif
            </div>
        @else
            <main class="hub-workspace">
                <aside class="hub-round-selector" aria-label="Các kỳ thi">
                    <label class="hub-round-select"><span>Kỳ thi đang xem</span>
                        <select aria-label="Chọn kỳ thi" onchange="window.location.href = this.value">
                            @foreach ($rounds as $r)
                                <option value="{{ $r['url'] }}" @selected($r['id'] === $round['id'])>{{ $r['noLabel'] }} · {{ $r['label'] }} · {{ $r['stateLabel'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="hub-round-grid">
                        @foreach ($rounds as $r)
                            @php $sel = $r['id'] === $round['id']; @endphp
                            <a href="{{ $r['url'] }}" class="hub-round-card" @if ($sel) aria-current="page" @endif>
                                <span class="hub-round-number">@if ($r['submitted'])<x-lucide name="check-circle-2" />@else{{ $r['noLabel'] }}@endif</span>
                                <span class="arena-round-copy">
                                    <span class="contest-badge {{ $r['state'] }}">{{ $r['stateLabel'] }}</span>
                                    <b>{{ $r['label'] }}</b>
                                    <span class="hub-round-date">{{ $r['dateRange'] }}</span>
                                    <span class="hub-round-own {{ $r['submitted'] ? 'is-done' : '' }}">{{ $r['submitted'] ? 'Bạn đã nộp bài' : ($r['hasDraft'] ? 'Có bài đang làm' : ($sel ? 'Đang xem kỳ thi này' : 'Xem kỳ thi')) }}<x-lucide name="arrow-right" /></span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                    @if ($viewer['isStudent'])
                        <div class="arena-progress">
                            <span>Đã tham gia <b>{{ $doneCount }}/{{ count($rounds) }} kỳ thi</b></span>
                            <div role="progressbar" aria-label="Số kỳ thi đã tham gia" aria-valuenow="{{ $doneCount }}" aria-valuemin="0" aria-valuemax="{{ count($rounds) }}"><i style="width: {{ count($rounds) ? round($doneCount / count($rounds) * 100, 1) : 0 }}%"></i></div>
                        </div>
                    @endif
                </aside>

                <section class="hub-exam" aria-label="Kỳ thi đang chọn">
                    <div class="hub-exam-summary">
                        <div class="arena-round-heading"><h2>{{ $round['label'] }}</h2>
                            <span class="hub-round-head-right">
                                @if (! empty($roundEditor))
                                    <button type="button" class="hub-edit-round" @click="openRound({{ $round['id'] }})"><x-lucide name="pencil" />Sửa kỳ thi</button>
                                @endif
                                <span class="contest-badge {{ $round['state'] }}">{{ $round['stateLabel'] }}</span>
                            </span>
                        </div>
                        <div class="arena-round-time"><x-lucide name="calendar-days" /><span>{{ $round['timeFrom'] }} <x-lucide name="arrow-right" /> {{ $round['timeTo'] }} <small>(GMT+7)</small></span></div>
                        <div class="arena-round-details">
                            <div class="hub-exam-metrics">
                                <span><x-lucide name="clock-3" /><b>{{ $round['duration'] }}</b> / lượt</span>
                                <span><x-lucide name="file-text" /><b>{{ $round['questionCount'] }} câu hỏi</b></span>
                                <span><x-lucide :name="$survey ? 'rotate-ccw' : 'shield-check'" /><b>{{ $survey ? 'Không giới hạn lượt' : '1 lượt nộp / kỳ' }}</b></span>
                            </div>
                            <div class="hub-countdown"><span>{{ $round['countdownLabel'] }}</span>@if ($round['countdownTargetMs'])<b x-text="clock">--:--:--</b>@endif</div>
                        </div>
                        <section class="arena-entry {{ $latest ? 'is-complete' : '' }}" aria-label="Quyền tham gia">
                            <span class="arena-entry-icon"><x-lucide :name="$action['icon']" /></span>
                            <div class="arena-entry-copy">
                                <h2>{{ $action['title'] }}</h2>
                                <p>{{ $action['text'] }}</p>
                                @if ($latest)
                                    <span class="arena-inline-result"><b>{{ $latest['scoreLabel'] }}{{ $latest['graded'] ? ' điểm' : '' }}</b><i></i>{{ $survey ? count($attempts).' lượt đã làm' : ($myRank ? 'Hạng #'.$myRank : 'Đang cập nhật thứ hạng') }}</span>
                                @endif
                            </div>
                            <div class="hub-desktop-action">@include('public.competitions.partials.action-button')</div>
                        </section>
                    </div>

                    <div x-ref="tabs" role="tablist" aria-label="Nội dung kỳ thi" class="hub-tabs">
                        @foreach ($tabs as $i => $t)
                            <button type="button" role="tab" id="hub-tab-{{ $t['id'] }}" :aria-selected="tab === '{{ $t['id'] }}'" aria-controls="hub-tabpanel"
                                    :tabindex="tab === '{{ $t['id'] }}' ? 0 : -1" @click="showTab('{{ $t['id'] }}')" @keydown="keyTabs($event, {{ $i }})">
                                <x-lucide :name="$t['icon']" /><span>{{ $t['label'] }}</span>@if ($t['count'] !== null)<b>{{ $t['count'] }}</b>@endif
                            </button>
                        @endforeach
                    </div>
                    <div class="hub-tab-content" id="hub-tabpanel" role="tabpanel" tabindex="0">
                        @if (! $round['hasAssessment'])
                            <p class="contest-error" role="alert">Kỳ thi này chưa được ban tổ chức gắn đề.</p>
                        @endif
                        <div x-show="tab === 'overview'">@include('public.competitions.partials.overview')</div>
                        @if (! empty($round['paperStateUrl']))
                            {{-- SỬA 24/10 — tab Thiết lập đề (ban tổ chức, khảo sát). --}}
                            <div x-show="tab === 'paper'" x-cloak>@include('public.competitions.partials.paper-builder')</div>
                        @endif
                        @if ($viewer['loggedIn'])
                            <div x-show="tab === 'submissions'" x-cloak>@include('public.competitions.partials.submissions')</div>
                        @endif
                        @if ($survey)
                            <div x-show="tab === 'results'" x-cloak>@include('public.competitions.partials.survey-results')</div>
                            <div x-show="tab === 'ranking'" x-cloak>@include('public.competitions.partials.rankings')</div>
                        @else
                            <div x-show="tab === 'results'" x-cloak>@include('public.competitions.partials.rankings')</div>
                        @endif
                        @if ($manager && $participants !== null)
                            <div x-show="tab === 'registrations'" x-cloak>@include('public.competitions.partials.participants')</div>
                        @endif
                    </div>
                </section>
            </main>
        @endif

        <footer class="arena-footer"><p>Thời gian mở và đóng kỳ thi theo múi giờ Việt Nam (GMT+7).</p></footer>
    </div>

    @if (! empty($roundEditor))
        @include('public.competitions.partials.round-dialog')
    @endif

    @if ($round !== null)
        <div class="hub-mobile-action"><div><span>Kỳ thi đang chọn</span><b>{{ $round['label'] }}</b></div>@include('public.competitions.partials.action-button')</div>
        @if (($action['kind'] ?? '') === 'enter')
            @include('public.competitions.partials.entry-dialog')
        @endif
        @if ($manager && $participants !== null)
            @include('public.competitions.partials.add-students-dialog')
            @include('public.competitions.partials.ranking-print')
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
    // SỬA 24/10 — trạng thái giao diện phòng thi (tab, đồng hồ, hộp thoại). Mọi luật quyền nằm ở máy chủ.
    function contestRoom(cfg) {
        const offset = (cfg.serverNow || Date.now()) - Date.now();
        return {
            tab: cfg.tab || 'overview',
            announcementOpen: false,
            announcementRead: false,
            selectedForm: cfg.selectedForm,
            selectedPaper: cfg.selectedPaper,
            paperLabels: cfg.paperLabels || {},
            nowMs: Date.now() + offset,
            init() {
                try { this.announcementRead = sessionStorage.getItem('contest-notice-read:' + cfg.contestId) === '1'; } catch (e) {}
                if (cfg.target) {
                    const timer = setInterval(() => {
                        this.nowMs = Date.now() + offset;
                        if (this.nowMs >= cfg.target) { clearInterval(timer); setTimeout(() => window.location.reload(), 1500); }
                    }, 1000);
                }
                // Chừa chỗ cho thanh điều hướng đáy của site trên điện thoại.
                const fit = () => { const nav = document.querySelector('nav[aria-label="Điều hướng nhanh"]'); const h = nav && getComputedStyle(nav).display !== 'none' ? nav.offsetHeight : 0; this.$root.style.setProperty('--hub-bottom-offset', h + 'px'); };
                fit(); window.addEventListener('resize', fit);
                const print = document.getElementById('contest-ranking-print');
                if (print) document.body.appendChild(print); // CSS in: body:has(> .contest-ranking-print)
                this.$nextTick(() => {
                    if (cfg.roundOpen) this.openRound(cfg.roundOpen === 'new' ? null : Number(cfg.roundOpen), cfg.roundOld);
                    if (cfg.openEntry) this.openEntry();
                });
            },
            get clock() {
                const remaining = Math.max(0, (cfg.target || 0) - this.nowMs);
                const days = Math.floor(remaining / 86400000);
                const pad = (n) => String(n).padStart(2, '0');
                return (days ? days + ' ngày ' : '') + pad(Math.floor(remaining / 3600000) % 24) + ':' + pad(Math.floor(remaining / 60000) % 60) + ':' + pad(Math.floor(remaining / 1000) % 60);
            },
            markRead() {
                this.announcementRead = true; this.announcementOpen = false;
                try { sessionStorage.setItem('contest-notice-read:' + cfg.contestId, '1'); } catch (e) {}
            },
            showTab(id) {
                if (!cfg.tabs.includes(id)) return;
                this.tab = id;
                try { const u = new URL(window.location.href); u.searchParams.set('tab', id); history.replaceState(null, '', u); } catch (e) {}
                this.$nextTick(() => { const el = document.getElementById('hub-tab-' + id); if (el) el.focus({ preventScroll: true }); this.$refs.tabs && this.$refs.tabs.scrollIntoView({ block: 'nearest' }); });
            },
            keyTabs(event, index) {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                const n = cfg.tabs.length;
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? n - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + n) % n;
                this.showTab(cfg.tabs[next]);
            },
            openEntry() { const d = this.$refs.entryDialog; if (d && !d.open) d.showModal(); },

            // ── SỬA 24/10 — hộp thoại Thêm / Sửa kỳ thi ──
            roundForm: { id: null, url: '', deleteUrl: '', title: '', startsAt: '', endsAt: '', assessmentId: '', source: 'matrix', formCount: '1', assignmentMode: 'random', locked: false, q: '' },
            get filteredOptions() {
                const ed = cfg.roundEditor; if (!ed) return [];
                const n = (v) => String(v || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/gi, 'd').toLowerCase();
                const q = n(this.roundForm.q.trim());
                return ed.options.filter((o) => q === '' || n(o.title).includes(q) || String(o.id) === this.roundForm.assessmentId).slice(0, 300);
            },
            openRound(id, old) {
                const ed = cfg.roundEditor; if (!ed) return;
                const r = id ? ed.rounds[id] : null;
                const v = old || {};
                this.roundForm = {
                    id: r ? r.id : null, url: r ? r.updateUrl : ed.storeUrl, deleteUrl: r ? r.deleteUrl : '',
                    title: v.title ?? (r ? r.title : ''), startsAt: v.startsAt ?? (r ? r.startsAt : ''), endsAt: v.endsAt ?? (r ? r.endsAt : ''),
                    assessmentId: String(v.assessmentId ?? (r ? r.assessmentId : '')), formCount: String(v.formCount ?? (r ? r.formCount : 1)),
                    assignmentMode: v.assignmentMode ?? (r ? r.assignmentMode : 'random'), locked: !!(r && r.locked), q: '', source: 'matrix',
                };
                this.roundForm.source = this.roundForm.assessmentId ? 'existing' : 'matrix';
                this.$nextTick(() => { const d = this.$refs.roundDialog; if (d && !d.open) d.showModal(); });
            },
            closeRound() { const d = this.$refs.roundDialog; if (d && d.open) d.close(); },
            presetRound(days) {
                const pad = (x) => String(x).padStart(2, '0');
                const fmt = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
                let start = this.roundForm.startsAt ? new Date(this.roundForm.startsAt) : null;
                if (!start || isNaN(start) || days === 0) { start = new Date(); start.setSeconds(0, 0); start.setMinutes(Math.ceil(start.getMinutes() / 5) * 5); }
                this.roundForm.startsAt = fmt(start);
                if (days > 0) this.roundForm.endsAt = fmt(new Date(start.getTime() + days * 86400000));
            },
            closeEntry() { const d = this.$refs.entryDialog; if (d && d.open) d.close(); },
            openAdd() { this.showTab('registrations'); const d = this.$refs.addDialog; if (d && !d.open) { d.showModal(); this.search(); } },
            closeAdd() { const d = this.$refs.addDialog; if (d && d.open) d.close(); },

            // ── Hộp thoại "Thêm học sinh" ──
            addQuery: '', addClass: '', students: [], picked: [], loading: false, loadError: '',
            async search() {
                if (!cfg.searchUrl) return;
                this.loading = true; this.loadError = '';
                try {
                    const u = new URL(cfg.searchUrl, window.location.origin);
                    if (this.addQuery.trim()) u.searchParams.set('q', this.addQuery.trim());
                    if (this.addClass) u.searchParams.set('lop', this.addClass);
                    const res = await fetch(u, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!res.ok) throw new Error();
                    this.students = (await res.json()).students || [];
                } catch (e) { this.students = []; this.loadError = 'Không tải được danh sách học sinh. Vui lòng thử lại.'; }
                this.loading = false;
            },
            get selectable() { return this.students.filter((s) => !s.added); },
            get allPicked() { return this.selectable.length > 0 && this.selectable.every((s) => this.picked.includes(s.id)); },
            togglePick(id) { this.picked = this.picked.includes(id) ? this.picked.filter((x) => x !== id) : [...this.picked, id]; },
            toggleAll() {
                const ids = this.selectable.map((s) => s.id);
                this.picked = this.allPicked ? this.picked.filter((id) => !ids.includes(id)) : [...new Set([...this.picked, ...ids])];
            },

            // ── Lọc bảng "Học sinh tham gia" ──
            peopleQuery: '',
            peopleMatch(key) {
                const q = this.peopleQuery.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase().trim();
                return q === '' || key.includes(q);
            },
        };
    }
</script>
@endpush
