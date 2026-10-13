{{-- ═══════════ BÀI ĐÃ GIAO / ĐỀ ĐÃ GIAO (giáo viên + admin) ═══════════
     SỬA 7/10 — dựng theo education-main/src/components/AssignmentManagement.jsx: 4 ô thống kê,
     bộ lọc (tìm kiếm / trạng thái / sắp xếp), danh sách mỗi dòng một học sinh nhận bài, bấm
     "Chi tiết" để xem mốc thời gian + bài làm đã nộp, phân trang 10 dòng.

     Khác bản mẫu: dữ liệu lấy từ máy chủ (bảng practice_assignments + bài làm thật), nên nhìn thấy
     được từ mọi thiết bị, không còn "lưu trên trình duyệt này".

     Tham số: $type ('problem' | 'exam'), $managed (kết quả PracticeAssignmentService::managed()),
     $assignRole ('teacher' | 'admin'). Hàm Alpine onthiAssignmentManager nằm ở
     partials/practice-page-script.

     SỬA 13/10 (khách: "bỏ nút Mở kho đề, thay bằng In / Xuất kết quả") — dựng theo
     education-main/src/components/AssignmentReportDialog.jsx: popup "Xem trước & in kết quả" có bộ lọc
     riêng (tìm kiếm / trạng thái / sắp xếp / lọc theo ngày giao·nộp·hạn), bản xem trước cập nhật ngay,
     nút "Tải ảnh PNG" (nhiều trang thì gói ZIP) và "In / Lưu PDF". Lọc + xuất chạy hoàn toàn trên trình
     duyệt từ chính danh sách lượt giao đã có, không gọi thêm máy chủ. --}}
@php
    $isExam = $type === 'exam';
    $managedTitle = $isExam ? 'Đề đã giao' : 'Bài đã giao';
    $managedPayload = [
        'rows' => $managed['rows'] ?? [],
        'type' => $type,
        'isAdmin' => $assignRole === 'admin',
        'title' => $managedTitle,
        'actor' => auth()->user()?->name ?: 'Người dùng',
    ];
    $reportSortOptions = [
        'title' => 'Tên bài / đề',
        'student' => 'Học sinh',
        'status' => 'Trạng thái',
        'score' => 'Điểm gần nhất',
        'submitted' => 'Thời gian nộp',
        'deadline' => 'Hạn nộp',
        'assigned' => 'Thời gian giao',
    ];
@endphp

