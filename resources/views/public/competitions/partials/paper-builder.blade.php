{{--
    SỬA 24/10 (khách: "giáo viên / admin thiết lập đề sinh ngẫu nhiên; 1 kỳ thi có thể gồm nhiều đề khác nhau") —
    tab "Thiết lập đề" (source mới: SurveyPaperBuilder.jsx). Dữ liệu + luật ở máy chủ: ContestPaperController /
    ContestPaperService. Trang này chỉ soạn ma trận, gọi API, hiển thị kết quả.
--}}
<div class="survey-builder" x-data="paperBuilder({{ Js::from(['stateUrl' => $round['paperStateUrl'], 'csrf' => csrf_token()]) }})" x-init="init()">
    <div class="hub-content-heading">
        <div><h3>Thiết lập đề khảo sát</h3><p>Một ma trận → một đề · Một kỳ có thể gồm nhiều đề, học sinh được giao ngẫu nhiên 1 đề đã chốt</p></div>
        <template x-if="paper">
            <span class="survey-paper-status" :class="paper.lock ? 'locked' : (paper.status === 'published' ? 'published' : 'draft')">
                <x-lucide name="file-check-2" />
                <span x-text="paper.lock ? 'Đã khóa nội dung' : (paper.status === 'published' ? ('Đã chốt · v' + paper.version) : 'Chưa chốt đề')"></span>
            </span>
        </template>
        <template x-if="!paper && state && state.existing">
            <span class="survey-paper-status"><x-lucide name="file-text" />Đang dùng đề có sẵn</span>
        </template>
    </div>

    <p class="survey-builder-meta" x-show="loading">Đang tải…</p>
    <p class="contest-error" role="alert" x-show="error" x-text="error" x-cloak></p>
    <p class="survey-builder-success" role="status" x-show="notice" x-text="notice" x-cloak></p>

    <template x-if="state">
        <div>
            {{-- Các đề của kỳ --}}
            <div class="pb-papers" role="tablist" aria-label="Các đề của kỳ">
                <template x-for="p in state.papers" :key="p.id">
                    <button type="button" class="pb-paper" :aria-selected="cur === p.id" @click="select(p.id)">
                        <b x-text="p.label"></b>
                        <small x-text="p.lock ? 'Đã khoá' : (p.status === 'published' ? ('Đã chốt v' + p.version) : 'Nháp')"></small>
                    </button>
                </template>
                <button type="button" class="pb-paper pb-paper-add" :disabled="busy" @click="addPaper()"><x-lucide name="plus" />Thêm đề</button>
            </div>
            <p class="survey-builder-meta" x-show="state.existing && !state.papers.some(p => p.status === 'published')">
                Kỳ đang dùng đề có sẵn: <b x-text="state.existing"></b>. Khi chốt ít nhất 1 đề sinh từ ma trận, học sinh sẽ được giao đề sinh từ ma trận thay cho đề có sẵn.
            </p>
            <div class="survey-preview-empty" x-show="!state.papers.length">
                <x-lucide name="shuffle" /><p>Chưa có đề nào. Bấm <b>Thêm đề</b> để thiết lập ma trận và sinh đề từ Kho câu hỏi ({{ '' }}<span x-text="state.meta.bankCount"></span> câu đã phát hành).</p>
            </div>

            <template x-if="paper && draft">
                <div>
                    <ol class="survey-builder-steps"><li><b>1</b>Chọn ma trận</li><li><b>2</b>Sinh &amp; duyệt câu hỏi</li><li><b>3</b>Chốt đề</li></ol>
                    <div class="survey-builder-rule"><x-lucide name="file-check-2" /><p>Ngẫu nhiên khi giáo viên sinh đề. <strong>Cùng câu hỏi, cùng thứ tự, cùng thang điểm</strong> cho mọi học sinh nhận đề này và mọi lượt làm lại. Đề khóa khi học sinh đầu tiên bắt đầu làm.</p></div>
                    <p class="survey-builder-meta" x-show="paper.status === 'published'">Đề đang áp dụng: <span x-text="paper.applied.length"></span> câu · 100 điểm · Chốt bởi <span x-text="paper.publishedBy || '—'"></span> · <span x-text="paper.publishedAt"></span></p>
                    <p class="survey-builder-warning" role="status" x-show="paper.lock" x-text="paper.lock"></p>

                    <div class="pb-basic">
                        <label>Tên đề<input type="text" maxlength="120" x-model="draft.label" :disabled="locked" @input="dirty = true"></label>
                        <label>Thời gian làm bài (phút)<input type="number" min="5" max="600" x-model.number="draft.duration" :disabled="locked" @input="dirty = true"></label>
                    </div>

                    <div class="survey-builder-section-heading"><h4>Ma trận câu hỏi</h4><span><span x-text="state.meta.bankCount"></span> câu đã phát hành trong Kho câu hỏi</span></div>
                    <div class="survey-no-repeat" :data-enabled="draft.excludeUsed ? 'true' : 'false'">
                        <label>
                            <input type="checkbox" x-model="draft.excludeUsed" :disabled="locked" @change="changed()">
                            <span><strong>Không lấy câu đã dùng ở các kỳ khảo sát trước</strong><small>Áp dụng cho toàn bộ các phần trong đề</small></span>
                            <b x-text="draft.excludeUsed ? 'Đang bật' : 'Đang tắt'"></b>
                        </label>
                        <p>Đối chiếu theo câu gốc với tất cả đề của kỳ khác đã từng chốt, kể cả bản đề đã được thay thế. Không tính bản nháp, lần sinh thử hoặc lượt làm lại trong cùng kỳ.</p>
                        <p x-text="draft.excludeUsed ? 'Đây là điều kiện bắt buộc. Thiếu câu sẽ dừng sinh đề; hệ thống không tự lấy lại câu cũ.' : 'Khi tắt, hệ thống chọn theo cách chọn câu của từng phần. Câu trong cùng một đề vẫn không được trùng.'"></p>
                    </div>

                    <fieldset class="survey-matrix-fields" :disabled="locked || busy">
                        <template x-for="(row, index) in draft.matrix" :key="row.id">
                            <section class="survey-matrix-section" :aria-label="'Điều kiện phần ' + (index + 1)">
                                <div class="survey-matrix-row">
                                    <b class="survey-matrix-number" x-text="String(index + 1).padStart(2, '0')"></b>
                                    <label class="survey-matrix-topic">Chuyên đề chính
                                        <select x-model="row.topic" @change="changed()">
                                            <option value="">Mọi chuyên đề</option>
                                            <template x-for="t in state.meta.topics" :key="t.id"><option :value="String(t.id)" x-text="t.name" :selected="String(t.id) === row.topic"></option></template>
                                        </select>
                                    </label>
                                    <label>Độ khó
                                        <select x-model="row.difficulty" @change="changed()">
                                            <template x-for="d in state.meta.difficulties" :key="d.key"><option :value="d.key" x-text="d.label" :selected="d.key === row.difficulty"></option></template>
                                        </select>
                                    </label>
                                    <label>Số câu<input type="number" min="1" max="100" step="1" x-model.number="row.count" @input="changed()"></label>
                                    <label>Điểm tối đa<input type="number" min="0.01" max="100" step="0.01" x-model.number="row.points" @input="changed()"></label>
                                    <button type="button" class="survey-icon-button" :aria-label="'Xóa phần ' + (index + 1)" @click="removeRow(index)"><x-lucide name="trash-2" /></button>
                                </div>
                                <details class="survey-row-criteria">
                                    <summary>Nguồn, tag &amp; cách chọn câu <span x-text="rowSummary(row)"></span></summary>
                                    <div class="survey-criteria-grid">
                                        <label>Ngân hàng
                                            <select x-model="row.bank" @change="changed()">
                                                <option value="">Tất cả ngân hàng</option>
                                                <template x-for="b in state.meta.banks" :key="b.id"><option :value="String(b.id)" x-text="b.name" :selected="String(b.id) === row.bank"></option></template>
                                            </select>
                                        </label>
                                        <label>Cách chọn câu
                                            <select x-model="row.usagePolicy" :disabled="draft.excludeUsed" @change="changed()">
                                                <template x-for="p in state.meta.policies" :key="p.key"><option :value="p.key" x-text="p.label" :selected="p.key === row.usagePolicy"></option></template>
                                            </select>
                                            <small x-show="draft.excludeUsed">Theo quy tắc không trùng của toàn đề</small>
                                        </label>
                                        <label>Khớp tag
                                            <select x-model="row.tagMatch" @change="changed()"><option value="any">Ít nhất một tag</option><option value="all">Tất cả tag đã chọn</option></select>
                                        </label>
                                    </div>
                                    <div class="survey-tags-heading"><b>Tag chuyên đề (lọc thêm)</b></div>
                                    <div class="survey-tag-options" role="group">
                                        <template x-for="t in state.meta.topics" :key="'t' + t.id">
                                            <label><input type="checkbox" :value="String(t.id)" x-model="row.tags" @change="changed()"><span x-text="t.name"></span></label>
                                        </template>
                                        <span class="survey-builder-meta" x-show="!state.meta.topics.length">Kho câu hỏi chưa gắn tag nào.</span>
                                    </div>
                                    <div class="survey-source-points">
                                        <label>Điểm gốc từ<input type="number" min="0" step="0.01" placeholder="Không giới hạn" x-model="row.minPoints" @input="changed()"></label>
                                        <label>Điểm gốc đến<input type="number" min="0" step="0.01" placeholder="Không giới hạn" x-model="row.maxPoints" @input="changed()"></label>
                                        <p>Khoảng điểm gốc lọc câu trong Kho câu hỏi. Điểm tối đa của phần được chia đều cho các câu đã chọn.</p>
                                    </div>
                                    <p class="survey-builder-meta">Ngân hàng, chuyên đề, độ khó, tag, khoảng điểm và lịch sử phải đồng thời phù hợp. Không chọn tag nghĩa là không lọc tag.</p>
                                </details>
                                <p class="survey-row-match" aria-live="polite" x-show="counts[row.id]">
                                    <strong x-text="(counts[row.id]?.available ?? 0) + ' câu khớp điều kiện'"></strong>
                                    · <span x-text="(counts[row.id]?.unused ?? 0) + ' chưa dùng'"></span>
                                    · <span x-text="(counts[row.id]?.used ?? 0) + ' đã dùng ở kỳ khác'"></span>
                                    <span x-show="draft.excludeUsed" x-text="' · Loại ' + (counts[row.id]?.historyExcluded ?? 0) + ' câu trùng kỳ trước'"></span>
                                    <em x-show="Number(row.count) > (counts[row.id]?.available ?? 0)" x-text="' · Thiếu ' + (Number(row.count) - (counts[row.id]?.available ?? 0)) + ' câu'"></em>
                                </p>
                            </section>
                        </template>
                        <div class="survey-matrix-total">
                            <button type="button" class="contest-btn secondary" :disabled="draft.matrix.length >= 20" @click="addRow()"><x-lucide name="plus" />Thêm phần</button>
                            <span><b x-text="totalCount + ' câu'"></b><i>·</i><b :class="Math.abs(totalPoints - 100) > 0.00001 ? 'survey-invalid-total' : ''" x-text="(Math.round(totalPoints * 100) / 100) + '/100 điểm'"></b></span>
                        </div>
                    </fieldset>

                    <ul class="survey-matrix-issues" aria-label="Kiểm tra ma trận" x-show="!locked && issues.length"><template x-for="it in issues" :key="it"><li x-text="it"></li></template></ul>
                    <p class="survey-builder-meta">"Chưa dùng" = chưa xuất hiện trong đề đã chốt nào của kỳ khác. "Đã dùng" = có ít nhất một kỳ khác sử dụng. Sinh thử không tính lượt dùng. Hệ thống không lặp câu và không tự nới điều kiện khi thiếu câu.</p>
                    <details class="survey-bank-note"><summary>Nguồn câu hỏi &amp; cách chia điểm</summary><p>Dùng câu hỏi đã phát hành trong Kho câu hỏi (không gồm bài tập riêng của sản phẩm). Độ khó lấy theo ô "Độ khó" của câu (chưa đặt thì suy theo điểm). Điểm mỗi phần chia đều cho các câu, phần lẻ làm tròn đến 0,01 điểm để tổng luôn bằng 100.</p></details>

                    <div class="survey-builder-actions" x-show="!locked">
                        <button type="button" class="contest-btn primary" :disabled="busy || issues.length > 0" @click="generate()"><x-lucide name="shuffle" /><span x-text="draft.questions.length ? 'Sinh lại đề dự kiến' : 'Sinh đề dự kiến'"></span></button>
                        <button type="button" class="contest-btn secondary" :disabled="busy || !dirty" @click="save(false)"><x-lucide name="save" />Lưu bản nháp</button>
                        <span x-text="dirty ? 'Có thay đổi chưa lưu' : 'Bản soạn đã lưu / chưa chỉnh sửa'"></span>
                        <button type="button" class="contest-btn secondary pb-delete" :disabled="busy" @click="removePaper()"><x-lucide name="trash-2" />Xoá đề này</button>
                    </div>

                    <div class="survey-builder-section-heading"><h4 x-text="locked ? 'Đề đã khóa' : 'Duyệt đề dự kiến'"></h4><span x-text="preview.length ? (preview.length + ' câu · 100 điểm') : 'Chưa sinh câu hỏi'"></span></div>
                    <div class="survey-preview-list" x-show="preview.length">
                        <template x-for="(q, index) in preview" :key="q.id + '-' + index">
                            <div class="survey-preview-row">
                                <span class="survey-matrix-number" x-text="String(index + 1).padStart(2, '0')"></span>
                                <div>
                                    <b x-text="q.title"></b>
                                    <p><span x-text="(q.topics && q.topics.length ? q.topics.join(', ') : 'Chưa gắn chuyên đề') + ' · ' + q.difficultyLabel + ' · ' + q.typeLabel + ' · '"></span><strong x-text="q.points + ' điểm'"></strong></p>
                                    <small x-text="q.uses + ' kỳ khác đã dùng · ' + (q.code || ('#' + q.id)) + ' · Ngân hàng: ' + q.bankName + (q.bankPoints !== null ? ' · Điểm gốc: ' + q.bankPoints : '')"></small>
                                    <details><summary>Xem nội dung</summary>
                                        <p class="survey-question-statement" x-text="q.statement || '(Câu chỉ có tệp / không có nội dung chữ)'"></p>
                                        <ol type="A" x-show="q.options && q.options.length"><template x-for="o in q.options"><li x-text="o"></li></template></ol>
                                    </details>
                                </div>
                                <button type="button" class="survey-icon-button" x-show="!locked" :disabled="busy" :aria-label="'Thay câu ' + (index + 1)" title="Thay bằng câu khác khớp toàn bộ điều kiện" @click="replace(q.id)"><x-lucide name="refresh-cw" /></button>
                            </div>
                        </template>
                    </div>
                    <div class="survey-preview-empty" x-show="!preview.length"><x-lucide name="shuffle" /><p>Hoàn thiện ma trận rồi sinh đề để kiểm tra câu hỏi.</p></div>

                    <div class="survey-publish-bar" x-show="!locked">
                        <div><b>Chỉ giao đề sau khi chốt</b><p x-text="paper.status === 'published' ? 'Bản nháp không thay đổi đề đang áp dụng cho tới khi chốt lại.' : 'Học sinh chỉ nhận các đề đã chốt (ngẫu nhiên 1 đề / học sinh).'"></p></div>
                        <button type="button" class="contest-btn primary" :disabled="busy || issues.length > 0 || !draft.questions.length" @click="$refs.publishDialog.showModal()"><x-lucide name="file-check-2" />Chốt đề cho kỳ</button>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <dialog x-ref="publishDialog" class="contest-entry-dialog contest-ui survey-publish-dialog" aria-labelledby="survey-publish-title" @click="if ($event.target === $refs.publishDialog) $refs.publishDialog.close()">
        <button type="button" class="contest-dialog-close" aria-label="Đóng xác nhận chốt đề" @click="$refs.publishDialog.close()"><x-lucide name="x" /></button>
        <span class="contest-icon teal"><x-lucide name="file-check-2" /></span>
        <h2 id="survey-publish-title" x-text="'Chốt ' + (draft?.label || 'đề') + ' cho kỳ?'"></h2>
        <p x-text="(draft?.questions.length || 0) + ' câu · 100 điểm · ' + (draft?.duration || 45) + ' phút. Mọi học sinh nhận đề này và mọi lượt làm lại dùng cùng câu hỏi, đáp án và điểm.'"></p>
        <p><strong>Chống trùng kỳ trước:</strong> <span x-text="draft?.excludeUsed ? 'Bật · Không lấy lại câu đã từng chốt ở kỳ khác.' : 'Tắt · Theo cách chọn câu của từng phần.'"></span></p>
        <p><span x-show="paper && paper.status === 'published'">Đề mới sẽ thay thế đề đang áp dụng. </span>Ngay khi có học sinh bắt đầu, đề sẽ được khóa. Muốn đổi nội dung sau đó, hãy thêm đề mới.</p>
        <div class="survey-builder-actions" style="justify-content:flex-end;margin-bottom:0">
            <button type="button" class="contest-btn secondary" @click="$refs.publishDialog.close()">Kiểm tra lại</button>
            <button type="button" class="contest-btn primary" @click="$refs.publishDialog.close(); save(true)">Chốt &amp; áp dụng</button>
        </div>
    </dialog>
