{{-- ═══════════ POPUP "GIAO BÀI" / "GIAO ĐỀ" ═══════════
     SỬA 7/10 — dựng theo education-main/src/components/QuickAssignButton.jsx (QuickAssignModal).
     SỬA 7/10 (khách: "chọn nhiều học sinh dạng select 2 có search") — ô "Tài khoản học sinh" đổi
     thành ô chọn NHIỀU học sinh kiểu Select2: gõ để tìm (tên / email / số điện thoại), bấm để
     chọn, học sinh đã chọn hiện thành thẻ có nút ×. Gợi ý lấy từ route practice.assign.students.
     Nằm TRONG phạm vi x-data="onthiPracticePage(...)" (hoặc onthiExamAssign ở trang chi tiết đề)
     nên dùng chung trạng thái `assign` (có `students`) và các hàm openAssign/closeAssign/submitAssign. --}}
<div x-show="assign.open" x-cloak
     @keydown.escape.window="if (assign.open) closeAssign()"
     @click.self="closeAssign()"
     class="oi-qa-backdrop" role="dialog" aria-modal="true" aria-labelledby="oi-qa-title">
    <div class="oi-qa-panel">
        <header class="oi-qa-heading">
            <span class="oi-qa-icon"><x-lucide name="send" class="h-5 w-5" /></span>
            <div>
                <h2 id="oi-qa-title" x-text="assignLabel + ' cho học sinh'"></h2>
                <p>Chọn một hoặc nhiều học sinh và hạn nộp.</p>
            </div>
            <button type="button" class="oi-qa-close" aria-label="Đóng hộp thoại giao bài" @click="closeAssign()">
                <x-lucide name="x" class="h-5 w-5" />
            </button>
        </header>

        <div class="oi-qa-subject">
            <span x-text="(assign.type === 'exam' ? 'Đề thi' : 'Bài tập') + ' · ' + assign.code"></span>
            <strong x-text="assign.title"></strong>
        </div>

        {{-- Màn thành công: liệt kê từng học sinh vừa được giao --}}
        <div class="oi-qa-success" role="status" x-show="assign.saved" x-cloak>
            <x-lucide name="check-circle-2" />
            <h3 x-text="'Đã ' + assignLabel.toLowerCase() + ' cho ' + (assign.saved ? assign.saved.count : 0) + ' học sinh'"></h3>
            <ul class="oi-qa-result" x-show="assign.saved && assign.saved.students">
                <template x-for="(s, i) in (assign.saved ? assign.saved.students : [])" :key="i">
                    <li>
                        <span class="oi-pk-avatar" x-text="(s.name || '?').trim().split(/\s+/).pop().charAt(0).toUpperCase()"></span>
                        <span class="oi-qa-result-name" x-text="s.name"></span>
                        <span class="oi-qa-result-acc" x-text="s.account || ''"></span>
                    </li>
                </template>
            </ul>
            <p>Hạn nộp: <strong x-text="assign.saved ? assign.saved.deadline : ''"></strong></p>
            <p class="oi-qa-note">Học sinh sẽ thấy trong mục “<span x-text="assign.type === 'exam' ? 'Đề được giao' : 'Bài được giao'"></span>”.</p>
            <button type="button" class="oi-qa-primary" @click="closeAssign()">Hoàn tất</button>
        </div>

        <form x-show="!assign.saved" x-cloak @submit.prevent="submitAssign()" novalidate>
            {{-- ── Ô chọn nhiều học sinh (Select2) ── --}}
            <div class="oi-pk" x-data="onthiStudentPicker()" @click.outside="pkClose()" @keydown.escape="pkEsc($event)">
                <label for="oi-pk-input">
                    <x-lucide name="users" class="h-4 w-4" />Học sinh nhận bài
                    <span class="oi-pk-count" x-show="assign.students.length" x-cloak x-text="assign.students.length + ' đã chọn'"></span>
                </label>

                <div class="oi-pk-box" :class="{ 'is-open': pkOpen }" @click="pkFocus()">
                    <template x-for="s in assign.students" :key="s.id">
                        <span class="oi-pk-chip">
                            <span class="oi-pk-avatar" x-text="pkInitial(s)"></span>
                            <span class="oi-pk-chip-name" x-text="s.name" :title="s.name"></span>
                            <button type="button" class="oi-pk-chip-x" :aria-label="'Bỏ chọn ' + s.name" @click.stop="pkRemove(s.id)">
                                <x-lucide name="x" />
                            </button>
                        </span>
                    </template>
                    <input id="oi-pk-input" type="text" class="oi-pk-input" x-ref="pkInput" x-model="pkQuery"
                           autocomplete="off" autocapitalize="none" spellcheck="false" maxlength="60"
                           role="combobox" aria-autocomplete="list" aria-haspopup="listbox"
                           :aria-expanded="pkOpen ? 'true' : 'false'" aria-controls="oi-pk-list"
                           :placeholder="assign.students.length ? 'Thêm học sinh…' : 'Gõ tên, email hoặc số điện thoại để tìm…'"
                           @focus="pkOpenList()" @input="pkOnInput()"
                           @keydown.arrow-down.prevent="pkMove(1)" @keydown.arrow-up.prevent="pkMove(-1)"
                           @keydown.enter.prevent="pkEnter()"
                           @keydown.backspace="pkBackspace($event)"
                           @keydown.tab="pkClose()">
                    <span class="oi-pk-caret" aria-hidden="true"><x-lucide name="chevron-down" /></span>
                </div>

                <div class="oi-pk-drop" x-show="pkOpen" x-cloak x-transition.opacity.duration.120ms @mousedown.prevent>
                    <div class="oi-pk-bar">
                        <span class="oi-pk-hint" x-text="pkLoading ? 'Đang tìm…' : (pkQuery.trim() ? pkResults.length + ' kết quả' : 'Học sinh mới đăng ký gần đây')"></span>
                        <span class="oi-pk-bar-actions">
                            <button type="button" class="oi-pk-link" x-show="pkResults.length > 1" @click="pkSelectAll()">Chọn tất cả</button>
                            <button type="button" class="oi-pk-link is-danger" x-show="assign.students.length" @click="pkClear()">Bỏ chọn hết</button>
                        </span>
                    </div>
                    <ul id="oi-pk-list" class="oi-pk-list" role="listbox" aria-multiselectable="true" x-ref="pkList">
                        <template x-for="(s, i) in pkResults" :key="s.id">
                            <li role="option" :aria-selected="pkIsSelected(s.id) ? 'true' : 'false'"
                                class="oi-pk-opt" :class="{ 'is-active': i === pkActive, 'is-selected': pkIsSelected(s.id) }"
                                @mouseenter="pkActive = i" @click="pkToggle(s)">
                                <span class="oi-pk-avatar" x-text="pkInitial(s)"></span>
                                <span class="oi-pk-opt-text">
                                    <span class="oi-pk-opt-name" x-text="s.name"></span>
                                    <span class="oi-pk-opt-sub" x-text="pkSub(s)"></span>
                                </span>
                                <span class="oi-pk-tick" x-show="pkIsSelected(s.id)"><x-lucide name="check" /></span>
                            </li>
                        </template>
                    </ul>
                    <p class="oi-pk-empty" x-show="!pkLoading && !pkError && pkResults.length === 0" x-cloak>
                        <x-lucide name="search" />Không tìm thấy học sinh phù hợp.
                    </p>
                    <p class="oi-pk-empty is-error" x-show="pkError" x-cloak>
                        <span x-text="pkError"></span>
                        <button type="button" class="oi-pk-link" @click="pkFetch(pkQuery)">Thử lại</button>
                    </p>
                    <div class="oi-pk-loading" x-show="pkLoading" x-cloak><span class="oi-pk-spin"></span></div>
                </div>
                <p class="oi-qa-note" x-show="!pkOpen">Tìm theo tên, email hoặc số điện thoại học sinh đã đăng ký. Chọn được nhiều học sinh cùng lúc (tối đa 50).</p>
            </div>

            <label for="oi-qa-deadline"><x-lucide name="calendar-days" class="h-4 w-4" />Hạn nộp</label>
            <input id="oi-qa-deadline" type="datetime-local" x-model="assign.deadline" :min="nowLocal()" required
                   @input="assign.error = ''">
            <p class="oi-qa-note">Ngày và giờ theo múi giờ trên thiết bị của bạn.</p>

            <p class="oi-qa-error" role="alert" x-show="assign.error" x-cloak x-text="assign.error"></p>

            <footer class="oi-qa-footer">
                <button type="button" class="oi-qa-cancel" @click="closeAssign()">Hủy</button>
                <button type="submit" class="oi-qa-primary" :disabled="assign.saving">
                    <x-lucide name="send" class="h-4 w-4" />
                    <span x-text="assign.saving ? 'Đang lưu…' : (assignLabel + (assign.students.length > 1 ? ' (' + assign.students.length + ')' : ''))"></span>
                </button>
            </footer>
        </form>
    </div>