<section class="assignment-management" aria-label="Quản lý {{ mb_strtolower($managedTitle) }}"
         x-data="onthiAssignmentManager({{ Js::from($managedPayload) }})">
    <header class="managed-heading">
        <div>
            <p>THEO DÕI HỌC SINH</p>
            <h2><x-lucide name="clipboard-list" class="h-5 w-5" />{{ $managedTitle }}</h2>
            <span>{{ $assignRole === 'admin' ? 'Tất cả lượt giao trong hệ thống' : 'Các lượt giao do bạn tạo' }} · Mỗi dòng là một học sinh nhận bài.</span>
        </div>
        <button type="button" class="managed-export" @click="openReport()">
            <x-lucide name="printer" />In / Xuất kết quả
        </button>
    </header>

    <div class="managed-stats">
        <div>
            <x-lucide name="users" />
            <span>Lượt giao</span>
            <strong x-text="rows.length"></strong>
            <small><span x-text="studentCount" style="font-size:inherit;font-weight:inherit"></span> học sinh · <span x-text="subjectCount" style="font-size:inherit;font-weight:inherit"></span> {{ $isExam ? 'đề' : 'bài' }}</small>
        </div>
        <div class="is-green">
            <x-lucide name="check-circle-2" />
            <span>Đã hoàn thành</span>
            <strong x-text="counts.completed"></strong>
            <small><span x-text="submittedCount" style="font-size:inherit;font-weight:inherit"></span>/<span x-text="rows.length" style="font-size:inherit;font-weight:inherit"></span> đã nộp · <span x-text="submittedPercent" style="font-size:inherit;font-weight:inherit"></span>%</small>
        </div>
        <div class="is-amber">
            <x-lucide name="clock" />
            <span>Chờ chấm</span>
            <strong x-text="counts.pending"></strong>
            <small>Đã nộp, đang chờ kết quả</small>
        </div>
        <div class="is-red">
            <x-lucide name="clock" />
            <span>Chưa nộp</span>
            <strong x-text="counts.unsubmitted + counts.overdue"></strong>
            <small><span x-text="counts.overdue" style="font-size:inherit;font-weight:inherit"></span> lượt đã quá hạn</small>
        </div>
    </div>

    <div class="managed-filters">
        <label class="managed-search">
            <span>Tìm {{ $isExam ? 'đề' : 'bài' }} hoặc học sinh</span>
            <div>
                <x-lucide name="search" />
                <input type="text" x-model="query" placeholder="Tên {{ $isExam ? 'đề' : 'bài' }}, mã, tài khoản…">
            </div>
        </label>
        <label>
            <span>Trạng thái</span>
            <select aria-label="Trạng thái" x-model="status">
                <option value="all">Tất cả trạng thái</option>
                <option value="unsubmitted">Chưa nộp</option>
                <option value="pending">Chờ chấm</option>
                <option value="completed">Đã hoàn thành</option>
                <option value="overdue">Quá hạn · Chưa nộp</option>
                <option value="late">Nộp muộn</option>
            </select>
        </label>
        <label>
            <span>Sắp xếp</span>
            <select aria-label="Sắp xếp" x-model="sort">
                <option value="assigned">Mới giao gần nhất</option>
                <option value="deadline">Hạn nộp gần nhất</option>
                <option value="submitted">Mới nộp gần nhất</option>
                <option value="score">Điểm cao nhất</option>
                <option value="custom" hidden disabled>Theo cột đã chọn</option>
            </select>
        </label>
    </div>

    <div class="managed-count" role="status">
        <span>Hiển thị <span x-text="filtered.length"></span> / <span x-text="rows.length"></span> lượt giao</span>
        <button type="button" x-show="query || status !== 'all'" x-cloak @click="clearFilters()">Xóa bộ lọc</button>
    </div>

    <div class="managed-list">
        <div class="managed-grid managed-table-header">
            <span><button type="button" class="oi-sort-btn" :class="{ 'is-active': sortKey === 'title' }" :aria-pressed="sortKey === 'title' ? 'true' : 'false'" title="Sắp xếp theo {{ $isExam ? 'đề' : 'bài' }}" @click="toggleSort('title')"><span>{{ $isExam ? 'Đề' : 'Bài' }} / Học sinh</span><x-lucide name="arrow-up-down" x-show="sortKey !== 'title'" /><x-lucide name="chevron-up" x-show="sortKey === 'title' && sortDir === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="sortKey === 'title' && sortDir === 'desc'" x-cloak /></button></span>
            <span><button type="button" class="oi-sort-btn" :class="{ 'is-active': sortKey === 'status' }" :aria-pressed="sortKey === 'status' ? 'true' : 'false'" title="Sắp xếp theo Trạng thái" @click="toggleSort('status')"><span>Trạng thái</span><x-lucide name="arrow-up-down" x-show="sortKey !== 'status'" /><x-lucide name="chevron-up" x-show="sortKey === 'status' && sortDir === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="sortKey === 'status' && sortDir === 'desc'" x-cloak /></button></span>
            <span><button type="button" class="oi-sort-btn" :class="{ 'is-active': sortKey === 'score' }" :aria-pressed="sortKey === 'score' ? 'true' : 'false'" title="Sắp xếp theo Điểm gần nhất" @click="toggleSort('score')"><span>Điểm gần nhất</span><x-lucide name="arrow-up-down" x-show="sortKey !== 'score'" /><x-lucide name="chevron-up" x-show="sortKey === 'score' && sortDir === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="sortKey === 'score' && sortDir === 'desc'" x-cloak /></button></span>
            <span><button type="button" class="oi-sort-btn" :class="{ 'is-active': sortKey === 'submitted' }" :aria-pressed="sortKey === 'submitted' ? 'true' : 'false'" title="Sắp xếp theo Thời gian nộp" @click="toggleSort('submitted')"><span>Thời gian nộp</span><x-lucide name="arrow-up-down" x-show="sortKey !== 'submitted'" /><x-lucide name="chevron-up" x-show="sortKey === 'submitted' && sortDir === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="sortKey === 'submitted' && sortDir === 'desc'" x-cloak /></button></span>
            <span><button type="button" class="oi-sort-btn" :class="{ 'is-active': sortKey === 'deadline' }" :aria-pressed="sortKey === 'deadline' ? 'true' : 'false'" title="Sắp xếp theo Hạn nộp" @click="toggleSort('deadline')"><span>Hạn nộp</span><x-lucide name="arrow-up-down" x-show="sortKey !== 'deadline'" /><x-lucide name="chevron-up" x-show="sortKey === 'deadline' && sortDir === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="sortKey === 'deadline' && sortDir === 'desc'" x-cloak /></button></span>
            <span>Chi tiết</span>
        </div>

        <template x-for="row in visible" :key="row.id">
            <article class="managed-entry" :aria-label="row.title + ' · ' + row.account">
                <div class="managed-grid managed-row">
                    <div class="managed-identity">
                        <h3 x-text="row.title"></h3>
                        <code x-text="row.code"></code>
                        <strong x-text="row.studentName"></strong>
                        <span x-text="row.account"></span>
                        <small x-show="isAdmin" x-cloak>Người giao: <span x-text="row.teacher"></span></small>
                    </div>
                    <div data-label="Trạng thái">
                        <span class="managed-status" :class="states[row.status].tone" x-text="states[row.status].label"></span>
                        <small class="managed-late" x-show="row.late" x-cloak>Nộp muộn</small>
                    </div>
                    <div data-label="Điểm gần nhất">
                        <strong class="managed-score" x-text="row.scoreLabel ? row.scoreLabel : (row.status === 'pending' ? 'Chờ chấm' : '—')"></strong>
                        <small x-show="row.scoreAfterLabel" x-cloak>Sau trừ: <span x-text="row.scoreAfterLabel"></span></small>
                        <small x-show="row.attempts > 0" x-cloak><span x-text="row.attempts"></span> lượt nộp</small>
                    </div>
                    <div data-label="Thời gian nộp">
                        <time x-text="row.submittedAt || 'Chưa nộp'"></time>
                        <small x-show="row.duration" x-cloak>Làm trong: <span x-text="row.duration"></span></small>
                    </div>
                    <div data-label="Hạn nộp">
                        <time x-text="row.deadline"></time>
                        <small>Giao: <span x-text="row.assignedAt"></span></small>
                    </div>
                    <div class="managed-actions">
                        <button type="button" :aria-expanded="expandedId === row.id" @click="toggle(row.id)">
                            <span x-show="expandedId !== row.id" style="display:inline-flex;align-items:center;gap:4px"><x-lucide name="clipboard-list" />Chi tiết</span>
                            <span x-show="expandedId === row.id" x-cloak style="display:inline-flex;align-items:center;gap:4px"><x-lucide name="x" />Thu gọn</span>
                        </button>
                    </div>
                </div>

                <template x-if="expandedId === row.id">
                    <section class="managed-detail" :aria-label="'Chi tiết giao cho ' + row.account">
                        <div class="managed-detail-title">
                            <h3 x-text="row.title"></h3>
                            <span><span x-text="row.account"></span> · <span x-text="row.attempts"></span> lượt nộp từ khi giao</span>
                        </div>
                        <dl class="managed-milestones">
                            <div><dt>Người giao</dt><dd x-text="row.teacher"></dd></div>
                            <div><dt>Giao lúc</dt><dd x-text="row.assignedAt"></dd></div>
                            <div><dt>Hạn nộp</dt><dd x-text="row.deadline"></dd></div>
                        </dl>

                        <template x-if="!row.submittedAt">
                            <p class="managed-no-submission">Học sinh chưa có lượt nộp nào cho nội dung này kể từ lúc giao.</p>
                        </template>

                        <template x-if="row.submittedAt">
                            <div>
                                <dl class="managed-milestones">
                                    <div x-show="row.startedAt" x-cloak><dt>Bắt đầu làm</dt><dd x-text="row.startedAt"></dd></div>
                                    <div><dt>Nộp lúc</dt><dd x-text="row.submittedAt"></dd></div>
                                    <div x-show="row.duration" x-cloak><dt>Thời gian làm</dt><dd x-text="row.duration"></dd></div>
                                    <div><dt>Chấm xong</dt><dd x-text="row.status === 'pending' ? 'Đang chờ chấm' : (row.gradedAt || row.submittedAt)"></dd></div>
                                </dl>
                                <p class="managed-result">
                                    <strong x-text="row.scoreLabel ? row.scoreLabel + ' điểm' : 'Chờ chấm'"></strong>
                                    <span x-show="row.scoreAfterLabel" x-cloak>Sau trừ: <span x-text="row.scoreAfterLabel"></span> điểm</span>
                                    <span x-text="row.resultLabel"></span>
                                    <span class="managed-late" x-show="row.late" x-cloak>Nộp muộn</span>
                                    <span x-show="row.tests" x-cloak x-text="row.tests"></span>
                                </p>
                                <template x-if="type === 'problem'">
                                    <div class="managed-answer">
                                        <h4>Bài làm đã nộp <span x-show="row.language" x-cloak x-text="row.language"></span></h4>
                                        <pre x-text="row.source ? row.source : 'Chưa lưu nội dung bài làm.'"></pre>
                                    </div>
                                </template>
                                <template x-if="type === 'exam'">
                                    <p class="managed-no-submission">Xem bài làm từng câu trong phần kết quả của lượt thi.</p>
                                </template>
                            </div>
                        </template>
                    </section>
                </template>
            </article>
        </template>

        <div class="managed-empty" x-show="filtered.length === 0" x-cloak>
            <x-lucide name="clipboard-list" />
            <h3 x-text="rows.length ? 'Không có lượt giao phù hợp' : 'Chưa có {{ mb_strtolower($managedTitle) }}'"></h3>
            <p x-text="rows.length ? 'Thử đổi từ khóa hoặc trạng thái lọc.' : 'Dùng nút “{{ $isExam ? 'Giao đề' : 'Giao bài' }}” trong kho luyện tập để giao cho học sinh và theo dõi tại đây.'"></p>
        </div>
    </div>

    <nav class="managed-pagination" aria-label="Phân trang lượt giao" x-show="totalPages > 1" x-cloak>
        <button type="button" aria-label="Trang trước" :disabled="currentPage === 1" @click="goTo(currentPage - 1)"><x-lucide name="chevron-left" /></button>
        <span>Trang <span x-text="currentPage"></span>/<span x-text="totalPages"></span></span>
        <button type="button" aria-label="Trang sau" :disabled="currentPage === totalPages" @click="goTo(currentPage + 1)"><x-lucide name="chevron-right" /></button>
    </nav>

    <p class="managed-note">Điểm hiển thị từ lần nộp gần nhất sau lúc giao; hoàn thành không đồng nghĩa đạt điểm tối đa. Chỉ hiện tối đa 300 lượt giao mới nhất.</p>

    {{-- ── Popup "Xem trước & in kết quả" — <dialog> mở bằng showModal() nên luôn nổi trên cùng,
         không bị khối cha (overflow / z-index) che. Bộ lọc ở đây độc lập với bộ lọc danh sách:
         lúc mở thì chép từ danh sách sang, đổi trong popup không làm đổi danh sách. ── --}}
    <dialog x-ref="reportDialog" class="managed-report-dialog" tabindex="-1" aria-labelledby="managed-report-title-{{ $type }}"
            @cancel.prevent="closeReport()" @close="report.open = false">
        <header class="managed-report-toolbar">
            <div>
                <h2 id="managed-report-title-{{ $type }}">Xem trước &amp; in kết quả</h2>
                <p>Đổi tiêu chí để cập nhật bản in ngay bên dưới.</p>
            </div>
            <button type="button" class="managed-report-close" aria-label="Đóng xem trước" @click="closeReport()"><x-lucide name="x" /></button>
        </header>

        <fieldset class="managed-filters managed-report-filters" :disabled="report.busy" aria-label="Tiêu chí bản in">
            <label class="managed-search">
                <span>Tìm {{ $isExam ? 'đề' : 'bài' }} hoặc học sinh</span>
                <input type="text" x-model="report.query" @input="report.message = ''" placeholder="Tên {{ $isExam ? 'đề' : 'bài' }}, mã, tài khoản…">
            </label>
            <label>
                <span>Trạng thái</span>
                <select aria-label="Trạng thái" x-model="report.status" @change="report.message = ''">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="unsubmitted">Chưa nộp</option>
                    <option value="pending">Chờ chấm</option>
                    <option value="completed">Đã hoàn thành</option>
                    <option value="overdue">Quá hạn · Chưa nộp</option>
                    <option value="late">Nộp muộn</option>
                </select>
            </label>
            <label class="managed-report-sort">
                <span>Sắp xếp kết quả</span>
                <select aria-label="Sắp xếp kết quả" x-model="report.sort" @change="report.message = ''">
                    @foreach ($reportSortOptions as $sortKey => $sortLabel)
                        <optgroup label="{{ $sortLabel }}">
                            <option value="{{ $sortKey }}:asc">{{ $sortLabel }} tăng dần</option>
                            <option value="{{ $sortKey }}:desc">{{ $sortLabel }} giảm dần</option>
                        </optgroup>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Lọc thời gian theo</span>
                <select aria-label="Lọc thời gian theo" x-model="report.dateField" @change="report.message = ''">
                    <option value="assigned">Ngày giao</option>
                    <option value="submitted">Ngày nộp gần nhất</option>
                    <option value="deadline">Hạn nộp</option>
                </select>
            </label>
            <label>
                <span>Từ ngày</span>
                <input type="date" x-model="report.from" :max="report.to || null" :aria-invalid="reportInvalidDates ? 'true' : 'false'"
                       aria-describedby="managed-report-date-help-{{ $type }}" @change="report.message = ''">
            </label>
            <label>
                <span>Đến ngày</span>
                <input type="date" x-model="report.to" :min="report.from || null" :aria-invalid="reportInvalidDates ? 'true' : 'false'"
                       aria-describedby="managed-report-date-help-{{ $type }}" @change="report.message = ''">
            </label>
            <button type="button" class="managed-report-reset" @click="resetReportFilters()">Xóa tiêu chí lọc</button>
        </fieldset>

        <p id="managed-report-date-help-{{ $type }}" class="managed-report-help" :class="{ 'is-error': reportInvalidDates }" :role="reportInvalidDates ? 'alert' : null"
           x-text="reportInvalidDates ? 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.' : 'Để trống ngày = toàn bộ thời gian · Tính cả hai ngày theo giờ Việt Nam.'"></p>
        <p role="status" class="managed-report-message" x-show="report.message" x-cloak x-text="report.message"></p>

        <div class="managed-report-scroll" role="region" aria-label="Bản xem trước trực tiếp" tabindex="0">
            <template x-if="report.open && reportMatching.length">
                <div class="managed-report-paper">
                    <h3 x-text="reportData.title"></h3>
                    <p x-text="reportData.created"></p>
                    <p x-text="reportData.filters"></p>
                    <p><strong x-text="reportData.summary"></strong></p>
                    <table>
                        <thead><tr><template x-for="header in reportData.headers" :key="header"><th scope="col" x-text="header"></th></template></tr></thead>
                        <tbody>
                            <template x-for="(cells, index) in reportData.rows" :key="index">
                                <tr><template x-for="(cell, col) in cells" :key="col"><td x-text="cell"></td></template></tr>
                            </template>
                        </tbody>
                    </table>
                    <p x-text="reportData.note"></p>
                </div>
            </template>
            <template x-if="report.open && !reportMatching.length">
                <div class="managed-report-empty">
                    <x-lucide name="printer" />
                    <strong x-text="reportInvalidDates ? 'Khoảng ngày chưa hợp lệ' : 'Chưa có kết quả để in'"></strong>
                    <p x-text="rows.length ? 'Điều chỉnh tiêu chí phía trên để xem kết quả.' : 'Chưa có lượt giao để xuất kết quả.'"></p>
                </div>
            </template>
        </div>

        <footer class="managed-report-footer">
            <span role="status" aria-live="polite" x-text="report.busy ? 'Đang tạo bản xuất…' : reportMatching.length + ' lượt giao phù hợp · ' + reportStudentCount + ' học sinh'"></span>
            <div>
                <button type="button" class="managed-report-secondary" :disabled="reportCannotExport" @click="runReport('png')"><x-lucide name="download" />Tải ảnh PNG</button>
                <button type="button" class="managed-export" :disabled="reportCannotExport" @click="runReport('print')"><x-lucide name="printer" />In / Lưu PDF</button>
            </div>
        </footer>
    </dialog>
</section>
