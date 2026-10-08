<script>
    function onthiPracticePage(config) {
        return {
            problems: config.problems,
            exams: config.exams,
            problemPageSize: config.problemPageSize,
            examPageSize: config.examPageSize,

            // SỬA 7/10 (khách: "bài được giao / giáo viên giao bài / nhật ký") — quyền của người xem
            // + trạng thái giao bài. `role` là guest | student | teacher | admin | member.
            role: config.role || 'guest',
            canViewAssigned: !!config.canViewAssigned,
            canManage: !!config.canManage,
            canAssign: !!config.canAssign,
            assignUrl: config.assignUrl || '',
            assignSearchUrl: config.assignSearchUrl || '',
            csrf: config.csrf || '',
            // Phạm vi đang xem ở MỖI kiểu luyện: 'all' | 'assigned' | 'managed'.
            problemScope: config.initialProblemScope || 'all',
            examScope: config.initialExamScope || 'all',
            // Lọc theo trạng thái bài được giao: 'all' | 'completed' | 'pending' | 'incomplete'.
            problemAssignStatus: 'all',
            examAssignStatus: 'all',
            // Lọc đề thi theo tỉnh/thành (cuộc thi dùng selectedExamType có sẵn).
            selectedExamProvince: 'all',
            // Popup giao bài.
            assign: { open: false, type: 'problem', id: 0, title: '', code: '', students: [], deadline: '', error: '', saving: false, saved: null },

            // SỬA 1/10 — tab mở đầu tiên do MÁY CHỦ quyết (xem $catalogMode ở
            // partials/practice-catalog), không đọc URL ở đây nữa: quyết ở máy chủ thì HTML trả
            // về đã đúng tab, không phụ thuộc lúc Alpine khởi tạo.
            practiceMode: config.initialMode || 'problems',
            activeTab: 'all',          // 'all' hoặc mã dạng câu (mcq/fill_blank/coding/composite)
            selectedTopic: 'all',      // 'all' hoặc id chuyên đề
            selectedDifficulty: 'all',
            selectedExamType: 'all',
            // SỬA 2/10 — ô sắp xếp đề của bản mẫu mới: 'default' | 'attempts' | 'newest'.
            examSort: 'default',
            // SỬA 8/10 — sắp xếp bảng bài tập theo Chuyên đề / Độ khó / Tỷ lệ AC như PracticeSortButton
            // của source mới: key rỗng = giữ thứ tự máy chủ; bấm lần 1 = tăng (Tỷ lệ AC thì giảm), bấm lại = đảo chiều.
            problemSort: { key: null, direction: 'asc' },
            // SỬA 8/10 — công tắc "Chỉ hiện bài chưa làm".
            onlyUndone: false,
            searchQuery: '',
            problemPageIndex: 1,
            examPageIndex: 1,

            init() {
                this.$watch('searchQuery', () => { this.problemPageIndex = 1; this.examPageIndex = 1; });
                // SỬA 2/10 — đổi cách sắp xếp thì về trang 1, nếu không đang ở trang 3 mà sắp xếp
                // lại là nhìn vào giữa danh sách, tưởng mất đề.
                this.$watch('examSort', () => { this.examPageIndex = 1; });
            },

            changeMode(mode) {
                this.practiceMode = mode;
                this.syncUrl();
                this.searchQuery = '';
                if (mode === 'problems') {
                    this.selectedExamType = 'all';
                } else {
                    this.activeTab = 'all';
                    this.selectedTopic = 'all';
                    this.selectedDifficulty = 'all';
                }
            },

            // ── Phạm vi: Tất cả / Được giao / Đã giao ──
            get scopeTabs() { return this.practiceMode === 'exams' ? this.examScope : this.problemScope; },

            setScope(kind, scope) {
                if (kind === 'exam') {
                    this.examScope = scope;
                    this.examPageIndex = 1;
                    this.practiceMode = 'exams';
                } else {
                    this.problemScope = scope;
                    this.problemPageIndex = 1;
                    this.practiceMode = 'problems';
                }
                this.searchQuery = '';
                this.syncUrl();
            },

            // Giữ ?tab / ?scope trên thanh địa chỉ để tải lại trang (ví dụ sau khi giao bài) vẫn
            // về đúng chỗ đang xem. Lỗi ở đây (trình duyệt cũ, iframe) chỉ làm mất việc ghi nhớ.
            syncUrl() {
                try {
                    const url = new URL(window.location.href);
                    if (this.practiceMode === 'exams') { url.searchParams.set('tab', 'de-thi'); } else { url.searchParams.delete('tab'); }
                    const scope = this.practiceMode === 'exams' ? this.examScope : this.problemScope;
                    if (scope && scope !== 'all') { url.searchParams.set('scope', scope); } else { url.searchParams.delete('scope'); }
                    window.history.replaceState({}, '', url.toString());
                } catch (e) { /* bỏ qua */ }
            },

            // Số lượng theo trạng thái cho dãy chip (chỉ tính hàng được giao).
            assignCounts(list) {
                const counts = { all: 0, completed: 0, pending: 0, incomplete: 0 };
                list.forEach((row) => {
                    if (!row.assigned) { return; }
                    counts.all++;
                    counts[row.assignStatus] = (counts[row.assignStatus] || 0) + 1;
                });
                return counts;
            },
            get problemAssignCounts() { return this.assignCounts(this.problems); },
            get examAssignCounts() { return this.assignCounts(this.exams); },

            // ── Popup giao bài ──
            get assignLabel() { return this.assign.type === 'exam' ? 'Giao đề' : 'Giao bài'; },

            nowLocal() {
                const d = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
                return d.toISOString().slice(0, 16);
            },

            openAssign(type, id, title, code) {
                this.assign = { open: true, type, id, title, code, students: [], deadline: '', error: '', saving: false, saved: null };
            },

            closeAssign() {
                const wasSaved = !!this.assign.saved;
                this.assign.open = false;
                // Giao xong thì tải lại để các danh sách "Đã giao" cập nhật số liệu mới.
                if (wasSaved) { window.location.reload(); }
            },

            async submitAssign() {
                const a = this.assign;
                if (a.saving || a.saved) { return; }

                if (!a.students.length) { a.error = 'Chọn ít nhất 1 học sinh.'; return; }
                if (!a.deadline || !Number.isFinite(new Date(a.deadline).getTime())) { a.error = 'Vui lòng chọn ngày và giờ hạn nộp hợp lệ.'; return; }
                if (new Date(a.deadline).getTime() <= Date.now()) { a.error = 'Hạn nộp phải ở sau thời điểm hiện tại.'; return; }

                a.saving = true;
                a.error = '';

                try {
                    const res = await fetch(this.assignUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ type: a.type, subject_id: a.id, student_ids: a.students.map((s) => s.id), deadline: a.deadline }),
                    });
                    const data = await res.json().catch(() => ({}));

                    if (res.ok && data.ok) {
                        a.saved = data;
                    } else if (res.status === 422 && data.errors) {
                        const first = Object.values(data.errors)[0];
                        a.error = Array.isArray(first) ? first[0] : 'Dữ liệu chưa hợp lệ.';
                    } else if (res.status === 419) {
                        a.error = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.';
                    } else if (res.status === 401 || res.status === 403) {
                        a.error = 'Bạn không có quyền giao nội dung này.';
                    } else {
                        a.error = 'Chưa lưu được lượt giao. Vui lòng thử lại.';
                    }
                } catch (e) {
                    a.error = 'Chưa lưu được lượt giao. Vui lòng kiểm tra kết nối và thử lại.';
                } finally {
                    a.saving = false;
                }
            },

            toggleOnlyUndone() { this.onlyUndone = !this.onlyUndone; this.problemPageIndex = 1; },

            toggleProblemSort(key) {
                if (this.problemSort.key === key) {
                    this.problemSort = { key, direction: this.problemSort.direction === 'asc' ? 'desc' : 'asc' };
                } else {
                    this.problemSort = { key, direction: key === 'acRate' ? 'desc' : 'asc' };
                }
                this.problemPageIndex = 1;
            },

            setTab(v) { this.activeTab = v; this.problemPageIndex = 1; },
            setTopic(v) { this.selectedTopic = v; this.problemPageIndex = 1; },
            setDifficulty(v) { this.selectedDifficulty = v; this.problemPageIndex = 1; },
            setExamType(v) { this.selectedExamType = v; this.examPageIndex = 1; },

            resetProblemFilters() {
                this.problemAssignStatus = 'all';
                this.onlyUndone = false;
                this.activeTab = 'all';
                this.selectedTopic = 'all';
                this.selectedDifficulty = 'all';
                this.searchQuery = '';
                this.problemPageIndex = 1;
            },
            resetExamFilters() {
                this.selectedExamProvince = 'all';
                this.examAssignStatus = 'all';
                this.selectedExamType = 'all';
                this.searchQuery = '';
                this.examPageIndex = 1;
            },

            get filteredProblems() {
                const q = this.searchQuery.trim().toLowerCase();
                const assignedView = this.problemScope === 'assigned';

                const list = this.problems.filter((p) => {
                    // "Tất cả bài tập" chỉ gồm bài thuộc kho; bài chỉ có mặt vì được giao thì chỉ
                    // hiện ở "Bài được giao".
                    const matchScope = assignedView ? p.assigned : p.inCatalog;
                    const matchAssign = !assignedView || this.problemAssignStatus === 'all' || p.assignStatus === this.problemAssignStatus;
                    const matchTab = this.activeTab === 'all' || p.type === this.activeTab;
                    const matchTopic = this.selectedTopic === 'all' || p.tagIds.includes(this.selectedTopic);
                    const matchDiff = this.selectedDifficulty === 'all' || p.difficulty === this.selectedDifficulty;
                    const matchSearch = !q || p.search.includes(q);
                    const matchUndone = !this.onlyUndone || !p.attempted;
                    return matchScope && matchAssign && matchTab && matchTopic && matchDiff && matchSearch && matchUndone;
                });

                // Bài được giao: hạn gần nhất lên trước (bản sao mảng — không sort tại chỗ).
                const base = assignedView
                    ? [...list].sort((a, b) => (a.deadlineTs || 0) - (b.deadlineTs || 0))
                    : list;

                // Sắp xếp theo cột người dùng bấm (cùng cách so sánh với PracticePage.jsx của source mới).
                const key = this.problemSort.key;
                if (!key) { return base; }
                const dir = this.problemSort.direction === 'asc' ? 1 : -1;
                return [...base].sort((a, b) => {
                    let result = 0;
                    if (key === 'topic') { result = String(a.topicLabel).localeCompare(String(b.topicLabel), 'vi'); }
                    if (key === 'year') {
                        // Bài chưa gán năm ("—" → 0) luôn nằm cuối, dù sắp tăng hay giảm.
                        if (!a.year && !b.year) { return String(a.titleText).localeCompare(String(b.titleText), 'vi'); }
                        if (!a.year) { return 1; }
                        if (!b.year) { return -1; }
                        result = a.year - b.year;
                    }
                    if (key === 'difficulty') { result = (a.difficultyLevel || 0) - (b.difficultyLevel || 0); }
                    if (key === 'acRate') { result = (parseFloat(a.acRate) || 0) - (parseFloat(b.acRate) || 0); }
                    return (result * dir) || String(a.titleText).localeCompare(String(b.titleText), 'vi');
                });
            },

            get filteredExams() {
                const q = this.searchQuery.trim().toLowerCase();
                const assignedView = this.examScope === 'assigned';

                const list = this.exams.filter((e) => {
                    const matchScope = assignedView ? e.assigned : e.inCatalog;
                    const matchAssign = !assignedView || this.examAssignStatus === 'all' || e.assignStatus === this.examAssignStatus;
                    const matchType = this.selectedExamType === 'all' || e.type === this.selectedExamType;
                    const matchProvince = this.selectedExamProvince === 'all'
                        || (this.selectedExamProvince === 'unknown' ? !e.province : e.province === this.selectedExamProvince);
                    const matchSearch = !q || e.search.includes(q);
                    return matchScope && matchAssign && matchType && matchProvince && matchSearch;
                });

                if (assignedView) {
                    return [...list].sort((a, b) => (a.deadlineTs || 0) - (b.deadlineTs || 0));
                }

                /*
                 * SỬA 2/10 — sắp xếp theo ô chọn mới. Luôn copy mảng trước khi sort: sort() đổi
                 * TẠI CHỖ, mà this.exams là mảng gốc Alpine đang theo dõi — sắp xếp thẳng lên nó
                 * là đổi luôn thứ tự mặc định, bấm về "Thứ tự mặc định" không còn quay lại được.
                 */
                if (this.examSort === 'attempts') {
                    return [...list].sort((a, b) => (b.attempts - a.attempts) || (a.order - b.order));
                }

                if (this.examSort === 'title') {
                    return [...list].sort((a, b) => a.title.localeCompare(b.title, 'vi'));
                }

                return list;
            },

            get problemTotalPages() { return Math.max(1, Math.ceil(this.filteredProblems.length / this.problemPageSize)); },
            get examPageSizeNow() { return this.examScope === 'assigned' ? 8 : this.examPageSize; },
            get examTotalPages() { return Math.max(1, Math.ceil(this.filteredExams.length / this.examPageSizeNow)); },

            // Trang hiện tại không bao giờ vượt quá tổng số trang sau khi lọc.
            get problemPage() { return Math.min(this.problemPageIndex, this.problemTotalPages); },
            get examPage() { return Math.min(this.examPageIndex, this.examTotalPages); },

            get visibleProblemIds() {
                const start = (this.problemPage - 1) * this.problemPageSize;
                return this.filteredProblems.slice(start, start + this.problemPageSize).map((p) => p.id);
            },
            get visibleExamIds() {
                const start = (this.examPage - 1) * this.examPageSizeNow;
                return this.filteredExams.slice(start, start + this.examPageSizeNow).map((e) => e.id);
            },
        };
    }

    /*
     * SỬA 7/10 — danh sách "Bài đã giao" / "Đề đã giao" của giáo viên + admin
     * (education-main/AssignmentManagement.jsx). Lọc / sắp xếp / phân trang chạy ngay trên trình
     * duyệt vì dữ liệu máy chủ đã trả tối đa 300 lượt giao mới nhất.
     */
    function onthiAssignmentManager(config) {
        return {
            rows: config.rows || [],
            type: config.type,
            isAdmin: !!config.isAdmin,
            query: '',
            status: 'all',
            sort: 'assigned',
            page: 1,
            pageSize: 10,
            expandedId: null,
            states: {
                unsubmitted: { label: 'Chưa nộp', tone: 'neutral' },
                pending: { label: 'Chờ chấm', tone: 'amber' },
                completed: { label: 'Đã hoàn thành', tone: 'green' },
                overdue: { label: 'Quá hạn · Chưa nộp', tone: 'red' },
            },

            init() {
                this.$watch('query', () => { this.page = 1; this.expandedId = null; });
                this.$watch('status', () => { this.page = 1; this.expandedId = null; });
                this.$watch('sort', () => { this.page = 1; this.expandedId = null; });
            },

            get counts() {
                const c = { completed: 0, pending: 0, unsubmitted: 0, overdue: 0 };
                this.rows.forEach((r) => { c[r.status] = (c[r.status] || 0) + 1; });
                return c;
            },
            get submittedCount() { return this.counts.completed + this.counts.pending; },
            get submittedPercent() { return this.rows.length ? Math.round(this.submittedCount / this.rows.length * 100) : 0; },
            get studentCount() { return new Set(this.rows.map((r) => r.account)).size; },
            get subjectCount() { return new Set(this.rows.map((r) => r.subjectId)).size; },

            get filtered() {
                const q = this.query.trim().toLocaleLowerCase('vi');
                const list = this.rows.filter((r) => {
                    const hay = (r.title + ' ' + r.code + ' ' + r.account + ' ' + r.studentName + ' ' + r.teacher).toLocaleLowerCase('vi');
                    const okStatus = this.status === 'all' || (this.status === 'late' ? r.late : r.status === this.status);
                    return okStatus && (!q || hay.includes(q));
                });

                return [...list].sort((a, b) => {
                    if (this.sort === 'deadline') { return a.deadlineTs - b.deadlineTs; }
                    if (this.sort === 'score') { return (b.scoreRatio ?? -1) - (a.scoreRatio ?? -1); }
                    if (this.sort === 'submitted') { return (b.submittedTs || 0) - (a.submittedTs || 0); }
                    return b.assignedTs - a.assignedTs;
                });
            },

            get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.pageSize)); },
            get currentPage() { return Math.min(this.page, this.totalPages); },
            get visible() {
                const start = (this.currentPage - 1) * this.pageSize;
                return this.filtered.slice(start, start + this.pageSize);
            },

            toggle(id) { this.expandedId = this.expandedId === id ? null : id; },
            goTo(n) { this.page = Math.min(Math.max(1, n), this.totalPages); this.expandedId = null; },
            clearFilters() { this.query = ''; this.status = 'all'; },
        };
    }
</script>
