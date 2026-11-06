{{-- SỬA 6/11 — Tab 2 · Người tổ chức: giáo viên chính + giáo viên đứng lớp cùng (Đồng giảng dạy / Trợ giảng), quyền theo vai trò. --}}
@php
    $og = $spaceData ?? [];
    $ogTone = ['main' => 'p-ex', 'co_teacher' => 'p-late', 'assistant' => 'p-ok'];
@endphp
<div class="csp-card" x-data="cspAddTeacher(@js(['url' => route('teacher.classes.teachers.search', $classRoom->id)]))" @keydown.escape.window="open && close()">
    <div class="csp-ch">
        <span class="csp-ic"><x-lucide name="user-round" /></span>
        <div class="grow"><h3>Người tổ chức lớp</h3><p>Giáo viên chính và giáo viên đứng lớp cùng. Ai trong danh sách cũng thấy lớp này ở mục Lớp học của mình.</p></div>
        @if ($og['canManage'] ?? false)
            <button type="button" class="csp-btn pri" @click="openModal()"><x-lucide name="user-plus" />Thêm giáo viên</button>
        @endif
    </div>

    <div class="ogz-grid">
        @foreach ($og['teachers'] ?? [] as $t)
            <div @class(['ogz-card', 'main' => $t['role'] === 'main']) x-data="{ del: false }">
                @if ($t['role'] === 'main')<span class="ogz-crown"><x-lucide name="crown" /></span>@endif
                <span class="csp-av h{{ $t['hue'] }}" style="width:62px;height:62px;font-size:20px">@if ($t['avatar'])<img src="{{ $t['avatar'] }}" alt="">@else{{ $t['initials'] }}@endif</span>
                <b class="ogz-name">{{ $t['name'] }}@if ($t['me']) <small>(bạn)</small>@endif</b>
                @if ($t['contact'])<span class="ogz-mut">{{ $t['contact'] }}</span>@endif
                <span class="csp-pill {{ $ogTone[$t['role']] ?? 'p-gray' }}">{{ $t['roleLabel'] }}</span>
                <span class="ogz-mut" style="font-size:11.5px">{{ $t['role'] === 'main' ? 'Phụ trách lớp' : 'Tham gia' }}{{ $t['since'] ? ' từ '.$t['since'] : '' }}</span>
                @if ($t['editable'])
                    <div class="ogz-acts" x-show="! del">
                        <form method="POST" action="{{ route('teacher.classes.teachers.role', ['class' => $classRoom->id, 'teacher' => $t['id']]) }}">
                            @csrf
                            <select name="role" class="csp-inp" style="height:32px;font-size:12px;padding:0 8px" onchange="this.form.submit()" aria-label="Đổi vai trò">
                                <option value="co_teacher" @selected($t['role'] === 'co_teacher')>Đồng giảng dạy</option>
                                <option value="assistant" @selected($t['role'] === 'assistant')>Trợ giảng</option>
                            </select>
                        </form>
                        <button type="button" class="csp-btn sm icon red" @click="del = true" title="Gỡ khỏi lớp" aria-label="Gỡ khỏi lớp"><x-lucide name="trash-2" /></button>
                    </div>
                    <form method="POST" action="{{ route('teacher.classes.teachers.remove', ['class' => $classRoom->id, 'teacher' => $t['id']]) }}" class="ogz-del" x-show="del" x-cloak>
                        @csrf
                        @method('DELETE')
                        <span>Gỡ {{ $t['name'] }} khỏi lớp?</span>
                        <div><button type="button" class="csp-btn sm" @click="del = false">Giữ lại</button><button type="submit" class="csp-btn sm red">Gỡ</button></div>
                    </form>
                @endif
            </div>
        @endforeach
        @if ($og['canManage'] ?? false)
            <button type="button" class="ogz-card ogz-add" @click="openModal()">
                <span class="ogz-plus"><x-lucide name="user-plus" /></span>
                <b>Mời thêm giáo viên</b>
                <span class="ogz-mut">Tìm theo tên, email hoặc số điện thoại</span>
            </button>
        @endif
    </div>

    @if ($og['canManage'] ?? false)
        <template x-teleport="body">
            <div class="tam-ov" x-show="open" x-transition.opacity x-cloak @click.self="close()">
                <form class="tam-dlg" method="POST" action="{{ route('teacher.classes.teachers.add', $classRoom->id) }}" x-show="open" x-transition.scale.95 role="dialog" aria-modal="true" @submit="busy = true">
                    @csrf
                    <input type="hidden" name="teacher_id" :value="picked ? picked.id : ''">
                    <header class="tam-head" style="background:linear-gradient(120deg,#EEF4FF,#F0F9FF)">
                        <span class="tam-hic" style="background:linear-gradient(135deg,#2563EB,#1D4ED8)"><x-lucide name="user-plus" /></span>
                        <div class="tam-htx"><h3>Thêm giáo viên vào lớp</h3><p>{{ $className }}</p></div>
                        <button type="button" class="tam-x" @click="close()" aria-label="Đóng"><x-lucide name="x" /></button>
                    </header>
                    <div class="tam-body" style="display:grid;gap:14px;padding-top:16px">
                        <div>
                            <label class="csp-lb">1. Chọn giáo viên</label>
                            <label class="tam-input on">
                                <x-lucide name="search" />
                                <input type="text" x-ref="q" x-model="q" @input="search()" placeholder="Gõ tên, email hoặc số điện thoại giáo viên…" autocomplete="off">
                                <span class="tam-spin" x-show="loading"></span>
                            </label>
                            <ul class="tam-list ogz-list" role="listbox">
                                <template x-for="u in results" :key="u.id">
                                    <li role="option" :aria-selected="picked && picked.id === u.id" :class="{ sel: picked && picked.id === u.id }" @click="picked = u">
                                        <span class="tam-ck"><x-lucide name="check" /></span>
                                        <span class="tam-av" :class="'t' + (u.id % 4)"><img x-show="u.avatar" :src="u.avatar" alt=""><b x-show="!u.avatar" x-text="u.initials"></b></span>
                                        <span class="tam-who"><b x-text="u.name"></b><small x-text="u.contact || 'Giáo viên'"></small></span>
                                    </li>
                                </template>
                            </ul>
                            <p class="tam-empty" x-show="!loading && loaded && results.length === 0" x-text="q.trim() ? 'Không tìm thấy giáo viên phù hợp (chỉ hiện tài khoản có vai trò Giáo viên, chưa ở trong lớp).' : 'Chưa có giáo viên nào khác.'"></p>
                        </div>
                        <div>
                            <label class="csp-lb">2. Vai trò trong lớp</label>
                            <div class="ogz-roles">
                                <label :class="{ on: role === 'co_teacher' }"><input type="radio" name="role" value="co_teacher" x-model="role"><b>Đồng giảng dạy</b><small>Làm được mọi việc trừ thêm / gỡ giáo viên</small></label>
                                <label :class="{ on: role === 'assistant' }"><input type="radio" name="role" value="assistant" x-model="role"><b>Trợ giảng</b><small>Chỉ điểm danh, nhận xét và chấm điểm</small></label>
                            </div>
                        </div>
                    </div>
                    <footer class="tam-foot">
                        <small><x-lucide name="bell" />Giáo viên được thêm sẽ nhận thông báo.</small>
                        <div class="tam-acts">
                            <button type="button" class="tam-btn ghost" @click="close()">Huỷ</button>
                            <button type="submit" class="tam-btn pri" style="background:linear-gradient(135deg,#2563EB,#1D4ED8)" :disabled="!picked || busy"><x-lucide name="user-plus" /><span x-text="picked ? 'Thêm ' + picked.name : 'Chọn một giáo viên'"></span></button>
                        </div>
                    </footer>
                </form>
            </div>
        </template>
    @endif
