{{-- Danh sách lượt giao (TeacherWorkPanel của source mới). Dùng chung cho "Bài & đề" ($resultsOnly = false) và
     "Kết quả & nhận xét" ($resultsOnly = true: chỉ lượt đã có bài nộp). Phải nằm trong x-data của window.twWorkList(). --}}
@php $resultsOnly = $resultsOnly ?? false; @endphp
        <section class="tw-card" x-show="!assigning">
            <div class="tw-heading">
                <div>
                    <h2><x-lucide name="{{ $resultsOnly ? 'file-text' : 'clipboard-check' }}" />{{ $resultsOnly ? 'Kết quả & nhận xét' : 'Bài & đề' }}</h2>
                    <p>{{ $resultsOnly ? 'Xem bài làm và nhận xét cho từng học sinh.' : 'Ai được giao · Hạn nộp · Kết quả thực hiện' }}</p>
                </div>
                @unless ($resultsOnly)
                    <button type="button" class="tw-button" x-show="scope.canAssign" x-cloak @click="assigning = true">Giao bài / đề</button>
                @endunless
            </div>

            <div class="tw-filters">
                <label>Loại
                    <select aria-label="Loại" x-model="kind" @change="reset()">
                        <option value="all">Bài &amp; đề</option><option value="problem">Bài tập</option><option value="exam">Đề</option>
                    </select>
                </label>
                <label>Trạng thái
                    <select aria-label="Trạng thái" x-model="status" @change="reset()" x-init="$nextTick(() => { $el.value = status })">
                        <option value="all">Tất cả</option>
                        <template x-for="key in stateKeys" :key="key"><option :value="key" x-text="stateLabels[key]"></option></template>
                    </select>
                </label>
                <label>Tìm kiếm
                    <input type="search" x-model="query" @input="reset()" placeholder="Tên nội dung hoặc học sinh">
                </label>
            </div>
            <p class="tw-muted tw-work-count" role="status" x-text="total + ' lượt giao'"></p>

            <div class="tw-work-list">
                <template x-for="r in visible" :key="r.id + r.type">
                    <article class="tw-work-row">
                        <div class="tw-work-summary">
                            <div class="tw-work-info">
                                <div class="tw-work-title">
                                    <span class="tw-work-kind" :class="'tw-work-kind-' + r.type" x-text="r.type === 'exam' ? 'Đề' : 'Bài'"></span>
                                    <h3 x-text="r.title"></h3>
                                </div>
                                <div class="tw-work-meta">
                                    <span class="tw-work-recipient" :title="r.account" x-text="r.student"></span>
                                    <span>Hạn: <time x-text="r.deadline"></time></span>
                                </div>
                            </div>
                            <div class="tw-work-result">
                                <span class="tw-tag" :class="'tw-status-' + r.status">
                                    <template x-if="r.status === 'completed' && r.score"><span><strong x-text="r.score"></strong> · Đã chấm</span></template>
                                    <template x-if="!(r.status === 'completed' && r.score)"><span x-text="stateLabels[r.status] || r.status"></span></template>
                                </span>
                                <small x-show="r.late">Nộp muộn</small>
                            </div>
                            <div class="tw-work-actions">
                                <button type="button" class="tw-button" :aria-expanded="isOpen(r, 'assignment')" @click="toggle(r, 'assignment')">Chi tiết lượt giao</button>
                                <button type="button" class="tw-button" x-show="submitted(r)" x-cloak :aria-expanded="isOpen(r, 'submission')" @click="toggle(r, 'submission')">Xem bài nộp</button>
                            </div>
                        </div>

                        <div class="tw-submission" x-show="open && open.id === r.id" x-cloak>
                            <div class="tw-heading"><h4 x-text="open && open.view === 'assignment' ? 'Chi tiết lượt giao' : 'Bài nộp & nhận xét'"></h4><button type="button" class="tw-secondary" @click="open = null">Thu gọn</button></div>

                            <dl class="tw-assignment-details" x-show="open && open.id === r.id && open.view === 'assignment'">
                                <div><dt>Nội dung</dt><dd x-text="(r.type === 'exam' ? 'Đề' : 'Bài tập') + ' · ' + r.title + (r.code ? ' (' + r.code + ')' : '')"></dd></div>
                                <div><dt>Người giao</dt><dd x-text="r.teacher"></dd></div>
                                <div><dt>Học sinh nhận</dt><dd x-text="r.student + ' · ' + r.account"></dd></div>
                                <div><dt>Lớp học</dt><dd>Giao riêng cho học sinh</dd></div>
                                <div><dt>Thời điểm giao</dt><dd x-text="r.assignedAt"></dd></div>
                                <div><dt>Hạn nộp</dt><dd x-text="r.deadline"></dd></div>
                                <div><dt>Trạng thái</dt><dd x-text="stateLabels[r.status] || r.status"></dd></div>
                            </dl>

                            <div x-show="open && open.id === r.id && open.view === 'submission'">
                                <div class="tw-submission-score" role="group" aria-label="Điểm bài nộp">
                                    <span>Điểm bài nộp</span>
                                    <strong x-text="r.score || (r.status === 'pending' ? 'Chờ chấm' : 'Chưa có điểm')"></strong>
                                    <span class="tw-tag" :class="resultClass(r)" x-show="r.score && r.result" x-text="r.result"></span>
                                    <span class="tw-tag" x-show="r.scoreAfter" x-text="'Sau trừ: ' + r.scoreAfter"></span>
                                </div>
                                <p class="tw-muted" x-text="'Nộp: ' + (r.submittedAt || '—') + (r.late ? ' · Nộp muộn' : '') + ' · ' + r.attempts + ' lượt' + (r.tests ? ' · ' + r.tests : '') + (r.language ? ' · ' + r.language : '') + (r.duration ? ' · ' + r.duration : '')"></p>
                                <pre x-show="r.type === 'problem'" x-text="r.source || 'Chưa lưu nội dung bài làm.'"></pre>
                                <p class="tw-muted" x-show="r.type === 'exam'">Xem chi tiết từng câu của đề ở Nhật ký làm đề: <a href="{{ route('practice.index') }}" style="color:#176b50;font-weight:600">mở kho luyện tập</a>.</p>

                                <form @submit.prevent="saveFeedback(r)" class="tw-form">
                                    <label>Nhận xét cho bài nộp
                                        <textarea rows="3" maxlength="2000" x-model="r.fb" @input="r.fbMsg = ''; r.fbErr = ''"></textarea>
                                    </label>
                                    <div class="tw-actions">
                                        <button type="submit" class="tw-button" :disabled="r.fbBusy" x-text="r.fbBusy ? 'Đang lưu…' : 'Lưu nhận xét'"></button>
                                        <span class="tw-muted" x-show="r.feedbackAt" x-text="'Đã lưu ' + r.feedbackAt"></span>
                                    </div>
                                    <p class="tw-success" role="status" x-show="r.fbMsg" x-cloak x-text="r.fbMsg"></p>
                                    <p class="tw-error" role="alert" x-show="r.fbErr" x-cloak x-text="r.fbErr"></p>
                                    <p class="tw-muted">Nhận xét hiện cho học sinh ở mục Bài &amp; đề được giao; không thay đổi điểm chấm tự động.</p>
                                </form>
                            </div>
                        </div>
                    </article>
                </template>
            </div>

            <div class="tw-empty" x-show="total === 0" x-cloak>
                <p x-text="rows.length ? emptyFiltered : emptyAll"></p>
                <button type="button" class="tw-secondary" x-show="rows.length" @click="kind = 'all'; status = 'all'; query = ''; reset()" style="margin-top:8px">Bỏ bộ lọc</button>
            </div>

            @include('student._pager')

            @unless ($resultsOnly)
                <div class="tw-actions"><a class="tw-secondary" href="{{ route('practice.index') }}">Mở kho luyện tập đầy đủ</a></div>
            @endunless
        </section>
