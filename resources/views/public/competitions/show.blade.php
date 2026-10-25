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
     * PHÒNG THI — dựng theo ContestRoomPage.jsx của source mới. Dữ liệu + luật: App\Services\Contest\ContestHubService::roomData().
     *   khách / phụ huynh : xem thông tin, lịch, thể lệ, bảng xếp hạng.
     *   học sinh          : cuộc thi → Đăng ký → chờ duyệt → Vào thi (nộp 1 lần);
     *                       khảo sát → có tên trong danh sách + nhập mật khẩu → làm bài (nhiều lượt).
     *   ban tổ chức       : SỬA 25/10 — admin / giáo viên: "Các kỳ tôi tổ chức" (giáo viên chỉ thấy kỳ mình chủ trì),
     *                       Thêm kỳ thi / Thêm kỳ khảo sát, tab Tổng quan · Thiết lập đề (hoặc Lấy đề có sẵn) · Học sinh ·
     *                       Bảng xếp hạng.
     * Mọi nút gửi về máy chủ (ContestManageController, ContestPaperController, student.competitions.enter) — kiểm lại
     * toàn bộ quyền ở đó, giao diện chỉ hiển thị.
     */
    $survey = $contest['isSurvey'];
    $round = $round ?? null;
    $staff = $viewer['isStaff'];
    $manager = $roundManager ?? false;            // người quản lý KỲ đang xem (dùng ở các partial: bảng xếp hạng, học sinh…)
    $setup = $setup ?? null;
    $kindLabel = $contest['kind'];                 // "kỳ thi" | "kỳ khảo sát"
    $entryFailed = session('contest_entry_failed') && $errors->has('entry');
    $notice = session('contest_notice')
        ?? (session('status') === 'competition-join-requested' ? 'Đã gửi đăng ký. Giáo viên hoặc ban tổ chức sẽ xét duyệt.' : null);
    $otherErrors = collect($errors->getMessages())->except(['entry', 'title', 'starts_at', 'ends_at', 'assessment_id'])->flatten()->first();
    $pageCfg = [
        'tab' => $activeTab ?? 'overview',
        'tabs' => array_column($tabs ?? [], 'id'),
        'serverNow' => $serverNowMs,
        'target' => $round['countdownTargetMs'] ?? null,
        'period' => $round['state'] ?? null,
        'contestId' => $contest['id'],
        'openEntry' => $entryFailed,
        // Khảo sát nhiều đề: đề học sinh chọn.
        'selectedPaper' => ($myPaperId ?? null) ?? (($paperList ?? [])[0]['id'] ?? null),
        'paperLabels' => collect($paperList ?? [])->pluck('label', 'id')->all(),
        'selectedForm' => $assignedForm ?? (($round['assignmentMode'] ?? 'random') === 'choice' ? 1 : null),
        'searchUrl' => $participants['searchUrl'] ?? null,
        // Ban tổ chức: hộp thoại Thêm / Sửa kỳ + Lấy đề có sẵn.
        'roundEditor' => $roundEditor ?? null,
        'roundId' => $round['id'] ?? null,
        'roundOpen' => session('round_dialog'),
        'roundOld' => session('round_dialog') ? ['title' => old('title'), 'startsAt' => old('starts_at'), 'endsAt' => old('ends_at'), 'source' => old('source_type')] : null,
        'pickerOpen' => (bool) (session('exam_picker') || ($manager && request()->boolean('chon') && ($setup['fromBank'] ?? false))),
        'builderOpen' => (bool) ($setup['openBuilder'] ?? false),
    ];
    $rankTab = $survey ? 'ranking' : 'results';
@endphp

