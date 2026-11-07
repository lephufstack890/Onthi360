{{-- SỬA 6/11 — Tab 5 · Bài tập / Đề luyện tập. Danh sách bài giao + giao đề (form cũ teacher.classes.assign, đặt trong popup) +
     số em đã nộp + nhắc em chưa nộp. Dữ liệu gốc: ClassRoomService::showForTeacher('assign'); số liệu: ClassSpaceService::assignmentStats. --}}
@php
    $assignments = $assignments ?? [];
    $assignableAssessments = $assignableAssessments ?? [];
    $asSt = $spaceData ?? [];
    $asRoster = (int) ($asSt['roster'] ?? 0);
    $asCan = ($space['can']['materials'] ?? false) && ! auth()->user()->isPermissionRevoked('teacher.assign-assessment');
    $asTone = ['Đang mở' => 'p-late', 'Đã lên lịch' => 'p-ex', 'Đã đóng' => 'p-gray', 'Nháp' => 'p-gray', 'Đã lưu trữ' => 'p-gray'];
    $asFilterOf = fn ($l) => match ($l) { 'Đang mở' => 'open', 'Đã lên lịch' => 'sched', default => 'closed' };
    $asCount = collect($assignments)->countBy(fn ($a) => $asFilterOf($a['statusLabel']));
@endphp
@if (session('status') === 'class-exam-assigned')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã giao cho lớp — học sinh thấy ở mục Bài được giao.'])
@endif
@if ($errors->any())
    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
@endif