</div>

<div class="csp-card">
    <div class="csp-ch"><span class="csp-ic g"><x-lucide name="shield-check" /></span><div class="grow"><h3>Quyền theo vai trò</h3><p>Áp dụng cho mọi lớp. Quản trị viên luôn có đủ quyền.</p></div></div>
    <div style="overflow-x:auto">
        <table class="ogz-perm">
            <thead><tr><th>Việc được làm</th><th>Giáo viên chính</th><th>Đồng giảng dạy</th><th>Trợ giảng</th></tr></thead>
            <tbody>
                @foreach ($og['matrix'] ?? [] as $m)
                    <tr>
                        <td>{{ $m['label'] }}</td>
                        @foreach (['main', 'co_teacher', 'assistant'] as $r)
                            <td>@if ($m[$r])<span class="ogz-yes"><x-lucide name="check-circle-2" /></span>@else<span class="ogz-no"><x-lucide name="x" /></span>@endif</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<style>
    .ogz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
    .ogz-card { position: relative; display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 20px 14px 16px; border: 1px solid #E5EDF5; border-radius: 18px; background: #fff; text-align: center; font: inherit; }
    .ogz-card.main { border-color: #FCD34D; background: linear-gradient(180deg, #FFFBEB, #fff); }
    .ogz-crown { position: absolute; top: 12px; right: 12px; color: #F59E0B; } .ogz-crown svg { width: 20px; height: 20px; }
    .ogz-name { font-size: 14px; color: #0F2F57; } .ogz-name small { font-weight: 600; color: #94A3B8; }
    .ogz-mut { font-size: 12px; color: #64748B; }
    .ogz-acts { display: flex; gap: 6px; margin-top: 6px; }
    .ogz-del { display: grid; gap: 8px; margin-top: 6px; padding: 10px; border-radius: 12px; background: #FEF2F2; font-size: 12px; font-weight: 700; color: #991B1B; }
    .ogz-del div { display: flex; justify-content: center; gap: 6px; }
    .ogz-add { justify-content: center; border-style: dashed; background: #FAFCFF; color: #2563EB; cursor: pointer; }
    .ogz-add:hover { border-color: #93C5FD; background: #F2F7FF; }
    .ogz-plus { display: grid; place-items: center; width: 62px; height: 62px; border-radius: 50%; background: #EEF4FF; } .ogz-plus svg { width: 26px; height: 26px; }
    .ogz-list { max-height: 260px; margin-top: 8px; border: 1px solid #E2EBF2; border-radius: 14px; }
    .ogz-list:empty { display: none; }
    .ogz-roles { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .ogz-roles label { display: grid; gap: 2px; padding: 12px; border: 1.5px solid #E2E8F0; border-radius: 14px; cursor: pointer; }
    .ogz-roles label.on { border-color: #2563EB; background: #F5F9FF; box-shadow: 0 0 0 4px rgba(37, 99, 235, .1); }
    .ogz-roles input { position: absolute; opacity: 0; pointer-events: none; }
    .ogz-roles b { font-size: 13px; color: #0F172A; } .ogz-roles small { font-size: 11.5px; color: #64748B; }
    .ogz-perm { width: 100%; border-collapse: collapse; min-width: 560px; }
    .ogz-perm th, .ogz-perm td { padding: 11px 12px; border-bottom: 1px solid #EEF2F7; text-align: center; font-size: 13px; }
    .ogz-perm th { background: #F7FAFD; font-size: 12px; color: #475569; }
    .ogz-perm td:first-child, .ogz-perm th:first-child { text-align: left; font-weight: 700; color: #334155; }
    .ogz-yes { color: #16A34A; } .ogz-yes svg { width: 20px; height: 20px; } .ogz-no { color: #CBD5E1; } .ogz-no svg { width: 18px; height: 18px; }
    @media (max-width: 560px) { .ogz-roles { grid-template-columns: 1fr; } }
</style>
<script>
    window.cspAddTeacher = function (cfg) {
        return {
            open: false, q: '', results: [], picked: null, role: 'co_teacher', loading: false, loaded: false, busy: false, timer: null, seq: 0,
            openModal() { this.open = true; this.busy = false; document.documentElement.style.overflow = 'hidden'; if (!this.loaded) this.search(true); this.$nextTick(() => this.$refs.q && this.$refs.q.focus()); },
            close() { this.open = false; document.documentElement.style.overflow = ''; },
            search(now) {
                clearTimeout(this.timer);
                const run = () => {
                    const my = ++this.seq; this.loading = true;
                    fetch(cfg.url + '?q=' + encodeURIComponent(this.q.trim()), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                        .then((r) => r.ok ? r.json() : { items: [] })
                        .then((j) => { if (my === this.seq) { this.results = j.items || []; this.loading = false; this.loaded = true; } })
                        .catch(() => { if (my === this.seq) this.loading = false; });
                };
                now ? run() : (this.timer = setTimeout(run, 250));
            },
        };
    };
</script>
