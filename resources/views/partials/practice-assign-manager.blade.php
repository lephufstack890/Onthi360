{{-- ═══════════ BÀI ĐÃ GIAO / ĐỀ ĐÃ GIAO (giáo viên + admin) ═══════════
     SỬA 7/10 — dựng theo education-main/src/components/AssignmentManagement.jsx: 4 ô thống kê,
     bộ lọc (tìm kiếm / trạng thái / sắp xếp), danh sách mỗi dòng một học sinh nhận bài, bấm
     "Chi tiết" để xem mốc thời gian + bài làm đã nộp, phân trang 10 dòng.

     Khác bản mẫu: dữ liệu lấy từ máy chủ (bảng practice_assignments + bài làm thật), nên nhìn thấy
     được từ mọi thiết bị, không còn "lưu trên trình duyệt này".

     Tham số: $type ('problem' | 'exam'), $managed (kết quả PracticeAssignmentService::managed()),
     $assignRole ('teacher' | 'admin'). Hàm Alpine onthiAssignmentManager nằm ở
     partials/practice-page-script. --}}
@php
    $isExam = $type === 'exam';
    $managedTitle = $isExam ? 'Đề đã giao' : 'Bài đã giao';
    $managedPayload = [
        'rows' => $managed['rows'] ?? [],
        'type' => $type,
        'isAdmin' => $assignRole === 'admin',
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
        <button type="button" class="managed-library" @click="setScope('{{ $isExam ? 'exam' : 'problem' }}', 'all')">
            Mở kho {{ $isExam ? 'đề' : 'bài tập' }}<x-lucide name="chevron-right" class="h-4 w-4" />
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
            </select>
        </label>
    </div>

    <div class="managed-count" role="status">
        <span>Hiển thị <span x-text="filtered.length"></span> / <span x-text="rows.length"></span> lượt giao</span>
        <button type="button" x-show="query || status !== 'all'" x-cloak @click="clearFilters()">Xóa bộ lọc</button>
    </div>

    <div class="managed-list">
        <div class="managed-grid managed-table-header" aria-hidden="true">
            <span>{{ $isExam ? 'Đề' : 'Bài' }} / Học sinh</span><span>Trạng thái</span><span>Điểm gần nhất</span><span>Thời gian nộp</span><span>Hạn nộp</span><span>Chi tiết</span>
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
</section>