</div>

<script>
    /*
     * Ô chọn nhiều học sinh (Select2). Là component Alpine LỒNG trong popup nên đọc/ghi trực tiếp
     * `assign.students` và `assign.error` của component cha (onthiPracticePage / onthiExamAssign)
     * và dùng `assignSearchUrl` của cha. Hàm riêng của ô này đều mang tiền tố "pk" để không đụng tên.
     */
    window.onthiStudentPicker = window.onthiStudentPicker || function () {
        return {
            pkOpen: false,
            pkQuery: '',
            pkResults: [],
            pkLoading: false,
            pkError: '',
            pkActive: 0,
            pkLoaded: false,
            pkTimer: null,
            pkSeq: 0,

            init() {
                // Mỗi lần mở popup là một lượt chọn mới.
                this.$watch('assign.open', (open) => { if (open) { this.pkReset(); } });
            },

            pkReset() {
                this.pkOpen = false;
                this.pkQuery = '';
                this.pkResults = [];
                this.pkError = '';
                this.pkActive = 0;
                this.pkLoaded = false;
                this.pkSeq++;
                clearTimeout(this.pkTimer);
            },

            pkFocus() { if (this.$refs.pkInput) { this.$refs.pkInput.focus(); } },

            pkOpenList() {
                this.pkOpen = true;
                if (!this.pkLoaded) { this.pkFetch(this.pkQuery); }
            },

            pkClose() { this.pkOpen = false; },

            // Esc khi danh sách đang mở chỉ đóng danh sách; đóng rồi bấm Esc nữa mới đóng popup.
            pkEsc(event) {
                if (this.pkOpen) { event.stopPropagation(); this.pkOpen = false; }
            },

            pkOnInput() {
                this.pkOpen = true;
                this.pkActive = 0;
                clearTimeout(this.pkTimer);
                // Chờ người dùng gõ xong một nhịp rồi mới gọi máy chủ.
                this.pkTimer = setTimeout(() => this.pkFetch(this.pkQuery), 250);
            },

            async pkFetch(term) {
                const url = this.assignSearchUrl;
                if (!url) { this.pkError = 'Chưa có địa chỉ tìm học sinh.'; return; }

                const seq = ++this.pkSeq;
                this.pkLoading = true;
                this.pkError = '';

                try {
                    const res = await fetch(url + '?q=' + encodeURIComponent((term || '').trim()), {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (seq !== this.pkSeq) { return; } // đã có lượt tìm mới hơn
                    if (!res.ok) { throw new Error('http ' + res.status); }
                    const data = await res.json();
                    this.pkResults = Array.isArray(data.students) ? data.students : [];
                    this.pkLoaded = true;
                    this.pkActive = 0;
                } catch (e) {
                    if (seq === this.pkSeq) { this.pkError = 'Không tải được danh sách học sinh.'; }
                } finally {
                    if (seq === this.pkSeq) { this.pkLoading = false; }
                }
            },

            pkIsSelected(id) { return this.assign.students.some((s) => s.id === id); },

            pkToggle(s) {
                this.assign.error = '';
                if (this.pkIsSelected(s.id)) { this.pkRemove(s.id); return; }
                if (this.assign.students.length >= 50) { this.assign.error = 'Mỗi lần giao tối đa 50 học sinh.'; return; }
                this.assign.students = [...this.assign.students, { id: s.id, name: s.name, email: s.email, phone: s.phone }];
            },

            pkRemove(id) {
                this.assign.students = this.assign.students.filter((s) => s.id !== id);
            },

            pkSelectAll() {
                const merged = [...this.assign.students];
                for (const s of this.pkResults) {
                    if (merged.length >= 50) { this.assign.error = 'Mỗi lần giao tối đa 50 học sinh.'; break; }
                    if (!merged.some((m) => m.id === s.id)) { merged.push({ id: s.id, name: s.name, email: s.email, phone: s.phone }); }
                }
                this.assign.students = merged;
            },

            pkClear() { this.assign.students = []; },

            pkMove(step) {
                if (!this.pkOpen) { this.pkOpenList(); return; }
                const n = this.pkResults.length;
                if (!n) { return; }
                this.pkActive = (this.pkActive + step + n) % n;
                this.$nextTick(() => {
                    const el = this.$refs.pkList && this.$refs.pkList.querySelectorAll('li')[this.pkActive];
                    if (el && el.scrollIntoView) { el.scrollIntoView({ block: 'nearest' }); }
                });
            },

            pkEnter() {
                if (!this.pkOpen) { this.pkOpenList(); return; }
                const s = this.pkResults[this.pkActive];
                if (s) { this.pkToggle(s); }
            },

            pkBackspace(event) {
                if (this.pkQuery === '' && this.assign.students.length) {
                    event.preventDefault();
                    this.assign.students = this.assign.students.slice(0, -1);
                }
            },

            pkInitial(s) {
                const parts = String(s.name || '?').trim().split(/\s+/);
                return (parts[parts.length - 1] || '?').charAt(0).toUpperCase();
            },

            pkSub(s) { return [s.email, s.phone].filter(Boolean).join(' · ') || 'Chưa có email / số điện thoại'; },
        };
    };
</script>
