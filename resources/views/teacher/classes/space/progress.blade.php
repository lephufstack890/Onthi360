{{-- SỬA 6/11 (khách: "Tiến trình dạy học — thêm các tiến trình và mở khoá các tiến trình") — Tab 6. Các bước theo thứ tự, mỗi bước
     gắn tài liệu / bài tập của lớp; công tắc mở khoá (học sinh chỉ thấy bước đã mở, mở là báo học sinh); hẹn ngày tự mở; ↑↓ sắp xếp.
     Dữ liệu: ClassSpaceService::progress(). --}}
@php
    $pg = $spaceData ?? [];
    $pgCan = (bool) ($space['can']['progress'] ?? false);
    $pgSteps = $pg['steps'] ?? [];
    $pgTotal = (int) ($pg['total'] ?? 0);
    $pgOpen = (int) ($pg['open'] ?? 0);
    $pgPct = $pgTotal > 0 ? round($pgOpen / $pgTotal * 100) : 0;
@endphp

@if (! ($pg['ready'] ?? false))
    <div class="csp-card"><div class="csp-empty"><x-lucide name="alert-triangle" /><b>Chưa cập nhật cơ sở dữ liệu</b><span>Quản trị chạy <code>php artisan migrate --force</code> trên máy chủ để dùng Tiến trình dạy học.</span></div></div>
