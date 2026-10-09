{{-- ═══════════ POPUP "GIAO TÀI LIỆU" ═══════════
     SỬA 9/10 — dựng theo education-main/src/components/QuickAssignButton.jsx (QuickAssignModal, type="material"):
     chọn học sinh, HẠN ĐỌC, THỜI HẠN CẤP QUYỀN ĐỌC (7/30/90/365 ngày) và lời nhắn.
     Ô chọn nhiều học sinh dùng chung với popup Giao bài ở trang Luyện tập (partials/student-picker-script).
     Nằm TRONG phạm vi x-data="onthiMaterialsPage(...)" nên dùng chung trạng thái `assign` (có `students`) và
     các hàm closeAssign/submitAssign/nowLocal/assignSearchUrl của component đó. --}}
<div x-show="assign.open" x-cloak
     @keydown.escape.window="if (assign.open) closeAssign()"
     @click.self="closeAssign()"
     class="oi-qa-backdrop" role="dialog" aria-modal="true" aria-labelledby="oi-qa-title">
    <div class="oi-qa-panel">
        <header class="oi-qa-heading">
            <span class="oi-qa-icon"><x-lucide name="send" class="h-5 w-5" /></span>
            <div>
                <h2 id="oi-qa-title">Giao tài liệu cho học sinh</h2>
                <p>Chọn học sinh, hạn đọc và thời hạn sử dụng.</p>
            </div>
            <button type="button" class="oi-qa-close" aria-label="Đóng hộp thoại giao tài liệu" @click="closeAssign()">
                <x-lucide name="x" class="h-5 w-5" />
            </button>
        </header>

        <div class="oi-qa-subject">
            <span>Tài liệu</span>
            <strong x-text="assign.title"></strong>
        </div>

        {{-- Màn thành công: liệt kê từng học sinh vừa được giao --}}
        <div class="oi-qa-success" role="status" x-show="assign.saved" x-cloak>
            <x-lucide name="check-circle-2" />
            <h3 x-text="'Đã giao tài liệu cho ' + (assign.saved ? assign.saved.count : 0) + ' học sinh'"></h3>
            <ul class="oi-qa-result" x-show="assign.saved && assign.saved.students">
                <template x-for="(s, i) in (assign.saved ? assign.saved.students : [])" :key="i">
                    <li>
                        <span class="oi-pk-avatar" x-text="(s.name || '?').trim().split(/\s+/).pop().charAt(0).toUpperCase()"></span>
                        <span class="oi-qa-result-name" x-text="s.name"></span>
                        <span class="oi-qa-result-acc" x-text="s.account || ''"></span>
                    </li>
                </template>
            </ul>
            <p>Hạn đọc: <strong x-text="assign.saved ? assign.saved.deadline : ''"></strong></p>
            <p>Quyền đọc: <strong x-text="assign.saved ? assign.saved.accessDays + ' ngày kể từ khi giao' : ''"></strong></p>
            <p class="oi-qa-note">Học sinh sẽ thấy trong mục “Tài liệu được giao” và “Tài liệu của tôi”.</p>
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

            <label for="oi-qa-deadline"><x-lucide name="calendar-days" class="h-4 w-4" />Hạn đọc</label>
            <input id="oi-qa-deadline" type="datetime-local" x-model="assign.deadline" :min="nowLocal()" required
                   @input="assign.error = ''">
            <p class="oi-qa-note">Ngày và giờ theo múi giờ trên thiết bị của bạn.</p>

            <label for="oi-qa-access">Thời hạn cấp quyền đọc</label>
            <select id="oi-qa-access" x-model="assign.accessDays" @change="assign.error = ''">
                <template x-for="d in accessDays" :key="d">
                    <option :value="String(d)" x-text="d + ' ngày'"></option>
                </template>
            </select>
            <p class="oi-qa-note">Học sinh được đọc tài liệu trong thời hạn này, tính từ lúc giao. Hạn đọc phải nằm trong thời hạn này. Giao lại sẽ cập nhật lượt giao hiện có.</p>

            <label for="oi-qa-note">Lời nhắn cho học sinh (không bắt buộc)</label>
            <textarea id="oi-qa-note" rows="3" maxlength="1000" x-model="assign.note"
                      placeholder="Ví dụ: Đọc chương 1 và làm các bài tập cuối chương."></textarea>

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

@include('partials.student-picker-script')
