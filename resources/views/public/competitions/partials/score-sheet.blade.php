{{--
    SỬA 30/10 (khách: "khảo sát kết thúc rồi thì admin, giáo viên có nút In — bấm ra modal chọn bản ghi nào để in, IN ĐÚC mẫu Bảng
    tổng hợp điểm và xếp giải (THCS Vũ Ninh); bạn gian lận không được tính thì ghi chú; 1 Nhất 1 Nhì 1 Ba 2 KK; có cả kết quả kiểm tra
    thao tác làm bài; nộp 2 lần thì chọn lần nào").

    Hộp "In bảng điểm & xếp giải" — chỉ ban tổ chức, kỳ ĐÃ KẾT THÚC. Mở bằng sự kiện 'oi-score-sheet' (nút ở tab Bảng xếp hạng / Tổng
    quan). Dữ liệu (JSON, đã kiểm quyền): App\Services\Contest\ContestScoreSheetService. Mọi lựa chọn (lượt tính, không xét giải, cơ cấu
    giải, ghi chú) chỉ để in — không ghi vào cơ sở dữ liệu; vài thông tin chung (tên trường, cơ cấu giải…) nhớ trên máy người in.
--}}
@php
    $ssCfg = [
        'url' => route('competitions.manage.scoreSheet', ['competition' => $contest['id'], 'exam' => $round['id']]),
        'competitionId' => $contest['id'],
        'survey' => (bool) $survey,
    ];
@endphp
<style>
    .ssm-back { position: fixed; inset: 0; z-index: 80; display: flex; align-items: flex-start; justify-content: center; padding: 18px 12px; background: rgba(15, 35, 55, .45); overflow-y: auto; }
    .ssm { width: min(1180px, 100%); background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(15, 35, 55, .3); color: #123b68; font-size: 12.5px; }
    .ssm-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #e3ecf2; }
    .ssm-head h2 { margin: 0; font-size: 16px; font-weight: 800; }
    .ssm-head p { margin: 2px 0 0; font-size: 12px; color: #61798b; }
    .ssm-x { border: 1px solid #d6e3ec; background: #fff; border-radius: 9px; width: 34px; height: 34px; font-size: 18px; color: #456a83; cursor: pointer; }
    .ssm-body { padding: 14px 18px; display: grid; gap: 14px; }
    .ssm-sec h3 { margin: 0 0 8px; font-size: 13px; font-weight: 800; }
    .ssm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 8px 12px; }
    .ssm label.f { display: grid; gap: 4px; font-size: 11.5px; color: #61798b; font-weight: 650; }
    .ssm input[type=text], .ssm input[type=date], .ssm input[type=number], .ssm select, .ssm textarea { width: 100%; box-sizing: border-box; border: 1px solid #d6e3ec; border-radius: 9px; padding: 7px 9px; font-size: 12.5px; color: #123b68; background: #fff; font-family: inherit; }
    .ssm input[type=number] { width: 70px; }
    .ssm-quota { display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: center; }
    .ssm-quota label { display: inline-flex; align-items: center; gap: 6px; font-weight: 650; }
    .ssm-check { display: inline-flex; align-items: center; gap: 6px; font-weight: 650; cursor: pointer; }
    .ssm-tbl-wrap { overflow-x: auto; border: 1px solid #e3ecf2; border-radius: 12px; max-height: 420px; overflow-y: auto; }
    .ssm-tbl { width: 100%; min-width: 860px; border-collapse: collapse; }
    .ssm-tbl th { position: sticky; top: 0; z-index: 1; background: #f5f9fb; text-align: left; font-size: 11px; color: #61798b; padding: 8px; border-bottom: 1px solid #e3ecf2; white-space: nowrap; }
    .ssm-tbl td { padding: 7px 8px; border-bottom: 1px solid #edf2f6; vertical-align: top; }
    .ssm-tbl tr.is-off td { opacity: .45; }
    .ssm-tbl tr.is-cheat td { background: #fff7ed; }
    .ssm-sub { font-size: 11px; color: #7e93a3; }
    .ssm-sig { display: inline-block; margin: 0 4px 2px 0; padding: 1px 7px; border-radius: 999px; background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; font-size: 10.5px; font-weight: 700; }
    .ssm-ok { color: #15803d; font-size: 11px; font-weight: 700; }
    .ssm-preview { border: 1px solid #e3ecf2; border-radius: 12px; background: #eef2f5; padding: 10px; }
    .ssm-preview iframe { width: 100%; height: 520px; border: 0; background: #fff; border-radius: 6px; }
    .ssm-foot { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; padding: 12px 18px; border-top: 1px solid #e3ecf2; position: sticky; bottom: 0; background: #fff; border-radius: 0 0 16px 16px; }
    .ssm-btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #d6e3ec; background: #fff; color: #126f91; border-radius: 10px; padding: 9px 14px; font-size: 12.5px; font-weight: 700; cursor: pointer; }
    .ssm-btn.primary { background: #126f91; border-color: #126f91; color: #fff; }
    .ssm-btn:disabled { opacity: .5; cursor: default; }
    .ssm-btn svg { width: 15px; height: 15px; }
    .ssm-empty { padding: 24px; text-align: center; color: #7e93a3; }
</style>

<div x-data="contestScoreSheet({{ Js::from($ssCfg) }})" @oi-score-sheet.window="open()" @keydown.escape.window="if (show) show = false">
    <div class="ssm-back" x-show="show" x-cloak @click.self="show = false">
        <div class="ssm" role="dialog" aria-modal="true" aria-labelledby="ssm-title">
            <div class="ssm-head">
                <div>
                    <h2 id="ssm-title">In bảng tổng hợp điểm và xếp giải</h2>
                    <p>{{ $round['label'] }} — chọn học sinh, lượt nộp được tính, bạn không xét giải, cơ cấu giải rồi bấm In.</p>
                </div>
                <button type="button" class="ssm-x" @click="show = false" aria-label="Đóng">×</button>
            </div>

            <div class="ssm-body">
                <template x-if="loading"><div class="ssm-empty">Đang tải danh sách bài nộp…</div></template>
                <template x-if="error"><div class="ssm-empty" x-text="error"></div></template>

                <template x-if="data && !loading">
                    <div style="display:grid;gap:14px">
                        {{-- 1. Thông tin đầu bảng --}}
                        <section class="ssm-sec">
                            <h3>1. Thông tin trên bảng in</h3>
                            <div class="ssm-grid">
                                <label class="f">Tiêu đề<input type="text" x-model="s.heading"></label>
                                <label class="f">Dòng thứ hai<input type="text" x-model="s.subheading"></label>
                                <label class="f">Đơn vị / Trường<input type="text" x-model="s.unit" placeholder="VD: Trường THCS Vũ Ninh"></label>
                                <label class="f">Ngày in<input type="date" x-model="s.date"></label>
                                <label class="f" style="grid-column:1/-1">Chân trang<input type="text" x-model="s.footer"></label>
                            </div>
                        </section>

                        {{-- 2. Cơ cấu giải --}}
                        <section class="ssm-sec">
                            <h3>2. Cơ cấu giải</h3>
                            <div class="ssm-quota">
                                <label>Nhất <input type="number" min="0" max="99" x-model.number="s.q1"></label>
                                <label>Nhì <input type="number" min="0" max="99" x-model.number="s.q2"></label>
                                <label>Ba <input type="number" min="0" max="99" x-model.number="s.q3"></label>
                                <label>Khuyến khích <input type="number" min="0" max="99" x-model.number="s.q4"></label>
                                <label class="ssm-check"><input type="checkbox" x-model="s.needScore"> Chỉ xét giải khi tổng điểm lớn hơn 0</label>
                                <label class="ssm-check"><input type="checkbox" x-model="s.showActivity"> In cột "Kiểm tra thao tác"</label>
                                <label class="ssm-check"><input type="checkbox" x-model="s.upper"> Viết hoa họ tên</label>
                            </div>
                            <p class="ssm-sub" style="margin:6px 0 0">Bằng điểm thì cùng hạng, cùng giải (vd hai bạn cùng hạng 4 đều được Khuyến khích nếu còn suất).</p>
                        </section>

                        {{-- 3. Chọn học sinh / lượt nộp --}}
                        <section class="ssm-sec">
                            <h3>3. Học sinh và lượt nộp được tính <span class="ssm-sub" x-text="'(' + includedCount + '/' + data.students.length + ' học sinh được in)'"></span></h3>
                            <template x-if="!data.students.length"><div class="ssm-empty">Kỳ này chưa có bài nộp nào.</div></template>
                            <div class="ssm-tbl-wrap" x-show="data.students.length">
                                <table class="ssm-tbl">
                                    <thead>
                                        <tr>
                                            <th><label class="ssm-check"><input type="checkbox" :checked="includedCount === data.students.length" @change="toggleAll($event.target.checked)"> In</label></th>
                                            <th>Học sinh</th>
                                            <th>Lượt nộp được tính</th>
                                            <th>Kiểm tra thao tác (lượt đã chọn)</th>
                                            <th>Không xét giải (gian lận…)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="st in data.students" :key="st.userId">
                                            <tr :class="{ 'is-off': !pick[st.userId].include, 'is-cheat': pick[st.userId].cheat }">
                                                <td><input type="checkbox" x-model="pick[st.userId].include" :aria-label="'In ' + st.name"></td>
                                                <td>
                                                    <div style="font-weight:700" x-text="st.name"></div>
                                                    <div class="ssm-sub" x-text="[st.className, st.account].filter(Boolean).join(' · ')"></div>
                                                </td>
                                                <td style="min-width:260px">
                                                    <select x-model.number="pick[st.userId].attemptId" :disabled="!pick[st.userId].include">
                                                        <template x-for="a in st.attempts" :key="a.id">
                                                            <option :value="a.id" :selected="a.id === pick[st.userId].attemptId" x-text="attemptLabel(st, a)"></option>
                                                        </template>
                                                    </select>
                                                    <div class="ssm-sub" x-show="st.attempts.length > 1" x-text="st.attempts.length + ' lượt nộp — mặc định lượt điểm cao nhất'"></div>
                                                </td>
                                                <td>
                                                    <template x-for="t in sigList(chosen(st))" :key="t"><span class="ssm-sig" x-text="t"></span></template>
                                                    <span class="ssm-ok" x-show="chosen(st).hasLog && !sigList(chosen(st)).length">Bình thường</span>
                                                    <span class="ssm-sub" x-show="!chosen(st).hasLog">Không có nhật ký</span>
                                                </td>
                                                <td style="min-width:220px">
                                                    <label class="ssm-check"><input type="checkbox" x-model="pick[st.userId].cheat" :disabled="!pick[st.userId].include"> Không xét giải</label>
                                                    <input type="text" x-show="pick[st.userId].cheat" x-model="pick[st.userId].note" placeholder="Lý do, vd: mở tab khác nhiều lần" style="margin-top:4px">
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        {{-- 4. Ghi chú --}}
                        <section class="ssm-sec">
                            <h3>4. Ghi chú in dưới bảng</h3>
                            <p class="ssm-sub" style="margin:0 0 6px">Các dòng tự tạo (cách xếp hạng, cơ cấu giải, bạn không xét giải, lượt được tính, kiểm tra thao tác) luôn có. Thêm ghi chú riêng — mỗi dòng một ý:</p>
                            <textarea rows="3" x-model="s.extra" placeholder="VD: Các bài lập trình được chấm bằng bộ test của đề; giới hạn 1 giây/test."></textarea>
                        </section>

                        {{-- 5. Xem trước --}}
                        <section class="ssm-sec">
                            <h3>5. Xem trước (khổ A4 ngang)</h3>
                            <div class="ssm-preview"><iframe title="Xem trước bảng điểm" x-effect="if (show && data) $el.srcdoc = html(false)"></iframe></div>
                        </section>
                    </div>
                </template>
            </div>

            <div class="ssm-foot">
                <button type="button" class="ssm-btn" @click="show = false">Đóng</button>
                <button type="button" class="ssm-btn primary" @click="print()" :disabled="!data || !includedCount"><x-lucide name="printer" />In bảng điểm</button>
            </div>
        </div>
    </div>
</div>

@once
<script>
    // SỬA 30/10 — hộp "In bảng tổng hợp điểm và xếp giải" (in đúc mẫu khách chốt). Tính hạng / giải / ghi chú ngay ở trình duyệt.
    function contestScoreSheet(cfg) {
        const KEY = 'oi.scoresheet.settings';
        const today = () => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
        return {
            show: false, loading: false, error: '', data: null, pick: {},
            s: { heading: 'BẢNG TỔNG HỢP ĐIỂM VÀ XẾP GIẢI', subheading: '', unit: '', date: today(), footer: '',
                 q1: 1, q2: 1, q3: 1, q4: 2, needScore: true, showActivity: true, upper: false, extra: '' },
            open() {
                this.show = true;
                if (this.data || this.loading) return;
                this.loading = true; this.error = '';
                fetch(cfg.url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((r) => r.ok ? r.json() : Promise.reject(r.status))
                    .then((j) => {
                        const pick = {};
                        (j.students || []).forEach((st) => { pick[st.userId] = { include: true, attemptId: st.defaultAttemptId, cheat: false, note: '' }; });
                        this.pick = pick;
                        this.s.subheading = cfg.survey ? 'KHẢO SÁT MÔN TIN HỌC' : ((j.defaults && j.defaults.subheading) || '');
                        this.s.footer = cfg.survey ? 'Bảng tổng hợp kết quả khảo sát môn Tin học' : 'Bảng tổng hợp kết quả kỳ thi môn Tin học';
                        // Thông tin chung lần in trước (tên trường, cơ cấu giải…) — nhớ trên máy này.
                        try {
                            const saved = JSON.parse(window.localStorage.getItem(KEY) || 'null');
                            if (saved && typeof saved === 'object') {
                                ['unit', 'q1', 'q2', 'q3', 'q4', 'needScore', 'showActivity', 'upper'].forEach((k) => { if (saved[k] !== undefined) this.s[k] = saved[k]; });
                            }
                        } catch (e) {}
                        this.data = j;
                    })
                    .catch((st) => { this.error = st === 403 ? 'Chỉ ban tổ chức của kỳ mới in được bảng điểm.' : 'Không tải được danh sách bài nộp — đóng hộp rồi thử lại.'; })
                    .finally(() => { this.loading = false; });
            },
            toggleAll(on) { Object.keys(this.pick).forEach((k) => { this.pick[k].include = on; }); },
            get includedCount() { return Object.values(this.pick).filter((p) => p.include).length; },
            chosen(st) { const id = this.pick[st.userId] ? this.pick[st.userId].attemptId : null; return st.attempts.find((a) => a.id === id) || st.attempts[0]; },
            num(n) { return n === null || n === undefined || isNaN(n) ? '' : (Math.round(Number(n) * 100) / 100).toFixed(2).replace('.', ','); },
            attemptLabel(st, a) { return 'Lần ' + a.no + ' · ' + a.submittedAt + ' · ' + (a.pending ? 'chờ chấm' : this.num(a.score) + ' điểm') + (this.sigList(a).length ? ' · có dấu hiệu' : ''); },
            sigList(a) {
                const c = (a && a.activity) || {}, out = [];
                if (c.tab) out.push('Rời tab ' + c.tab);
                if (c.screenshot) out.push('Chụp màn hình ' + c.screenshot);
                if (c.guideOpen) out.push('Xem Hướng dẫn ' + c.guideOpen);
                if (c.sampleOpen) out.push('Xem Bài mẫu ' + c.sampleOpen);
                if (c.other) out.push('Khác ' + c.other);
                return out;
            },
            // Dòng in: xếp hạng (bằng điểm cùng hạng), chia giải theo cơ cấu; không xét giải / chờ chấm xếp cuối, hạng "-".
            rows() {
                if (!this.data) return [];
                const list = this.data.students.filter((st) => this.pick[st.userId] && this.pick[st.userId].include).map((st) => {
                    const p = this.pick[st.userId], a = this.chosen(st);
                    return { st, a, cheat: !!p.cheat, note: (p.note || '').trim(), score: a.pending ? null : Number(a.score) };
                });
                const byName = (x, y) => x.st.name.localeCompare(y.st.name, 'vi');
                const ranked = list.filter((r) => !r.cheat && r.score !== null).sort((x, y) => (y.score - x.score) || byName(x, y));
                const q = [this.s.q1, this.s.q2, this.s.q3, this.s.q4].map((v) => Math.max(0, parseInt(v, 10) || 0));
                const cut = [q[0], q[0] + q[1], q[0] + q[1] + q[2], q[0] + q[1] + q[2] + q[3]];
                const names = ['Nhất', 'Nhì', 'Ba', 'Khuyến khích'];
                ranked.forEach((r, i) => {
                    r.rank = (i > 0 && Math.abs(ranked[i - 1].score - r.score) < 1e-9) ? ranked[i - 1].rank : i + 1;
                    const k = cut.findIndex((c) => r.rank <= c);
                    r.award = (k === -1 || (this.s.needScore && !(r.score > 0))) ? '-' : names[k];
                });
                const pending = list.filter((r) => !r.cheat && r.score === null).sort(byName).map((r) => Object.assign(r, { rank: '-', award: 'Chờ chấm' }));
                const cheats = list.filter((r) => r.cheat).sort(byName).map((r) => Object.assign(r, { rank: '-', award: 'Không xét' }));
                return ranked.concat(pending, cheats);
            },
            notes(rows) {
                const q = [this.s.q1, this.s.q2, this.s.q3, this.s.q4].map((v) => Math.max(0, parseInt(v, 10) || 0));
                const parts = [[q[0], 'Nhất'], [q[1], 'Nhì'], [q[2], 'Ba'], [q[3], 'Khuyến khích']].filter((x) => x[0] > 0).map((x) => x[0] + ' ' + x[1]);
                const out = ['Danh sách xếp theo tổng điểm giảm dần; học sinh bằng điểm cùng hạng, cùng giải.' + (parts.length ? ' Cơ cấu: ' + parts.join(', ') + '.' : '')];
                const multi = rows.filter((r) => r.st.attempts.length > 1);
                if (multi.length) out.push('Học sinh nộp nhiều lần — lượt được tính: ' + multi.map((r) => this.nm(r.st.name) + ' (lần ' + r.a.no + '/' + r.st.attempts.length + ', nộp ' + r.a.submittedAt + ')').join('; ') + '.');
                const cheats = rows.filter((r) => r.cheat);
                if (cheats.length) out.push('Không xét giải do vi phạm quy chế: ' + cheats.map((r) => this.nm(r.st.name) + (r.note ? ' (' + r.note + ')' : '')).join('; ') + '.');
                const pend = rows.filter((r) => r.award === 'Chờ chấm');
                if (pend.length) out.push('Bài chưa chấm xong, chưa xếp hạng: ' + pend.map((r) => this.nm(r.st.name)).join('; ') + '.');
                const flagged = rows.filter((r) => this.sigList(r.a).length);
                out.push('Kiểm tra thao tác làm bài (hệ thống ghi nhận rời tab / cửa sổ, chụp màn hình, mở Hướng dẫn / Bài mẫu trong lúc làm bài): '
                    + (flagged.length ? flagged.map((r) => this.nm(r.st.name) + ' — ' + this.sigList(r.a).join(', ')).join('; ') + '.' : 'không phát hiện bất thường.'));
                String(this.s.extra || '').split('\n').map((t) => t.trim()).filter(Boolean).forEach((t) => out.push(t));
                return out;
            },
            nm(name) { return this.s.upper ? String(name).toLocaleUpperCase('vi') : name; },
            dateLine() {
                const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(this.s.date || '');
                if (!m) return '';
                return 'Ngày ' + parseInt(m[3], 10) + ' tháng ' + parseInt(m[2], 10) + ' năm ' + m[1];
            },
            // HTML trang in — in đúc mẫu: tiêu đề 3 dòng + gạch ngắn, dòng "Số học sinh/bài nộp | Thang điểm" và ngày, bảng Hạng · Họ tên ·
            // Bài 1…n (mã, thang điểm) · Tổng điểm (tô xám) · Giải, ghi chú, chân trang "… / Trang x / y". Times New Roman, A4 ngang.
            html(autoPrint) {
                const e = (v) => String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
                const css = (v) => '"' + String(v || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/[\r\n]+/g, ' ') + '"';
                const rows = this.rows();
                const qs = this.data.questions || [];
                const max = Number(this.data.maxScore || 0);
                const act = !!this.s.showActivity;
                const head = '<tr><th style="width:5%">Hạng</th><th style="width:' + (act ? 19 : 22) + '%">Họ tên / Tên bài làm</th>'
                    + qs.map((q, i) => '<th>Bài ' + (i + 1) + (q.code ? '<br>' + e(q.code) : '') + '<br>(' + this.num(q.points) + ' điểm)</th>').join('')
                    + '<th class="tot" style="width:9%">Tổng điểm<br>(' + this.num(max) + ')</th><th style="width:' + (act ? 11 : 13) + '%">Giải</th>'
                    + (act ? '<th style="width:16%">Kiểm tra thao tác</th>' : '') + '</tr>';
                const body = rows.map((r) => {
                    const cells = qs.map((q) => {
                        if (r.score === null) return '<td></td>';
                        if (q.papers && q.papers.length && q.papers.indexOf(r.a.assessmentId) === -1) return '<td>—</td>';
                        const v = r.a.perQuestion ? r.a.perQuestion[q.id] : undefined;
                        return '<td>' + this.num(v === undefined || v === null ? 0 : v) + '</td>';
                    }).join('');
                    const sig = this.sigList(r.a);
                    return '<tr><td>' + e(r.rank) + '</td><td class="nm">' + e(this.nm(r.st.name)) + '</td>' + cells
                        + '<td class="tot">' + (r.score === null ? '' : this.num(r.score)) + '</td><td class="aw">' + e(r.award) + '</td>'
                        + (act ? '<td class="ac">' + e(!r.a.hasLog ? 'Không có dữ liệu' : (sig.length ? sig.join('; ') : 'Bình thường')) + '</td>' : '') + '</tr>';
                }).join('');
                const notes = this.notes(rows).map((t, i) => '<p>' + (i === 0 ? '<b>Ghi chú:</b> ' : '') + e(t) + '</p>').join('');
                return '<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>' + e(this.s.heading) + '</title><style>'
                    + '@page{size:A4 landscape;margin:13mm 12mm 16mm 12mm;'
                    + '@bottom-left{content:' + css(this.s.footer) + ';font:italic 9pt "Times New Roman",Times,serif;color:#333;border-top:.6pt solid #999;vertical-align:top;padding-top:2mm}'
                    + '@bottom-center{content:"";border-top:.6pt solid #999}'
                    + '@bottom-right{content:"Trang " counter(page) " / " counter(pages);font:italic 9pt "Times New Roman",Times,serif;color:#333;border-top:.6pt solid #999;vertical-align:top;padding-top:2mm}}'
                    + 'html,body{margin:0;background:#fff;color:#000;font-family:"Times New Roman",Times,serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}'
                    + 'body{padding:0}@media screen{body{padding:14px 18px;font-size:12px}}'
                    + '.h1{text-align:center;font-size:19pt;font-weight:bold;margin:0;letter-spacing:.2pt}'
                    + '.h2{text-align:center;font-size:13pt;font-weight:bold;margin:5pt 0 0}'
                    + '.h3{text-align:center;font-size:14pt;font-weight:bold;margin:5pt 0 0}'
                    + '.rule{width:31mm;border-top:1pt solid #000;margin:4pt auto 14pt}'
                    + '.meta{display:flex;justify-content:space-between;align-items:flex-end;font-size:11.5pt;margin:0 0 6pt}.meta i{font-style:italic}'
                    + 'table{width:100%;border-collapse:collapse;border:1pt solid #000;font-size:12pt}'
                    + 'th,td{border:.6pt solid #666;padding:6pt 6pt;text-align:center;vertical-align:middle}'
                    + 'th{background:#efefef;font-weight:bold;font-size:10.5pt;line-height:1.25}thead tr{border-bottom:1.2pt solid #000}'
                    + 'td.nm{text-align:left;padding-left:10pt}td.tot{background:#f3f3f3;font-weight:bold}th.tot{background:#efefef}td.aw{font-weight:bold;white-space:nowrap}td.ac{font-size:9.5pt;text-align:left}'
                    + 'tr{page-break-inside:avoid}.notes{margin-top:10pt;font-size:10.5pt;line-height:1.55}.notes p{margin:0 0 3pt}'
                    + '</style></head><body>'
                    + '<p class="h1">' + e(this.s.heading) + '</p>'
                    + (this.s.subheading ? '<p class="h2">' + e(this.s.subheading) + '</p>' : '')
                    + (this.s.unit ? '<p class="h3">' + e(this.s.unit) + '</p>' : '')
                    + '<div class="rule"></div>'
                    + '<div class="meta"><span>Số học sinh/bài nộp: ' + rows.length + ' &nbsp;&nbsp;|&nbsp;&nbsp; Thang điểm: ' + e(this.num(max).replace(/,00$/, '')) + '</span><i>' + e(this.dateLine()) + '</i></div>'
                    + '<table><thead>' + head + '</thead><tbody>' + body + '</tbody></table>'
                    + '<div class="notes">' + notes + '</div>'
                    + (autoPrint ? '<script>window.onload=function(){setTimeout(function(){window.print()},150)};<\/script>' : '')
                    + '</body></html>';
            },
            print() {
                try { window.localStorage.setItem(KEY, JSON.stringify({ unit: this.s.unit, q1: this.s.q1, q2: this.s.q2, q3: this.s.q3, q4: this.s.q4, needScore: this.s.needScore, showActivity: this.s.showActivity, upper: this.s.upper })); } catch (e) {}
                const w = window.open('', '_blank');
                if (!w) { alert('Trình duyệt đã chặn cửa sổ in — cho phép cửa sổ bật lên (pop-up) cho trang này rồi bấm In lại.'); return; }
                w.document.open(); w.document.write(this.html(true)); w.document.close();
            },
        };
    }
</script>
@endonce