@else
<div class="csp-grid pg-layout" x-data="cspSteps(@js(['materials' => $pg['materials'], 'assignments' => $pg['assignments'], 'store' => route('teacher.classes.steps.store', $classRoom->id), 'update' => route('teacher.classes.steps.update', ['class' => $classRoom->id, 'step' => 0])]))" @keydown.escape.window="open && close()">
    <div class="csp-card" style="margin:0">
        <div class="csp-ch">
            <span class="csp-ic v"><x-lucide name="layers" /></span>
            <div class="grow"><h3>Tiến trình dạy học</h3><p>Xếp các bước theo thứ tự dạy · bật công tắc để <b>mở khoá</b> cho học sinh (mở là các em nhận thông báo).</p></div>
            @if ($pgCan)<button type="button" class="csp-btn pri" @click="edit(null)"><x-lucide name="plus" />Thêm bước</button>@endif
        </div>

        @forelse ($pgSteps as $i => $s)
            @php $state = $s['unlocked'] ? ($s['current'] ? 'now' : 'done') : 'lock'; @endphp
            <div class="pg-step">
                <div class="pg-dot {{ $state }}">
                    <b>@if ($state === 'done')<x-lucide name="check" />@elseif ($state === 'lock')<x-lucide name="lock" />@else{{ $i + 1 }}@endif</b>
                    @if (! $loop->last)<i></i>@endif
                </div>
                <div class="pg-box {{ $state }}">
                    <div class="pg-top">
                        @if ($pgCan)
                            <span class="pg-move">
                                <form method="POST" action="{{ route('teacher.classes.steps.move', ['class' => $classRoom->id, 'step' => $s['id']]) }}">@csrf<input type="hidden" name="dir" value="up"><button type="submit" @disabled($loop->first) title="Lên trên" aria-label="Chuyển lên"><x-lucide name="chevron-up" /></button></form>
                                <form method="POST" action="{{ route('teacher.classes.steps.move', ['class' => $classRoom->id, 'step' => $s['id']]) }}">@csrf<input type="hidden" name="dir" value="down"><button type="submit" @disabled($loop->last) title="Xuống dưới" aria-label="Chuyển xuống"><x-lucide name="chevron-down" /></button></form>
                            </span>
                        @endif
                        <div class="pg-tx">
                            <b>Bước {{ $i + 1 }}. {{ $s['title'] }}</b>
                            <small>
                                @if ($s['unlocked'])Đã mở{{ $s['unlockedAt'] ? ' '.$s['unlockedAt'] : '' }}@elseif ($s['unlockOnLabel'])Chưa mở · hẹn tự mở {{ $s['unlockOnLabel'] }}@else Chưa mở @endif
                                @if ($s['done'] !== null) · {{ $s['done'] }}/{{ $s['roster'] }} học sinh hoàn thành @endif
                            </small>
                        </div>
                        @if ($s['current'])<span class="csp-pill p-late">Đang học</span>@endif
                        <span class="pg-state">{{ $s['unlocked'] ? 'Đã mở' : 'Đang khoá' }}</span>
                        <form method="POST" action="{{ route('teacher.classes.steps.toggle', ['class' => $classRoom->id, 'step' => $s['id']]) }}" style="margin:0">
                            @csrf
                            <button type="submit" @class(['csp-sw', 'on' => $s['unlocked']]) @disabled(! $pgCan) title="{{ $s['unlocked'] ? 'Khoá lại' : 'Mở khoá cho học sinh' }}" aria-label="{{ $s['unlocked'] ? 'Khoá lại bước' : 'Mở khoá bước' }}"></button>
                        </form>
                        @if ($pgCan)
                            <button type="button" class="csp-btn sm icon" @click="edit(@js($s))" title="Sửa bước" aria-label="Sửa bước"><x-lucide name="pencil" /></button>
                        @endif
                    </div>
                    @if ($s['description'] !== '')<p class="pg-desc">{{ $s['description'] }}</p>@endif
                    <div class="pg-items">
                        @foreach ($s['items'] as $it)
                            <span class="pg-chip {{ $it['type'] }}"><x-lucide :name="$it['type'] === 'material' ? 'book-open' : 'clipboard-list'" />{{ $it['title'] }}</span>
                        @endforeach
                        @if ($pgCan)<button type="button" class="pg-chip add" @click="edit(@js($s))">+ Gắn tài liệu / bài tập</button>@endif
                    </div>
                    @if ($s['pct'] !== null && $s['unlocked'])
                        <div class="pg-bar"><i style="width: {{ $s['pct'] }}%"></i></div>
                    @endif
                </div>
            </div>
        @empty
            <div class="csp-empty"><x-lucide name="layers" /><b>Chưa có bước nào</b><span>Bấm <b>Thêm bước</b> — ví dụ "Tuần 1: Làm quen C++", gắn tài liệu và bài tập cho bước đó, rồi mở khoá khi lên lớp.</span></div>
        @endforelse
    </div>

    <div class="csp-grid" style="align-content:start">
        <div class="csp-card" style="margin:0">
            <h3 class="pg-h">Tổng quan tiến trình</h3>
            <div style="display:flex;align-items:center;gap:14px">
                <div class="pg-ring" style="background: conic-gradient(#7C3AED 0 {{ $pgPct }}%, #EDE9FE {{ $pgPct }}% 100%)"><span>{{ $pgOpen }}/{{ $pgTotal }}</span></div>
                <div><b style="color:#0F2F57">Đã mở {{ $pgOpen }} bước</b><div style="font-size:12px;color:#64748B">Còn {{ max(0, $pgTotal - $pgOpen) }} bước chưa mở</div></div>
            </div>
        </div>
        <div class="csp-card" style="margin:0">
            <h3 class="pg-h">Cách dùng</h3>
            <ul class="pg-tips">
                <li><x-lucide name="plus" /><span>Thêm bước theo thứ tự bài dạy, gắn tài liệu &amp; bài tập đã có ở tab Tài liệu, Bài tập / Đề.</span></li>
                <li><x-lucide name="lock" /><span>Học sinh chỉ thấy bước <b>đã mở</b> (trang lớp của em → Tiến trình học).</span></li>
                <li><x-lucide name="calendar-days" /><span>Đặt "Hẹn ngày mở" để bước tự mở vào ngày đó.</span></li>
                <li><x-lucide name="check-circle-2" /><span>% hoàn thành = số em đã nộp đủ bài tập của bước.</span></li>
            </ul>
        </div>
    </div>

    @if ($pgCan)
        <template x-teleport="body">
            <div class="tam-ov" x-show="open" x-transition.opacity x-cloak @click.self="close()">
                <form class="tam-dlg" method="POST" :action="action" x-show="open" x-transition.scale.95 role="dialog" aria-modal="true" @submit="busy = true">
                    @csrf
                    <template x-if="id"><input type="hidden" name="_method" value="PUT"></template>
                    <header class="tam-head" style="background:linear-gradient(120deg,#F3EEFF,#EEF4FF)">
                        <span class="tam-hic" style="background:linear-gradient(135deg,#7C3AED,#6D28D9)"><x-lucide name="layers" /></span>
                        <div class="tam-htx"><h3 x-text="id ? 'Sửa bước' : 'Thêm bước vào tiến trình'"></h3><p>{{ $className }}</p></div>
                        <button type="button" class="tam-x" @click="close()" aria-label="Đóng"><x-lucide name="x" /></button>
                    </header>
                    <div class="tam-body" style="display:grid;gap:14px;padding-top:16px">
                        <div><label class="csp-lb" for="pg-title">Tên bước</label><input id="pg-title" x-ref="title" name="title" x-model="title" required maxlength="200" class="csp-inp" style="width:100%" placeholder="Ví dụ: Tuần 3 — Vòng lặp for / while"></div>
                        <div><label class="csp-lb" for="pg-desc">Mô tả / mục tiêu <em class="asg-opt">(không bắt buộc)</em></label><textarea id="pg-desc" name="description" x-model="description" rows="2" maxlength="2000" class="csp-inp" style="width:100%" placeholder="Học sinh cần nắm được…"></textarea></div>
                        <div>
                            <label class="csp-lb">Tài liệu của lớp gắn vào bước</label>
                            <div class="pg-pick" x-show="materials.length">
                                <template x-for="m in materials" :key="'m' + m.id"><label :class="{ on: mIds.includes(m.id) }"><input type="checkbox" name="material_ids[]" :value="m.id" x-model.number="mIds"><span x-text="'📘 ' + m.title"></span></label></template>
                            </div>
                            <p class="pg-none" x-show="!materials.length">Lớp chưa gắn tài liệu nào (tab Tài liệu).</p>
                        </div>
                        <div>
                            <label class="csp-lb">Bài tập / đề của lớp gắn vào bước</label>
                            <div class="pg-pick" x-show="assignments.length">
                                <template x-for="a in assignments" :key="'a' + a.id"><label :class="{ on: aIds.includes(a.id) }"><input type="checkbox" name="assignment_ids[]" :value="a.id" x-model.number="aIds"><span x-text="'📝 ' + a.title"></span></label></template>
                            </div>
                            <p class="pg-none" x-show="!assignments.length">Lớp chưa giao bài nào (tab Bài tập / Đề).</p>
                        </div>
                        <div><label class="csp-lb" for="pg-on">Hẹn ngày tự mở khoá <em class="asg-opt">(không bắt buộc)</em></label><input id="pg-on" type="date" name="unlock_on" x-model="unlockOn" class="csp-inp"></div>
                    </div>
                    <footer class="tam-foot">
                        <template x-if="id">
                            <button type="button" class="tam-btn ghost" style="color:#DC2626" @click="if (confirmDel) { $refs.del.submit() } else { confirmDel = true }" x-text="confirmDel ? 'Bấm lần nữa để xoá' : 'Xoá bước'"></button>
                        </template>
                        <div class="tam-acts">
                            <button type="button" class="tam-btn ghost" @click="close()">Huỷ</button>
                            <button type="submit" class="tam-btn pri" style="background:linear-gradient(135deg,#7C3AED,#6D28D9)" :disabled="!title.trim() || busy"><x-lucide name="check" /><span x-text="id ? 'Lưu bước' : 'Thêm bước'"></span></button>
                        </div>
                    </footer>
                </form>
            </div>
        </template>
        <form x-ref="del" method="POST" :action="delAction" style="display:none">@csrf<input type="hidden" name="_method" value="DELETE"></form>
    @endif
