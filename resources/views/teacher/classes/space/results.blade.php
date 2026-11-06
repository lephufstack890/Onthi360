{{-- SỬA 6/11 (khách: "Kết quả học tập: chấm điểm, in kết quả, nhận xét") — Tab 7. Bảng điểm thang 10: điểm tự động lấy từ bài làm
     (đổi về thang 10 theo tổng điểm đề), bấm ô để chấm / sửa điểm + nhận xét (lưu ngay, teacher.classes.grades.save); cột Điểm TB, Đi học,
     Xếp loại, Nhận xét chung; in bảng điểm, in phiếu từng em / cả lớp, xuất Excel. Dữ liệu: ClassSpaceService::results(). --}}
@php
    $rs = $spaceData ?? [];
    $rsCols = $rs['cols'] ?? [];
    $rsRows = $rs['rows'] ?? [];
    $rsCan = (bool) ($space['can']['grade'] ?? false) && ($rs['ready'] ?? false);
    $rsCells = [];
    foreach ($rsRows as $r) {
        foreach ($r['cells'] as $aid => $c) {
            $rsCells[$aid.':'.$r['id']] = $c;
        }
        $rsCells['0:'.$r['id']] = ['score' => null, 'comment' => $r['note']];
    }
@endphp
<div class="csp-card" x-data="cspGrades(@js(['cells' => (object) $rsCells, 'cols' => array_column($rsCols, 'id'), 'rows' => array_column($rsRows, 'id'), 'url' => route('teacher.classes.grades.save', $classRoom->id), 'csrf' => csrf_token(), 'can' => $rsCan]))" @keydown.escape.window="ed = null">
    <div class="att-print-title"><h2>Bảng kết quả học tập — {{ $className }}</h2><p>In ngày {{ now()->format('d/m/Y') }}</p></div>
    <div class="csp-ch csp-noprint">
        <span class="csp-ic o"><x-lucide name="bar-chart-3" /></span>
        <div class="grow"><h3>Bảng kết quả học tập</h3><p>Điểm thang 10, tự lấy từ bài làm · bấm vào ô để chấm / sửa điểm và nhận xét · ô cam là bài chờ chấm.</p></div>
        <a href="{{ route('teacher.classes.results.export', $classRoom->id) }}" class="csp-btn"><x-lucide name="download" />Xuất Excel</a>
        <button type="button" class="csp-btn" onclick="window.print()"><x-lucide name="printer" />In bảng điểm</button>
        <a href="{{ route('teacher.classes.results.print', $classRoom->id) }}" target="_blank" class="csp-btn pri"><x-lucide name="printer" />In phiếu cả lớp</a>
    </div>
    @if (! ($rs['ready'] ?? false))
        <div class="csp-note csp-noprint" style="margin-bottom:12px"><x-lucide name="alert-triangle" /><span>Chưa cập nhật cơ sở dữ liệu — chạy <code>php artisan migrate --force</code> để chấm điểm / nhận xét. Điểm tự động vẫn hiện bình thường.</span></div>
    @endif

    @if (empty($rsRows))
        <div class="csp-empty"><x-lucide name="users" /><b>Lớp chưa có học sinh</b></div>
    @else
        <div class="att-wrap">
            <table class="rs-t">
                <thead>
                    <tr>
                        <th class="rs-name">Học sinh</th>
                        @foreach ($rsCols as $c)<th class="rs-c" title="{{ $c['title'] }}">{{ \Illuminate\Support\Str::limit($c['title'], 22) }}</th>@endforeach
                        <th class="rs-c">Điểm TB</th><th class="rs-c">Đi học</th><th class="rs-c">Xếp loại</th>
                        <th class="rs-note">Nhận xét của giáo viên</th><th class="csp-noprint"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rsRows as $r)
                        <tr>
                            <td class="rs-name"><div class="csp-who"><span class="csp-av h{{ $r['hue'] }}">@if ($r['avatar'])<img src="{{ $r['avatar'] }}" alt="">@else{{ $r['initials'] }}@endif</span><b>{{ $r['name'] }}</b></div></td>
                            @foreach ($rsCols as $c)
                                @php $k = $c['id'].':'.$r['id']; @endphp
                                <td class="rs-c">
                                    <button type="button" class="rs-sc" :class="tone('{{ $k }}')" @click="open('{{ $k }}', $event)" :disabled="!can" :title="hint('{{ $k }}')">
                                        <span x-text="show('{{ $k }}')"></span><i x-show="cells['{{ $k }}'] && cells['{{ $k }}'].override" class="rs-dot"></i><i x-show="cells['{{ $k }}'] && cells['{{ $k }}'].comment" class="rs-cm">✎</i>
                                    </button>
                                </td>
                            @endforeach
                            <td class="rs-c"><b class="rs-avg" x-text="avgOf({{ $r['id'] }})"></b></td>
                            <td class="rs-c">@if ($r['attRate'] !== null)<span class="csp-pill {{ $r['attRate'] >= 90 ? 'p-ok' : ($r['attRate'] >= 70 ? 'p-ex' : 'p-no') }}">{{ $r['attRate'] }}%</span>@else<span class="csp-pill p-gray">—</span>@endif</td>
                            <td class="rs-c"><span class="csp-pill" :class="rank({{ $r['id'] }})[1]" x-text="rank({{ $r['id'] }})[0]"></span></td>
                            <td class="rs-note"><button type="button" class="att-note" :class="{ empty: !cells['0:{{ $r['id'] }}'].comment }" @click="open('0:{{ $r['id'] }}', $event)" :disabled="!can" x-text="cells['0:{{ $r['id'] }}'].comment || '+ Nhận xét chung'"></button></td>
                            <td class="csp-noprint"><a class="csp-btn sm icon" href="{{ route('teacher.classes.results.print', ['class' => $classRoom->id, 'student' => $r['id']]) }}" target="_blank" title="In phiếu kết quả của {{ $r['name'] }}" aria-label="In phiếu"><x-lucide name="printer" /></a></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Trung bình lớp</td>
                        @foreach ($rsCols as $c)<td class="rs-c" x-text="colAvg({{ $c['id'] }})"></td>@endforeach
                        <td class="rs-c"><b x-text="classAvg()"></b></td>
                        <td class="rs-c">{{ $rs['classAtt'] !== null ? $rs['classAtt'].'%' : '—' }}</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @if (empty($rsCols))
            <div class="csp-note csp-noprint" style="margin-top:12px"><x-lucide name="info" /><span>Lớp chưa có bài giao nào đã phát hành — giao bài ở tab <a href="{{ route('teacher.classes.show', ['class' => $classRoom->id, 'tab' => 'assign']) }}" style="font-weight:800;color:#1D4ED8">Bài tập / Đề</a>. Vẫn ghi được nhận xét chung cho từng em.</span></div>
        @endif
        <div class="rs-legend csp-noprint"><span><i class="rs-sc s-a">8+</i>Giỏi</span><span><i class="rs-sc s-b">6.5+</i>Khá</span><span><i class="rs-sc s-c">5+</i>Trung bình</span><span><i class="rs-sc s-d">&lt;5</i>Yếu</span><span><i class="rs-dot" style="position:static"></i>Điểm giáo viên chấm / sửa</span><span>✎ có nhận xét</span></div>
    @endif

    {{-- Ô chấm điểm / nhận xét --}}
    <div class="att-ed rs-ed" x-show="ed" x-cloak :style="ed && ('top:' + ed.y + 'px;left:' + ed.x + 'px')" @click.outside="ed = null">
        <template x-if="ed && !ed.general">
            <div class="rs-ed-score">
                <label>Điểm (0 – 10)<input type="number" x-ref="sc" min="0" max="10" step="0.25" x-model="edScore" class="csp-inp" placeholder="Để trống = điểm tự động" @keydown.enter.prevent="save()"></label>
                <small x-text="ed && ed.auto !== null ? 'Điểm tự động: ' + ed.auto : (ed && ed.submitted ? 'Bài đã nộp, chưa có điểm tự động' : 'Em chưa nộp bài này')"></small>
            </div>
        </template>
        <textarea x-ref="cm" x-model="edComment" rows="3" maxlength="2000" class="csp-inp" :placeholder="ed && ed.general ? 'Nhận xét chung về em trong lớp…' : 'Nhận xét bài làm (không bắt buộc)…'" @keydown.ctrl.enter="save()" @keydown.meta.enter="save()"></textarea>
        <div class="att-ed-acts">
            <button type="button" class="csp-btn sm" x-show="ed && !ed.general && ed.override" @click="edScore = ''; save()">Về điểm tự động</button>
            <span style="flex:1"></span>
            <button type="button" class="csp-btn sm" @click="ed = null">Huỷ</button><button type="button" class="csp-btn sm pri" @click="save()">Lưu</button>
        </div>
    </div>