<div class="contest-ui contest-hub {{ $survey ? 'is-survey' : '' }} {{ $staff ? 'is-manager' : ($viewer['isStudent'] ? 'is-student' : 'is-observer') }}" x-data="contestRoom({{ Js::from($pageCfg) }})" x-init="init()">
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
                    <span><x-lucide :name="$survey ? 'key-round' : 'user-check'" />{{ $survey ? 'Vào bằng mật khẩu' : 'Đăng ký & được duyệt' }}</span>
                    @if ($adminUrl)
                        <span><a href="{{ $adminUrl }}" class="hub-admin-link"><x-lucide name="settings" />Quản trị cuộc thi</a></span>
                    @endif
                </div>
            </div>
            <span class="hub-role-context">{{ $viewer['roleLabel'] }}</span>
        </section>

        <section id="hub-announcement" class="hub-announcement" aria-label="Thông báo ban tổ chức" x-show="announcementOpen" x-cloak>
            <x-lucide name="megaphone" />
            <div>
                <h3>{{ $announcement['title'] ?? ($survey ? 'Nhận mật khẩu trước khi khảo sát' : 'Hoàn tất đăng ký trước khi vào thi') }}</h3>
                <p>{{ $announcement['text'] ?? ($survey ? 'Liên hệ giáo viên để được thêm vào danh sách và nhận mật khẩu.' : 'Đăng ký và chờ giáo viên duyệt trước khi vào thi.') }}</p>
                <small>{{ $contest['organizer'] }} · Hướng dẫn tham gia</small>
            </div>
            <button type="button" @click="markRead()">Đã đọc</button>
        </section>

        @if ($notice)
            <div role="status" class="contest-notice" x-data="{ open: true }" x-show="open"><x-lucide name="info" /><span>{{ $notice }}</span><button type="button" aria-label="Đóng thông báo" @click="open = false"><x-lucide name="x" /></button></div>
        @endif
        @if ($otherErrors || ($errors->has('entry') && ! $entryFailed) || ($errors->hasAny(['assessment_id']) && ! session('exam_picker')))
            <div role="alert" class="contest-notice" style="background:#fff3f2;border-color:#f3cccc;color:#a33b3b" x-data="{ open: true }" x-show="open"><x-lucide name="info" /><span>{{ $otherErrors ?: ($errors->first('entry') ?: $errors->first('assessment_id')) }}</span><button type="button" aria-label="Đóng thông báo" @click="open = false"><x-lucide name="x" /></button></div>
        @endif

        <div class="arena-section-heading">
            <h2>{{ $roundsHeading }} <span>{{ count($rounds) }}</span></h2>
            @if (! empty($roundEditor))
                <button type="button" class="contest-btn primary" @click="openRound(null)"><x-lucide name="plus" />{{ $survey ? 'Thêm kỳ khảo sát' : 'Thêm kỳ thi' }}</button>
            @else
                <p>{{ $survey ? 'Có tên trong danh sách + mật khẩu · Làm nhiều lần' : 'Đăng ký một lần · Duyệt cho toàn cuộc thi' }}</p>
            @endif
        </div>

        @if ($round === null)
            <div class="hub-round-empty">
                <x-lucide name="search" />
                <b>{{ $action['title'] }}</b>
                <p>{{ $action['text'] }}</p>
                @if (! empty($roundEditor))
                    <button type="button" class="contest-btn primary" @click="openRound(null)"><x-lucide name="plus" />Tạo kỳ đầu tiên</button>
                @endif
            </div>
        @else
            <main class="hub-workspace">
                <aside class="hub-round-selector" aria-label="{{ $survey ? 'Các kỳ khảo sát' : 'Các kỳ thi' }}">
                    <label class="hub-round-select"><span>{{ $survey ? 'Kỳ khảo sát đang xem' : 'Kỳ thi đang xem' }}</span>
                        <select aria-label="Chọn {{ $kindLabel }}" onchange="window.location.href = this.value">
                            @foreach ($rounds as $r)
                                <option value="{{ $r['url'] }}" @selected($r['id'] === $round['id'])>{{ $r['noLabel'] }} · {{ $r['label'] }} · {{ $r['host'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="hub-round-grid">
                        @foreach ($rounds as $r)
                            @php $sel = $r['id'] === $round['id']; @endphp
                            <a href="{{ $r['url'] }}" class="hub-round-card" @if ($sel) aria-current="page" @endif>
                                <span class="hub-round-number">@if ($r['submitted'] && ! $staff)<x-lucide name="check-circle-2" />@else{{ $r['noLabel'] }}@endif</span>
                                <span class="arena-round-copy">
                                    <span class="contest-badge {{ $r['state'] }}">{{ $r['stateLabel'] }}</span>
                                    <b>{{ $r['label'] }}</b>
                                    <span class="hub-round-host">Chủ trì: {{ $r['host'] }}</span>
                                    <span class="hub-round-date">{{ $r['dateRange'] }}</span>
                                    <span class="hub-round-own {{ $r['submitted'] && ! $staff ? 'is-done' : '' }}">{{ ! $staff && $r['submitted'] ? 'Bạn đã nộp bài' : (! $staff && $r['hasDraft'] ? 'Có bài đang làm' : ($sel ? 'Đang xem kỳ này' : 'Xem chi tiết')) }}<x-lucide name="arrow-right" /></span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                    @if ($viewer['isStudent'])
                        <div class="arena-progress">
                            <span>Đã tham gia <b>{{ $doneCount }}/{{ count($rounds) }} {{ $kindLabel }}</b></span>
                            <div role="progressbar" aria-label="Số {{ $kindLabel }} đã tham gia" aria-valuenow="{{ $doneCount }}" aria-valuemin="0" aria-valuemax="{{ count($rounds) }}"><i style="width: {{ count($rounds) ? round($doneCount / count($rounds) * 100, 1) : 0 }}%"></i></div>
                        </div>
                    @endif
                </aside>

                <section class="hub-exam" aria-label="{{ $survey ? 'Kỳ khảo sát đang chọn' : 'Kỳ thi đang chọn' }}">
                    <div class="hub-exam-summary">
                        <div class="arena-round-heading"><h2>{{ $round['label'] }}</h2>
                            <span class="hub-round-head-right">
                                @if ($manager)
                                    <button type="button" class="hub-edit-round" @click="openRound({{ $round['id'] }})"><x-lucide name="pencil" />Sửa {{ $kindLabel }}</button>
                                @endif
                                <span class="contest-badge {{ $round['state'] }}">{{ $round['stateLabel'] }}</span>
                            </span>
                        </div>
                        <p class="hub-selected-host">Chủ trì: <b>{{ $round['host'] }}</b>@if ($manager)<span> · {{ $setup['sourceLabel'] }}</span>@endif</p>
                        <div class="hub-round-schedule">
                            <div><x-lucide name="calendar-days" /><span><small>Bắt đầu</small><b>{{ $round['timeFrom'] }}</b></span></div>
                            <div><x-lucide name="clock-3" /><span><small>Kết thúc</small><b>{{ $round['timeTo'] }}</b></span></div>
                            <small class="hub-timezone">Giờ Việt Nam · GMT+7</small>
                        </div>
                        <dl class="hub-round-facts">
                            <div><dt>Thời lượng</dt><dd>{{ $round['duration'] }}<small>Mỗi lượt làm</small></dd></div>
                            <div><dt>Bộ đề</dt><dd>{{ $setup['factValue'] }}<small>{{ $setup['factNote'] }}</small></dd></div>
                            <div><dt>Tham gia bằng</dt><dd>{{ $survey ? 'Mật khẩu' : 'Đăng ký' }}<small>{{ $survey ? 'Có tên trong danh sách' : 'Cần được duyệt' }}</small></dd></div>
                            <div><dt>Số lượt nộp</dt><dd>{{ $survey ? 'Không giới hạn' : '1 lượt' }}<small>Trong thời gian kỳ mở</small></dd></div>
                        </dl>
                        @if ($round['deadline'])
                            <div class="hub-deadline"><x-lucide name="clock-3" /><span>{{ $round['deadline']['prefix'] }} <b x-text="deadlineClock">{{ $round['deadline']['clock'] }}</b>{{ $round['deadline']['suffix'] }}</span></div>
                        @endif
                        <section class="arena-entry {{ $latest && ! $manager ? 'is-complete' : '' }}" aria-label="{{ $manager ? 'Việc cần làm' : 'Quyền tham gia' }}">
                            <span class="arena-entry-icon"><x-lucide :name="$action['icon']" /></span>
                            <div class="arena-entry-copy">
                                <h2>{{ $action['title'] }}</h2>
                                <p>{{ $action['text'] }}</p>
                                @if ($latest && ! $manager)
                                    <span class="arena-inline-result"><b>{{ $latest['scoreLabel'] }}{{ $latest['graded'] ? ' điểm' : '' }}</b><i></i>{{ $survey ? count($attempts).' lượt đã làm' : ($myRank ? 'Hạng #'.$myRank : 'Đang cập nhật thứ hạng') }}</span>
                                @endif
                            </div>
                            <div class="hub-desktop-action">@include('public.competitions.partials.action-button')</div>
                        </section>
                    </div>

                    <div x-ref="tabs" role="tablist" aria-label="Nội dung {{ $kindLabel }}" class="hub-tabs">
                        @foreach ($tabs as $i => $t)
                            <button type="button" role="tab" id="hub-tab-{{ $t['id'] }}" :aria-selected="tab === '{{ $t['id'] }}'" aria-controls="hub-tabpanel"
                                    :tabindex="tab === '{{ $t['id'] }}' ? 0 : -1" @click="showTab('{{ $t['id'] }}')" @keydown="keyTabs($event, {{ $i }})">
                                <x-lucide :name="$t['icon']" /><span>{{ $t['label'] }}</span>@if ($t['count'] !== null)<b>{{ $t['count'] }}</b>@endif
                            </button>
                        @endforeach
                    </div>
                    <div class="hub-tab-content" id="hub-tabpanel" role="tabpanel" tabindex="0">
                        @if (! $round['hasAssessment'] && ! $manager)
                            <p class="contest-error" role="alert">{{ $survey ? 'Kỳ khảo sát này đang được chuẩn bị đề.' : 'Kỳ thi này chưa được ban tổ chức gắn đề.' }}</p>
                        @endif
                        <div x-show="tab === 'overview'">
                            @if ($manager)
                                @include('public.competitions.partials.organizer-overview')
                            @else
                                @include('public.competitions.partials.overview')
                            @endif
                            @include('public.competitions.partials.rules')
                        </div>
                        @if ($manager)
                            <div x-show="tab === 'paper'" x-cloak>@include('public.competitions.partials.paper-tab')</div>
                            @if ($participants !== null)
                                <div x-show="tab === 'registrations'" x-cloak>@include('public.competitions.partials.participants')</div>
                            @endif
                            <div x-show="tab === '{{ $rankTab }}'" x-cloak>@include('public.competitions.partials.rankings')</div>
                        @else
                            @if ($viewer['loggedIn'])
                                <div x-show="tab === 'submissions'" x-cloak>@include('public.competitions.partials.submissions')</div>
                            @endif
                            @if ($survey)
                                <div x-show="tab === 'results'" x-cloak>@include('public.competitions.partials.survey-results')</div>
                                <div x-show="tab === 'ranking'" x-cloak>@include('public.competitions.partials.rankings')</div>
                            @else
                                <div x-show="tab === 'results'" x-cloak>@include('public.competitions.partials.rankings')</div>
                            @endif
                        @endif
                    </div>
                </section>
            </main>
        @endif

        <footer class="arena-footer"><p>Thời gian mở và đóng {{ $kindLabel }} theo múi giờ Việt Nam (GMT+7).</p></footer>
    </div>

    @if (! empty($roundEditor))
        @include('public.competitions.partials.round-dialog')
    @endif

    @if ($round !== null)
        <div class="hub-mobile-action"><div><span>{{ $survey ? 'Kỳ khảo sát đang chọn' : 'Kỳ thi đang chọn' }}</span><b>{{ $round['label'] }}</b></div>@include('public.competitions.partials.action-button')</div>
        @if (($action['kind'] ?? '') === 'enter')
            @include('public.competitions.partials.entry-dialog')
        @endif
        @if ($manager)
            @include('public.competitions.partials.exam-picker')
            @if ($participants !== null)
                @include('public.competitions.partials.add-students-dialog')
                @include('public.competitions.partials.ranking-print')
            @endif
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
    // Trạng thái giao diện phòng thi (tab, đồng hồ, hộp thoại). Mọi luật quyền nằm ở máy chủ.
    function contestRoom(cfg) {
        const offset = (cfg.serverNow || Date.now()) - Date.now();
        const norm = (v) => String(v || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase();
        return {
            tab: cfg.tab || 'overview',
            announcementOpen: false,
            announcementRead: false,
            selectedForm: cfg.selectedForm,
            selectedPaper: cfg.selectedPaper,
            paperLabels: cfg.paperLabels || {},
            builderOpen: !!cfg.builderOpen,
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
                    if (cfg.pickerOpen) this.openPicker();
                    if (cfg.openEntry) this.openEntry();
                });
            },
            // "4 ngày 0 giờ 15 phút" — giống bản mẫu (hub-deadline).
            get deadlineClock() {
                const remaining = Math.max(0, (cfg.target || 0) - this.nowMs);
                if (remaining < 60000) return 'Dưới 1 phút';
                const days = Math.floor(remaining / 86400000);
                return (days ? days + ' ngày ' : '') + (Math.floor(remaining / 3600000) % 24) + ' giờ ' + (Math.floor(remaining / 60000) % 60) + ' phút';
            },
            markRead() {
                this.announcementRead = true; this.announcementOpen = false;
                try { sessionStorage.setItem('contest-notice-read:' + cfg.contestId, '1'); } catch (e) {}
            },
            showTab(id) {
                if (!cfg.tabs.includes(id)) return;
                this.tab = id;
                try { const u = new URL(window.location.href); u.searchParams.set('tab', id); u.searchParams.delete('chon'); history.replaceState(null, '', u); } catch (e) {}
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
            closeEntry() { const d = this.$refs.entryDialog; if (d && d.open) d.close(); },

            // ── Hộp thoại Thêm / Sửa kỳ (bản mẫu: ContestRoundDialog.jsx) ──
            roundForm: { id: null, url: '', deleteUrl: '', title: '', startsAt: '', endsAt: '', source: 'exam', host: '', locked: false },
            openRound(id, old) {
                const ed = cfg.roundEditor; if (!ed) return;
                const r = id ? ed.rounds[id] : null;
                if (id && !r) return;
                const v = old || {};
                this.roundForm = {
                    id: r ? r.id : null, url: r ? r.updateUrl : ed.storeUrl, deleteUrl: r ? r.deleteUrl : '',
                    title: v.title ?? (r ? r.title : ''), startsAt: v.startsAt ?? (r ? r.startsAt : ''), endsAt: v.endsAt ?? (r ? r.endsAt : ''),
                    source: ed.isSurvey ? (v.source || (r ? r.source : 'questions')) : 'exam',
                    host: r ? r.host : ed.organizerName, locked: !!(r && r.locked),
                };
                this.$nextTick(() => { const d = this.$refs.roundDialog; if (d && !d.open) d.showModal(); });
            },
            closeRound() { const d = this.$refs.roundDialog; if (d && d.open) d.close(); },

            // ── Hộp thoại "Lấy đề có sẵn" (bản mẫu: ContestPaperSettings.jsx) ──
            picker: { url: '', roundId: null, source: 'exam', assessmentId: '', formCount: '1', mode: 'random', q: '', locked: false },
            get pickerOptions() {
                const ed = cfg.roundEditor; if (!ed) return [];
                const q = norm(this.picker.q.trim());
                return ed.options.filter((o) => q === '' || norm(o.title + ' ' + o.id).includes(q) || String(o.id) === this.picker.assessmentId).slice(0, 300);
            },
            usesOther(o) { return (o.uses || []).filter((u) => u.roundId !== this.picker.roundId); },
            openPicker() {
                const ed = cfg.roundEditor; const r = ed && cfg.roundId ? ed.rounds[cfg.roundId] : null; if (!r) return;
                this.picker = { url: r.examUrl, roundId: r.id, source: r.source, assessmentId: String(r.assessmentId || ''), formCount: String(r.formCount || 1), mode: r.assignmentMode || 'random', q: '', locked: !!(r.locked && !ed.isSurvey) };
                this.$nextTick(() => { const d = this.$refs.pickerDialog; if (d && !d.open) d.showModal(); });
            },
            closePicker() { const d = this.$refs.pickerDialog; if (d && d.open) d.close(); },

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
                const q = norm(this.peopleQuery.trim());
                return q === '' || key.includes(q);
            },
        };
    }
</script>
@endpush