</div>
@endif

<style>
    .pg-layout { grid-template-columns: minmax(0, 1fr) 320px; }
    @media (max-width: 1100px) { .pg-layout { grid-template-columns: minmax(0, 1fr); } }
    .pg-step { display: flex; gap: 12px; }
    .pg-dot { width: 36px; flex: none; display: flex; flex-direction: column; align-items: center; }
    .pg-dot b { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; font-weight: 900; font-size: 13px; z-index: 1; }
    .pg-dot b svg { width: 15px; height: 15px; }
    .pg-dot i { flex: 1; width: 3px; margin: 4px 0; background: #E2E8F0; }
    .pg-dot.done b { background: #DCFCE7; color: #16A34A; } .pg-dot.now b { background: #DBEAFE; color: #2563EB; } .pg-dot.lock b { background: #F1F5F9; color: #94A3B8; }
    .pg-box { flex: 1; min-width: 0; margin-bottom: 12px; padding: 14px 16px; border: 1px solid #E5EDF5; border-radius: 16px; background: #fff; }
    .pg-box.done { background: #F7FCF9; border-color: #CDEBDA; } .pg-box.now { border-color: #93C5FD; box-shadow: 0 0 0 4px rgba(59, 130, 246, .1); } .pg-box.lock { background: #FAFBFC; }
    .pg-top { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; }
    .pg-move { display: grid; gap: 2px; } .pg-move form { margin: 0; }
    .pg-move button { display: grid; place-items: center; width: 22px; height: 16px; padding: 0; border: 0; border-radius: 5px; background: #F1F5F9; color: #64748B; cursor: pointer; }
    .pg-move button:hover:not(:disabled) { background: #E0E7FF; color: #4338CA; } .pg-move button:disabled { opacity: .35; cursor: default; }
    .pg-move svg { width: 13px; height: 13px; }
    .pg-tx { flex: 1 1 220px; min-width: 0; } .pg-tx b { display: block; font-size: 14px; color: #0F2F57; } .pg-tx small { display: block; margin-top: 2px; font-size: 12px; color: #64748B; }
    .pg-state { font-size: 12px; font-weight: 700; color: #64748B; }
    .pg-desc { margin: 8px 0 0; font-size: 12.5px; line-height: 1.55; color: #475569; white-space: pre-line; }
    .pg-items { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .pg-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; border: 0; border-radius: 9px; background: #F1F5F9; font: inherit; font-size: 11.5px; font-weight: 700; color: #475569; }
    .pg-chip svg { width: 13px; height: 13px; } .pg-chip.material { background: #EEF4FF; color: #1D4ED8; } .pg-chip.assignment { background: #FFF1E6; color: #C2410C; }
    .pg-chip.add { background: #F5F9FF; color: #2563EB; cursor: pointer; } .pg-chip.add:hover { background: #E6EFFF; }
    .pg-bar { height: 8px; margin-top: 10px; border-radius: 9px; background: #EEF2F7; overflow: hidden; } .pg-bar i { display: block; height: 100%; border-radius: 9px; background: linear-gradient(90deg, #2563EB, #60A5FA); }
    .pg-h { margin: 0 0 12px; font-size: 14px; font-weight: 800; color: #0F2F57; }
    .pg-ring { display: grid; place-items: center; width: 84px; height: 84px; flex: none; border-radius: 50%; }
    .pg-ring span { display: grid; place-items: center; width: 62px; height: 62px; border-radius: 50%; background: #fff; font-weight: 900; font-size: 16px; color: #5B21B6; }
    .pg-tips { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
    .pg-tips li { display: flex; gap: 8px; font-size: 12.5px; line-height: 1.5; color: #475569; } .pg-tips svg { width: 15px; height: 15px; flex: none; margin-top: 2px; color: #7C3AED; }
    .pg-pick { display: flex; flex-wrap: wrap; gap: 6px; max-height: 170px; overflow-y: auto; padding: 2px; }
    .pg-pick label { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border: 1px solid #E2E8F0; border-radius: 10px; font-size: 12.5px; font-weight: 600; color: #334155; cursor: pointer; }
    .pg-pick label.on { border-color: #7C3AED; background: #F5F3FF; color: #5B21B6; }
    .pg-pick input { accent-color: #7C3AED; }
    .pg-none { margin: 0; font-size: 12px; color: #94A3B8; }
    .asg-opt { font-style: normal; font-weight: 600; color: #94A3B8; }
</style>
<script>
    window.cspSteps = function (cfg) {
        return {
            materials: cfg.materials || [], assignments: cfg.assignments || [],
            open: false, id: null, title: '', description: '', unlockOn: '', mIds: [], aIds: [], busy: false, confirmDel: false, action: cfg.store, delAction: '',
            edit(s) {
                this.busy = false; this.confirmDel = false;
                if (s) {
                    this.id = s.id; this.title = s.title; this.description = s.description || ''; this.unlockOn = s.unlockOn || '';
                    this.mIds = s.items.filter((i) => i.type === 'material').map((i) => i.id);
                    this.aIds = s.items.filter((i) => i.type === 'assignment').map((i) => i.id);
                    this.action = cfg.update.replace(/\/0$/, '/' + s.id); this.delAction = this.action;
                } else {
                    this.id = null; this.title = ''; this.description = ''; this.unlockOn = ''; this.mIds = []; this.aIds = []; this.action = cfg.store;
                }
                this.open = true; document.documentElement.style.overflow = 'hidden';
                this.$nextTick(() => this.$refs.title && this.$refs.title.focus());
            },
            close() { this.open = false; document.documentElement.style.overflow = ''; },
        };
    };
</script>