</div>
<style>
    .att-print-title { display: none; }
    .att-wrap { overflow-x: auto; border: 1px solid #E5EDF5; border-radius: 16px; }
    .rs-t { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
    .rs-t th, .rs-t td { padding: 9px 10px; border-bottom: 1px solid #EEF2F7; background: #fff; vertical-align: middle; }
    .rs-t thead th { background: #F7FAFD; font-size: 11.5px; font-weight: 800; color: #475569; white-space: nowrap; }
    .rs-t tfoot td { background: #F7FAFD; font-weight: 800; color: #334155; }
    .rs-name { position: sticky; left: 0; z-index: 2; min-width: 200px; box-shadow: 1px 0 0 #E5EDF5; }
    .rs-c { text-align: center; }
    .rs-note { min-width: 220px; max-width: 280px; }
    .rs-sc { position: relative; display: inline-grid; place-items: center; min-width: 44px; height: 30px; padding: 0 8px; border: 0; border-radius: 9px; font: inherit; font-size: 13px; font-weight: 900; font-style: normal; cursor: pointer; }
    .rs-sc:hover:not(:disabled) { box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); } .rs-sc:disabled { cursor: default; }
    .s-a { background: #DCFCE7; color: #15803D; } .s-b { background: #DBEAFE; color: #1D4ED8; } .s-c { background: #FEF3C7; color: #B45309; } .s-d { background: #FEE2E2; color: #DC2626; }
    .s-e { background: #F8FAFC; color: #A3B1C2; font-weight: 700; border: 1px dashed #CBD5E1; } .s-wait { background: #FFEDD5; color: #C2410C; font-size: 11.5px; }
    .rs-dot { position: absolute; top: -3px; right: -3px; width: 9px; height: 9px; border: 2px solid #fff; border-radius: 50%; background: #7C3AED; display: inline-block; }
    .rs-cm { position: absolute; bottom: -6px; right: -4px; font-size: 10px; font-style: normal; color: #64748B; }
    .rs-avg { font-size: 15px; color: #0F2F57; }
    .rs-legend { display: flex; flex-wrap: wrap; gap: 8px 16px; margin-top: 12px; font-size: 12px; color: #64748B; }
    .rs-legend span { display: inline-flex; align-items: center; gap: 6px; } .rs-legend .rs-sc { min-width: 34px; height: 22px; font-size: 11px; cursor: default; }
    .att-note { display: block; width: 100%; min-height: 28px; padding: 4px 6px; border: 0; border-radius: 8px; background: none; font: inherit; font-size: 12px; line-height: 1.4; color: #334155; text-align: left; white-space: normal; word-break: break-word; cursor: pointer; }
    .att-note:hover:not(:disabled) { background: #F5F9FF; } .att-note.empty { color: #A3B1C2; font-style: italic; }
    .att-ed { position: fixed; z-index: 80; width: 300px; padding: 10px; border: 1px solid #E2E8F0; border-radius: 14px; background: #fff; box-shadow: 0 18px 40px rgba(15, 35, 60, .18); }
    .att-ed textarea { width: 100%; box-sizing: border-box; margin-top: 8px; }
    .att-ed-acts { display: flex; align-items: center; gap: 6px; margin-top: 8px; }
    .rs-ed-score label { display: grid; gap: 4px; font-size: 12px; font-weight: 800; color: #1E293B; }
    .rs-ed-score input { width: 100%; }
    .rs-ed-score small { display: block; margin-top: 4px; font-size: 11.5px; color: #64748B; }
    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        .att-print-title { display: block; margin-bottom: 8px; } .att-print-title h2 { margin: 0; font-size: 16px; } .att-print-title p { margin: 2px 0 0; font-size: 11px; }
        .att-wrap { overflow: visible; border: 0; }
        .rs-t th, .rs-t td { padding: 4px 5px; border: 1px solid #CBD5E1; font-size: 10px; }
        .rs-name { position: static; box-shadow: none; }
        .rs-sc { min-width: 0; height: auto; padding: 0; background: none !important; border: 0 !important; color: #000 !important; font-size: 10px; }
        .rs-dot, .rs-cm { display: none !important; }
        .att-note.empty { visibility: hidden; }
    }
</style>
<script>
    window.cspGrades = function (cfg) {
        const fmt = (v) => (Math.round(v * 10) / 10).toFixed(1).replace(/\.0$/, '');
        return {
            cells: cfg.cells || {}, can: cfg.can, ed: null, edScore: '', edComment: '',
            score(k) { const c = this.cells[k]; return c && c.score !== null && c.score !== undefined ? Number(c.score) : null; },
            show(k) { const c = this.cells[k]; const s = this.score(k); if (s !== null) return fmt(s); return c && c.pending ? 'Chấm' : '—'; },
            tone(k) { const c = this.cells[k]; const s = this.score(k); if (s === null) return c && c.pending ? 's-wait' : 's-e'; return s >= 8 ? 's-a' : (s >= 6.5 ? 's-b' : (s >= 5 ? 's-c' : 's-d')); },
            hint(k) { const c = this.cells[k] || {}; return c.override ? 'Điểm giáo viên chấm (tự động: ' + (c.auto ?? '—') + ')' : (c.pending ? 'Bài đã nộp — chờ chấm' : (c.submitted ? 'Điểm tự động' : 'Chưa nộp')); },
            avg(uid) { const v = cfg.cols.map((a) => this.score(a + ':' + uid)).filter((x) => x !== null); return v.length ? v.reduce((a, b) => a + b, 0) / v.length : null; },
            avgOf(uid) { const a = this.avg(uid); return a === null ? '—' : a.toFixed(1); },
            rank(uid) { const a = this.avg(uid); if (a === null) return ['—', 'p-gray']; const r = Math.round(a * 10) / 10; return r >= 8 ? ['Giỏi', 'p-ok'] : (r >= 6.5 ? ['Khá', 'p-late'] : (r >= 5 ? ['Trung bình', 'p-ex'] : ['Yếu', 'p-no'])); },
            colAvg(aid) { const v = cfg.rows.map((u) => this.score(aid + ':' + u)).filter((x) => x !== null); return v.length ? (v.reduce((a, b) => a + b, 0) / v.length).toFixed(1) : '—'; },
            classAvg() { const v = cfg.rows.map((u) => this.avg(u)).filter((x) => x !== null); return v.length ? (v.reduce((a, b) => a + b, 0) / v.length).toFixed(1) : '—'; },
            open(k, ev) {
                if (!this.can) return;
                const c = this.cells[k] || {};
                const general = k.startsWith('0:');
                const r = ev.currentTarget.getBoundingClientRect();
                const h = general ? 170 : 240;
                let y = r.bottom + 6; if (y + h > window.innerHeight - 8) y = Math.max(8, r.top - h - 6);
                this.ed = { k, general, auto: c.auto ?? null, override: !!c.override, submitted: !!c.submitted, x: Math.max(8, Math.min(r.left, window.innerWidth - 308)), y };
                this.edScore = c.override && c.score !== null ? c.score : '';
                this.edComment = c.comment || '';
                this.$nextTick(() => { const el = general ? this.$refs.cm : this.$refs.sc; el && el.focus(); });
            },
            save() {
                if (!this.ed) return;
                const { k, general } = this.ed; this.ed = null;
                const [aid, uid] = k.split(':').map(Number);
                const prev = { ...(this.cells[k] || {}) };
                const raw = String(this.edScore).trim().replace(',', '.');
                const score = general || raw === '' ? null : Math.max(0, Math.min(10, Number(raw)));
                if (!general && raw !== '' && isNaN(score)) { window.toast && window.toast('error', 'Điểm phải là số từ 0 đến 10.'); return; }
                const next = { ...prev, comment: this.edComment.trim() };
                if (!general) { next.override = score !== null; next.score = score !== null ? score : prev.auto; }
                this.cells[k] = next;
                fetch(cfg.url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ student_id: uid, assignment_id: general ? null : aid, score: score, comment: this.edComment.trim() }),
                }).then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                  .catch(() => { this.cells[k] = prev; window.toast && window.toast('error', 'Chưa lưu được — kiểm tra mạng rồi thử lại.'); });
            },
        };
    };
</script>