<div class="csp-card" x-data="cspAssign(@js(['url' => route('teacher.classes.practice.sources', $classRoom->id), 'open' => $errors->has('assessment_id') || old('assessment_id') || $errors->has('items') || $errors->has('deadline'), 'mode' => ($errors->has('assessment_id') || old('assessment_id')) ? 'own' : 'exam']))" @keydown.escape.window="open && close()">
    <div class="csp-ch">
        <span class="csp-ic o"><x-lucide name="clipboard-list" /></span>
        <div class="grow"><h3>Bài tập / Đề luyện tập</h3><p>Giao cho cả lớp, theo dõi em nào đã nộp, nhắc em chưa nộp.</p></div>
        <nav class="csp-seg">
            <button type="button" :class="{ on: f === 'all' }" @click="f = 'all'">Tất cả {{ count($assignments) }}</button>
            <button type="button" :class="{ on: f === 'open' }" @click="f = 'open'">Đang mở {{ $asCount['open'] ?? 0 }}</button>
            <button type="button" :class="{ on: f === 'sched' }" @click="f = 'sched'">Hẹn giờ {{ $asCount['sched'] ?? 0 }}</button>
            <button type="button" :class="{ on: f === 'closed' }" @click="f = 'closed'">Đã đóng {{ $asCount['closed'] ?? 0 }}</button>
        </nav>
        @if ($asCan)
            <button type="button" class="csp-btn pri" @click="openModal('exam')"><x-lucide name="plus" />Thêm bài / đề</button>
        @endif
    </div>

    @if (empty($assignments))
        <div class="csp-empty"><x-lucide name="clipboard-list" /><b>Lớp chưa có bài nào được giao</b><span>Bấm <b>Thêm bài / đề</b> để chọn đề kiểm tra, đề luyện tập hoặc bài luyện tập.</span></div>
    @else
        <div class="asg-grid">
            @foreach ($assignments as $ag)
                @php
                    $done = (int) ($asSt['submitted'][$ag['id']] ?? 0);
                    $wait = (int) ($asSt['grading'][$ag['id']] ?? 0);
                    $pct = $asRoster > 0 ? min(100, round($done / $asRoster * 100)) : 0;
                    $isExam = in_array($asSt['types'][$ag['id']] ?? '', ['exam', 'test', 'contest'], true);
                @endphp
                <article class="asg-card" x-show="f === 'all' || f === '{{ $asFilterOf($ag['statusLabel']) }}'">
                    <span class="csp-ic {{ $isExam ? 'o' : '' }}" style="width:44px;height:44px"><x-lucide :name="$isExam ? 'target' : 'clipboard-list'" /></span>
                    <div class="asg-main">
                        <div class="asg-top"><b>{{ $ag['title'] }}</b><span class="csp-pill {{ $asTone[$ag['statusLabel']] ?? 'p-gray' }}">{{ $ag['statusLabel'] }}</span>@if ($wait > 0)<span class="csp-pill p-ex">{{ $wait }} bài chờ chấm</span>@endif</div>
                        <div class="asg-meta"><x-lucide name="clock-3" />Mở: {{ $ag['opensAtLabel'] }} · Đóng: {{ $ag['closesAtLabel'] }}</div>
                        @if ($ag['instructions'])<p class="asg-ins">{{ $ag['instructions'] }}</p>@endif
                        <div class="asg-prog"><div><i style="width: {{ $pct }}%; background: {{ $pct >= 100 ? '#16A34A' : '#2563EB' }}"></i></div><b>{{ $done }}/{{ $asRoster }} đã nộp</b></div>
                        <div class="asg-acts">
                            <a href="{{ route('teacher.results.index', ['class' => $classRoom->id, 'assessment' => $ag['id']]) }}" class="csp-btn sm"><x-lucide name="eye" />Xem bài nộp</a>
                            @if (($space['can']['materials'] ?? false) && $done < $asRoster && $ag['statusLabel'] === 'Đang mở')
                                <form method="POST" action="{{ route('teacher.classes.assignments.remind', ['class' => $classRoom->id, 'assignment' => $ag['id']]) }}">
                                    @csrf
                                    <button type="submit" class="csp-btn sm"><x-lucide name="bell" />Nhắc {{ $asRoster - $done }} em chưa nộp</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    {{-- SỬA 6/11 — đề / bài LUYỆN TẬP đã giao cho học sinh của lớp (hệ "giao bài luyện tập", mỗi em một lượt, có hạn nộp). --}}
    @php $asPr = $asSt['practiceAssigned'] ?? []; @endphp
    @if (! empty($asPr))
        <h4 class="asg-sub"><x-lucide name="target" />Luyện tập đã giao cho lớp <span>{{ count($asPr) }}</span></h4>
        <div class="asg-plist">
            @foreach ($asPr as $pr)
                @php $pp = $pr['total'] > 0 ? round($pr['done'] / $pr['total'] * 100) : 0; @endphp
                <div class="asg-prow">
                    <span class="csp-ic {{ $pr['type'] === 'exam' ? 'o' : '' }}" style="width:38px;height:38px"><x-lucide :name="$pr['type'] === 'exam' ? 'target' : 'code-2'" /></span>
                    <div class="asg-main">
                        <div class="asg-top">
                            @if ($pr['href'])<a href="{{ $pr['href'] }}" target="_blank" class="asg-link">{{ $pr['title'] }}</a>@else<b>{{ $pr['title'] }}</b>@endif
                            <span class="csp-pill p-gray">{{ $pr['kind'] }}</span>
                        </div>
                        <div class="asg-meta"><x-lucide name="clock-3" /><span @class(['asg-late' => $pr['overdue']])>Hạn {{ $pr['deadline'] }}{{ $pr['overdue'] ? ' (đã quá hạn)' : '' }}</span>@if ($pr['by']) · {{ $pr['by'] }}@endif</div>
                    </div>
                    <div class="asg-prog" style="flex:0 0 220px;margin:0"><div><i style="width: {{ $pp }}%; background: {{ $pp >= 100 ? '#16A34A' : '#2563EB' }}"></i></div><b>{{ $pr['done'] }}/{{ $pr['total'] }} đã làm</b></div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($asCan)
        <template x-teleport="body">
            <div class="tam-ov" x-show="open" x-transition.opacity x-cloak @click.self="close()">
                <div class="tam-dlg asg-dlg" x-show="open" x-transition.scale.95 role="dialog" aria-modal="true">
                    <header class="tam-head" style="background:linear-gradient(120deg,#FFF1E6,#EEF4FF)">
                        <span class="tam-hic" style="background:linear-gradient(135deg,#F97316,#EA580C)"><x-lucide name="clipboard-list" /></span>
                        <div class="tam-htx"><h3>Thêm bài / đề cho lớp</h3><p>{{ $className }} · {{ $asRoster }} học sinh</p></div>
                        <button type="button" class="tam-x" @click="close()" aria-label="Đóng"><x-lucide name="x" /></button>
                    </header>
                    <div class="tam-seg">
                        <button type="button" :class="{ on: mode === 'exam' }" @click="setMode('exam')"><x-lucide name="target" />Đề luyện tập</button>
                        <button type="button" :class="{ on: mode === 'problem' }" @click="setMode('problem')"><x-lucide name="code-2" />Bài luyện tập</button>
                        <button type="button" :class="{ on: mode === 'own' }" @click="setMode('own')"><x-lucide name="clipboard-list" />Đề kiểm tra (mở – đóng)</button>
                    </div>

                    {{-- Đề / bài LUYỆN TẬP: chọn nhiều, đặt hạn nộp → mọi học sinh của lớp nhận lượt giao --}}
                    <form method="POST" action="{{ route('teacher.classes.practice.assign', $classRoom->id) }}" x-show="mode !== 'own'" @submit="busy = true" class="asg-form">
                        @csrf
                        <template x-for="k in picked" :key="'h' + k"><input type="hidden" name="items[]" :value="k"></template>
                        <div class="tam-body" style="display:grid;gap:12px;padding-top:14px">
                            <div class="csp-note" style="padding:8px 12px"><x-lucide name="info" /><span x-text="seeAll ? 'Hiện TẤT CẢ ' + (mode === 'exam' ? 'đề' : 'bài') + ' luyện tập đã phát hành trên hệ thống.' : 'Chỉ hiện ' + (mode === 'exam' ? 'đề' : 'bài') + ' luyện tập do bạn tạo và đã phát hành.'"></span></div>
                            <label class="tam-input on">
                                <x-lucide name="search" />
                                <input type="text" x-ref="q" x-model="q" :placeholder="mode === 'exam' ? 'Tìm tên hoặc mã đề…' : 'Tìm tên hoặc mã bài…'" autocomplete="off">
                                <span class="tam-spin" x-show="loading"></span>
                            </label>
                            <div class="asg-pick">
                                <div class="asg-pick-h"><span x-text="loading ? 'Đang tải…' : (filtered.length + ' ' + (mode === 'exam' ? 'đề' : 'bài') + (filtered.length > shown.length ? ' · hiện ' + shown.length + ' đầu, gõ để tìm thêm' : ''))"></span><span>Đã chọn <b x-text="picked.length"></b></span></div>
                                <ul class="tam-list" role="listbox" aria-multiselectable="true">
                                    <template x-for="it in shown" :key="it.key">
                                        <li role="option" :aria-selected="isSel(it.key)" :class="{ sel: isSel(it.key) }" @click="toggle(it.key)">
                                            <span class="tam-ck"><x-lucide name="check" /></span>
                                            <span class="tam-who"><b x-text="it.title"></b><small x-text="it.code + ' · ' + it.kind"></small></span>
                                        </li>
                                    </template>
                                </ul>
                                <p class="tam-empty" x-show="!loading && loaded && filtered.length === 0" x-text="q.trim() ? 'Không tìm thấy — thử tên hoặc mã khác.' : (seeAll ? 'Chưa có nội dung luyện tập nào đã phát hành.' : 'Bạn chưa có ' + (mode === 'exam' ? 'đề' : 'bài') + ' luyện tập nào đã phát hành — tạo ở Kho bài tập / câu hỏi và đề.')"></p>
                            </div>
                            <div class="asg-two">
                                <div><label class="csp-lb" for="asg-dl">Hạn nộp</label><input id="asg-dl" type="datetime-local" name="deadline" x-model="deadline" required class="csp-inp" style="width:100%"></div>
                                <div class="asg-quick"><span class="csp-lb">Chọn nhanh</span><div><button type="button" @click="plusDays(3)">3 ngày</button><button type="button" @click="plusDays(7)">1 tuần</button><button type="button" @click="plusDays(14)">2 tuần</button></div></div>
                            </div>
                        </div>
                        <footer class="tam-foot">
                            <small><x-lucide name="bell" />Học sinh nhận thông báo và thấy ở Luyện tập → Được giao.</small>
                            <div class="tam-acts">
                                <button type="button" class="tam-btn ghost" @click="close()">Huỷ</button>
                                <button type="submit" class="tam-btn pri" style="background:linear-gradient(135deg,#F97316,#EA580C)" :disabled="!picked.length || !deadline || busy"><x-lucide name="check" /><span x-text="busy ? 'Đang giao…' : (picked.length ? 'Giao ' + picked.length + ' cho ' + {{ $asRoster }} + ' học sinh' : 'Chọn đề / bài')"></span></button>
                            </div>
                        </footer>
                    </form>

                    {{-- Đề kiểm tra có giờ mở – đóng (luồng giao đề cũ, teacher.classes.assign) --}}
                    <form method="POST" action="{{ route('teacher.classes.assign', $classRoom->id) }}" x-show="mode === 'own'" x-cloak class="asg-form">
                        @csrf
                        @foreach (['opens', 'closes'] as $px)
                            <input type="hidden" name="{{ $px }}_day" :value="{{ $px }} ? {{ $px }}.slice(8, 10) : ''">
                            <input type="hidden" name="{{ $px }}_month" :value="{{ $px }} ? {{ $px }}.slice(5, 7) : ''">
                            <input type="hidden" name="{{ $px }}_year" :value="{{ $px }} ? {{ $px }}.slice(0, 4) : ''">
                            <input type="hidden" name="{{ $px }}_hour" :value="{{ $px }} ? {{ $px }}.slice(11, 13) : ''">
                            <input type="hidden" name="{{ $px }}_minute" :value="{{ $px }} ? {{ $px }}.slice(14, 16) : ''">
                        @endforeach
                        <div class="tam-body" style="display:grid;gap:14px;padding-top:14px">
                            @if (empty($assignableAssessments))
                                <div class="csp-note"><x-lucide name="info" /><span>Bạn chưa có đề kiểm tra nào. Tạo đề ở <a href="{{ route('teacher.questions.index', ['tab' => 'assessments']) }}" style="font-weight:700;color:#1D4ED8">Kho bài tập / câu hỏi và đề</a>, hoặc giao <b>Đề / Bài luyện tập</b> ở 2 mục bên cạnh.</span></div>
                            @else
                                <div>
                                    <label class="csp-lb" for="asg-ass">Chọn đề</label>
                                    <select id="asg-ass" name="assessment_id" required class="csp-inp" style="width:100%">
                                        <option value="">— Chọn đề —</option>
                                        @foreach ($assignableAssessments as $ass)
                                            <option value="{{ $ass['id'] }}" @selected((string) old('assessment_id') === (string) $ass['id'])>{{ $ass['title'] }}{{ $ass['status'] === 'Nháp' ? ' (Nháp — tự phát hành khi giao)' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="asg-two">
                                    <div><label class="csp-lb">Mở lúc <em class="asg-opt">(không bắt buộc)</em></label><input type="datetime-local" x-model="opens" class="csp-inp" style="width:100%"></div>
                                    <div><label class="csp-lb">Đóng lúc / hạn nộp <em class="asg-opt">(không bắt buộc)</em></label><input type="datetime-local" x-model="closes" class="csp-inp" style="width:100%"></div>
                                </div>
                                <div>
                                    <label class="csp-lb" for="asg-shift">Chia ca thi <em class="asg-opt">(không bắt buộc — cần đủ Mở lúc + Đóng lúc)</em></label>
                                    <input id="asg-shift" name="shift_count" type="number" min="1" max="20" value="{{ old('shift_count') }}" class="csp-inp" style="width:160px" placeholder="VD: 3">
                                </div>
                                <div>
                                    <label class="csp-lb" for="asg-ins">Hướng dẫn làm bài <em class="asg-opt">(không bắt buộc)</em></label>
                                    <textarea id="asg-ins" name="instructions" rows="2" class="csp-inp" style="width:100%" placeholder="Ví dụ: Làm trong 45 phút, nộp trước 21:00.">{{ old('instructions') }}</textarea>
                                </div>
                            @endif
                        </div>
                        <footer class="tam-foot">
                            <small><x-lucide name="info" />Đề tự phát hành nếu mọi câu đã đủ điều kiện; điểm vào tab Kết quả học tập.</small>
                            <div class="tam-acts">
                                <button type="button" class="tam-btn ghost" @click="close()">Huỷ</button>
                                @if (! empty($assignableAssessments))<button type="submit" class="tam-btn pri" style="background:linear-gradient(135deg,#F97316,#EA580C)"><x-lucide name="check" />Giao cho lớp</button>@endif
                            </div>
                        </footer>
                    </form>
                </div>
            </div>
        </template>
    @endif
</div>
<style>
    .asg-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    @media (max-width: 1000px) { .asg-grid { grid-template-columns: minmax(0, 1fr); } }
    .asg-card { display: flex; align-items: flex-start; gap: 14px; padding: 16px; border: 1px solid #E5EDF5; border-radius: 16px; background: #fff; }
    .asg-main { flex: 1; min-width: 0; }
    .asg-top { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; }
    .asg-top b { font-size: 14px; color: #0F2F57; }
    .asg-meta { display: flex; align-items: center; gap: 5px; margin-top: 4px; font-size: 12px; color: #64748B; }
    .asg-meta svg { width: 13px; height: 13px; }
    .asg-ins { margin: 6px 0 0; font-size: 12px; color: #475569; }
    .asg-prog { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
    .asg-prog div { flex: 1; height: 8px; border-radius: 9px; background: #EEF2F7; overflow: hidden; }
    .asg-prog i { display: block; height: 100%; border-radius: 9px; }
    .asg-prog b { font-size: 12px; white-space: nowrap; color: #334155; }
    .asg-acts { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .asg-acts form { margin: 0; }
    .asg-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .asg-opt { font-style: normal; font-weight: 600; color: #94A3B8; }
    @media (max-width: 560px) { .asg-two { grid-template-columns: 1fr; } }
    .asg-sub { display: flex; align-items: center; gap: 8px; margin: 22px 0 10px; font-size: 14px; font-weight: 700; color: #0F2F57; }
    .asg-sub svg { width: 17px; height: 17px; color: #EA580C; }
    .asg-sub span { padding: 1px 8px; border-radius: 999px; background: #FFF1E6; color: #C2410C; font-size: 12px; }
    .asg-plist { display: grid; gap: 8px; }
    .asg-prow { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; padding: 12px 14px; border: 1px solid #E5EDF5; border-radius: 14px; background: #fff; }
    .asg-link { font-size: 14px; font-weight: 700; color: #0F2F57; text-decoration: none; } .asg-link:hover { color: #1D4ED8; text-decoration: underline; }
    .asg-late { color: #DC2626; font-weight: 700; }
    .asg-dlg { width: min(760px, 100%); }
    .asg-form { display: flex; flex-direction: column; min-height: 0; flex: 1; }
    .asg-form .tam-body { flex: 1; }
    .asg-pick { border: 1px solid #E2EBF2; border-radius: 14px; overflow: hidden; }
    .asg-pick-h { display: flex; justify-content: space-between; gap: 8px; padding: 8px 12px; border-bottom: 1px solid #EEF3F7; background: #F8FAFC; font-size: 12px; font-weight: 700; color: #7B8EA0; }
    .asg-pick-h b { color: #EA580C; }
    .asg-pick .tam-list { max-height: min(300px, 40vh); }
    .asg-quick div { display: flex; flex-wrap: wrap; gap: 6px; }
    .asg-quick button { height: 38px; padding: 0 12px; border: 1px solid #DCE6F2; border-radius: 10px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; }
    .asg-quick button:hover { border-color: #FDBA74; color: #C2410C; background: #FFF7ED; }
    @media (max-width: 640px) { .asg-prow .asg-prog { flex: 1 1 100% !important; } }
</style>
<script>
    window.cspAssign = function (cfg) {
        const fold = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
        const pad = (n) => String(n).padStart(2, '0');
        return {
            f: 'all', open: !!cfg.open, mode: cfg.mode || 'exam', q: '', src: { exams: [], problems: [] }, seeAll: false, loaded: false, loading: false,
            picked: [], deadline: '', busy: false, opens: '', closes: '',
            init() { this.plusDays(7); if (this.open) this.openModal(this.mode); },
            openModal(m) { this.open = true; this.busy = false; document.documentElement.style.overflow = 'hidden'; this.setMode(m || this.mode); this.load(); },
            close() { this.open = false; document.documentElement.style.overflow = ''; },
            setMode(m) { this.mode = m; this.q = ''; this.$nextTick && this.$nextTick(() => this.$refs.q && m !== 'own' && this.$refs.q.focus()); },
            load() {
                if (this.loaded || this.loading) return;
                this.loading = true;
                fetch(cfg.url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then((r) => r.ok ? r.json() : { exams: [], problems: [] })
                    .then((j) => {
                        const add = (a) => (a || []).map((x) => ({ ...x, f: fold(x.title + ' ' + x.code) }));
                        this.src = { exams: add(j.exams), problems: add(j.problems) }; this.seeAll = !!j.seeAll; this.loaded = true; this.loading = false;
                    })
                    .catch(() => { this.loading = false; });
            },
            get list() { return this.mode === 'exam' ? this.src.exams : this.src.problems; },
            get filtered() { const k = fold(this.q.trim()); return k ? this.list.filter((x) => x.f.includes(k)) : this.list; },
            get shown() { return this.filtered.slice(0, 150); },
            isSel(k) { return this.picked.includes(k); },
            toggle(k) { if (this.isSel(k)) { this.picked = this.picked.filter((x) => x !== k); } else if (this.picked.length < 30) { this.picked.push(k); } else { window.toast && window.toast('error', 'Mỗi lần giao tối đa 30 đề / bài.'); } },
            plusDays(n) { const d = new Date(); d.setDate(d.getDate() + n); d.setHours(23, 59, 0, 0); this.deadline = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T23:59'; },
        };
    };
</script>