</div>

@once
@push('scripts')
<script>
    // SỬA 24/10 — tab "Thiết lập đề": soạn ma trận ở trình duyệt, mọi kiểm tra / sinh đề / chốt ở máy chủ.
    function paperBuilder(cfg) {
        const clone = (v) => JSON.parse(JSON.stringify(v));
        const toForm = (m) => m.map((r) => ({ ...r, topic: r.topic === null ? '' : String(r.topic), bank: r.bank === null ? '' : String(r.bank),
            tags: (r.tags || []).map(String), minPoints: r.minPoints ?? '', maxPoints: r.maxPoints ?? '' }));
        let timer = null;
        return {
            loading: true, busy: false, state: null, cur: null, draft: null, preview: [], counts: {}, issues: [], dirty: false, error: '', notice: '',
            async init() { await this.load(); },
            get paper() { return this.state ? this.state.papers.find((p) => p.id === this.cur) || null : null; },
            get locked() { return !!(this.paper && this.paper.lock); },
            get totalCount() { return this.draft ? this.draft.matrix.reduce((s, r) => s + (Number(r.count) || 0), 0) : 0; },
            get totalPoints() { return this.draft ? this.draft.matrix.reduce((s, r) => s + (Number(r.points) || 0), 0) : 0; },
            async api(url, body) {
                const res = await fetch(url, { method: body ? 'POST' : 'GET', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: body ? JSON.stringify(body) : undefined });
                let data = {};
                try { data = await res.json(); } catch (e) {}
                if (!res.ok) throw new Error(data.error || data.message || 'Không thực hiện được. Vui lòng thử lại.');
                return data;
            },
            url(action) { return cfg.stateUrl.replace(/\/$/, '') + '/' + this.cur + '/' + action; },
            async run(fn) {
                this.busy = true; this.error = '';
                try { await fn(); } catch (e) { this.error = e.message; }
                this.busy = false;
            },
            apply(r, keepId) {
                if (r.notice) this.notice = r.notice;
                if (!r.state) return;
                this.state = r.state;
                const ids = r.state.papers.map((p) => p.id);
                this.select(keepId === 'last' ? (ids[ids.length - 1] ?? null) : (ids.includes(keepId) ? keepId : (ids.includes(this.cur) ? this.cur : ids[ids.length - 1] ?? null)), true);
            },
            async load() { this.loading = true; await this.run(async () => this.apply(await this.api(cfg.stateUrl))); this.loading = false; },
            select(id, force) {
                if (!force && this.dirty && id !== this.cur && !confirm('Đề đang soạn có thay đổi chưa lưu. Chuyển đề khác?')) return;
                this.cur = id;
                const p = this.paper;
                if (!p) { this.draft = null; this.preview = []; return; }
                this.draft = { label: p.label, duration: p.duration, excludeUsed: p.excludeUsed, matrix: toForm(clone(p.matrix)), questions: clone(p.questions) };
                this.preview = clone(p.preview || []);
                this.counts = p.counts || {}; this.issues = p.issues || []; this.dirty = false;
            },
            payload(extra) { return { label: this.draft.label, duration: this.draft.duration, excludeUsed: this.draft.excludeUsed, matrix: this.draft.matrix, questions: this.draft.questions, ...(extra || {}) }; },
            changed() {
                this.dirty = true; this.notice = '';
                if (this.draft.questions.length) { this.draft.questions = []; this.preview = []; }
                clearTimeout(timer);
                timer = setTimeout(() => this.check(), 400);
            },
            async check() {
                if (!this.cur) return;
                try { const r = await this.api(this.url('dem'), this.payload()); this.counts = r.counts; this.issues = r.issues; } catch (e) { this.error = e.message; }
            },
            rowSummary(row) {
                const bank = row.bank === '' ? 'Mọi ngân hàng' : ((this.state.meta.banks.find((b) => String(b.id) === row.bank) || {}).name || 'Ngân hàng');
                const policy = (this.state.meta.policies.find((p) => p.key === (this.draft.excludeUsed ? 'new_only' : row.usagePolicy)) || {}).label || '';
                return bank + ' · ' + row.tags.length + ' tag · ' + policy;
            },
            addRow() {
                this.draft.matrix.push({ id: Math.random().toString(36).slice(2, 10), topic: '', difficulty: 'basic', count: 1, points: 0, bank: '', tags: [], tagMatch: 'any', usagePolicy: 'prefer_new', minPoints: '', maxPoints: '' });
                this.changed();
            },
            removeRow(i) { this.draft.matrix.splice(i, 1); this.changed(); },
            addPaper() { return this.run(async () => { const r = await this.api(cfg.stateUrl, {}); this.dirty = false; this.apply(r, 'last'); }); },
            generate() { return this.run(async () => { const r = await this.api(this.url('sinh'), this.payload()); this.draft.questions = r.questions; this.preview = r.preview; this.notice = r.notice || ''; this.dirty = true; }); },
            replace(id) { return this.run(async () => { const r = await this.api(this.url('thay'), this.payload({ replaceId: id })); this.draft.questions = r.questions; this.preview = r.preview; this.dirty = true; }); },
            save(publish) { return this.run(async () => { const r = await this.api(this.url('luu'), this.payload({ publish: !!publish })); this.dirty = false; this.apply(r, this.cur); }); },
            removePaper() {
                if (!confirm('Xoá ' + (this.paper ? this.paper.label : 'đề') + '? Đề đã chốt (chưa ai làm) cũng bị xoá.')) return;
                return this.run(async () => { const r = await this.api(this.url('xoa'), {}); this.dirty = false; this.apply(r); });
            },
        };
    }
</script>
@endpush
@endonce
