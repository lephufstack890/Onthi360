<script>
    function onthiPracticePage(config) {
        /*
         * SỬA 16/10 (khách: "trang luyện tập chậm, sắp tới hơn 1000 đề + 1000 câu") — 3 chỗ tối ưu:
         *  1. Danh sách bài/đề được ĐÓNG BĂNG (Object.freeze): trang chỉ đọc, không sửa phần tử nào, nên Alpine khỏi
         *     bọc Proxy theo dõi từng ô của hàng nghìn dòng.
         *  2. Kết quả lọc + sắp xếp được NHỚ LẠI (memo) theo đúng bộ lọc đang chọn: trước đây MỖI dòng tự gọi lại
         *     filteredProblems (lọc + sắp xếp cả danh sách) 3 lần để biết mình có hiện không — 1000 dòng là 3000 lần lọc
         *     mỗi lần bấm. Giờ lọc đúng 1 lần cho mỗi lần đổi bộ lọc / trang.
         *  3. problemPos / examPos: bảng {id: vị trí trong trang} — mỗi dòng tra 1 phát (O(1)) thay vì includes()/indexOf().
         */
        const freezeAll = (list) => Object.freeze((list || []).map((row) => Object.freeze(row)));
        const memo = {};

        return {
            problems: freezeAll(config.problems),
            exams: freezeAll(config.exams),
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
            // SỬA 12/10 — ô lọc "Khối": 'all' | 'thcs' | 'thpt' | 'khac'.
            selectedExamLevel: 'all',
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
                // SỬA 11/10 (khách: "làm bài xong bấm thoát phải về lại đúng trang đang xem") — khôi phục số trang +
                // bộ lọc từ URL TRƯỚC khi gắn các $watch bên dưới (gắn sau thì việc gán ở đây không bị $watch
                // đẩy về trang 1). Trang Luyện tập ghi lại các tham số này ở syncUrl(), và gửi kèm khi bấm "Làm bài".
                this.restoreFromUrl();

                this.$watch('searchQuery', () => { this.problemPageIndex = 1; this.examPageIndex = 1; });
                // SỬA 2/10 — đổi cách sắp xếp thì về trang 1, nếu không đang ở trang 3 mà sắp xếp
                // lại là nhìn vào giữa danh sách, tưởng mất đề.
                this.$watch('examSort', () => { this.examPageIndex = 1; });

                // Mọi thay đổi trang/bộ lọc đều ghi lên URL (replaceState — không thêm mục vào lịch sử).
                ['problemPageIndex', 'examPageIndex', 'activeTab', 'selectedTopic', 'selectedDifficulty', 'selectedExamType', 'selectedExamLevel', 'onlyUndone', 'searchQuery', 'selectedExamProvince', 'examSort']
                    .forEach((key) => this.$watch(key, () => this.syncUrl()));
                // SỬA 29/10 — nhớ ngay địa chỉ đang xem (để nút Quay lại ở trang đề / lịch sử nộp về đúng chỗ).
                this.syncUrl();
            },

            // Đọc lại trạng thái trang từ URL (số trang, bộ lọc). Giá trị lạ/hỏng thì bỏ qua; số trang vượt quá thì
            // problemPage/examPage tự kẹp về trang cuối.
            restoreFromUrl() {
                try {
                    const q = new URL(window.location.href).searchParams;
                    const num = (k) => { const n = parseInt(q.get(k) || '', 10); return Number.isFinite(n) && n >= 1 ? n : null; };

                    if (this.practiceMode === 'exams') {
                        const t = q.get('etype'); if (t) { this.selectedExamType = t; }
                        const lv = q.get('elevel'); if (lv) { this.selectedExamLevel = lv; }
                        // SỬA 29/10 — giữ cả tỉnh/thành + cách sắp xếp đề.
                        const pv = q.get('eprov'); if (pv) { this.selectedExamProvince = pv; }
                        const so = q.get('esort'); if (so) { this.examSort = so; }
                        const p = num('pg'); if (p) { this.examPageIndex = p; }
                    } else {
                        const t = q.get('type'); if (t) { this.activeTab = t; }
                        const tp = q.get('topic'); if (tp) { this.selectedTopic = tp; }
                        const df = q.get('diff'); if (df) { this.selectedDifficulty = df; }
                        if (q.get('undone') === '1') { this.onlyUndone = true; }
                        const p = num('pg'); if (p) { this.problemPageIndex = p; }
                    }
                    const s = q.get('q'); if (s) { this.searchQuery = s; }
                } catch (e) { /* bỏ qua */ }
            },

            // Địa chỉ trang đang xem (đường dẫn + query) để gửi kèm khi bấm "Làm bài".
            returnTarget() {
                this.syncUrl();
                return window.location.pathname + window.location.search;
            },

            // Gắn ?return_url vào liên kết "Làm bài" ngay lúc bấm (liên kết do máy chủ dựng sẵn).
            withReturn(event) {
                try {
                    const a = event.currentTarget;
                    const url = new URL(a.href, window.location.origin);
                    if (url.origin !== window.location.origin) { return; }
                    url.searchParams.set('return_url', this.returnTarget());
                    a.href = url.pathname + url.search;
                } catch (e) { /* bỏ qua — vẫn mở bài như cũ */ }
            },

            changeMode(mode) {
                this.practiceMode = mode;
                this.syncUrl();
                this.searchQuery = '';
                if (mode === 'problems') {
                    this.selectedExamType = 'all';
                    this.selectedExamLevel = 'all';
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

                    // SỬA 11/10 — số trang + bộ lọc đang xem. Giá trị mặc định thì bỏ khỏi URL cho gọn.
                    const put = (k, v, def) => { if (v !== undefined && v !== null && String(v) !== '' && String(v) !== String(def)) { url.searchParams.set(k, v); } else { url.searchParams.delete(k); } };
                    const exams = this.practiceMode === 'exams';
                    put('pg', exams ? this.examPage : this.problemPage, 1);
                    put('type', exams ? '' : this.activeTab, 'all');
                    put('topic', exams ? '' : this.selectedTopic, 'all');
                    put('diff', exams ? '' : this.selectedDifficulty, 'all');
                    put('undone', !exams && this.onlyUndone ? '1' : '', '');
                    put('etype', exams ? this.selectedExamType : '', 'all');
                    put('elevel', exams ? this.selectedExamLevel : '', 'all');
                    put('eprov', exams ? this.selectedExamProvince : '', 'all');
                    put('esort', exams ? this.examSort : '', 'default');
                    put('q', this.searchQuery.trim(), '');
                    window.history.replaceState({}, '', url.toString());
                    // SỬA 29/10 — nhớ địa chỉ (đủ lọc + trang) cho nút Quay lại ở trang đề / lịch sử nộp (partials/list-memo-script).
                    try { window.sessionStorage.setItem('oi.list.practice', url.pathname + url.search); } catch (e) { /* bỏ qua */ }
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
                this.selectedExamLevel = 'all';
                this.searchQuery = '';
                this.examPageIndex = 1;
            },

            get filteredProblems() {
                const q = this.searchQuery.trim().toLowerCase();
                const assignedView = this.problemScope === 'assigned';
                const memoKey = [this.problemScope, this.problemAssignStatus, this.activeTab, this.selectedTopic, this.selectedDifficulty,
                    q, this.onlyUndone, this.problemSort.key, this.problemSort.direction].join('\u0001');
                if (memo.problemKey === memoKey) { return memo.problemList; }
                memo.problemKey = memoKey;
                memo.problemList = this.computeFilteredProblems(q, assignedView);
                return memo.problemList;
            },

            computeFilteredProblems(q, assignedView) {

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
                const memoKey = [this.examScope, this.examAssignStatus, this.selectedExamType, this.selectedExamLevel,
                    this.selectedExamProvince, q, this.examSort].join('\u0001');
                if (memo.examKey === memoKey) { return memo.examList; }
                memo.examKey = memoKey;
                memo.examList = this.computeFilteredExams(q, assignedView);
                return memo.examList;
            },

            computeFilteredExams(q, assignedView) {

                const list = this.exams.filter((e) => {
                    const matchScope = assignedView ? e.assigned : e.inCatalog;
                    const matchAssign = !assignedView || this.examAssignStatus === 'all' || e.assignStatus === this.examAssignStatus;
                    // SỬA 12/10 — lọc theo NHÓM cố định (mã loại cũ đã gom về 4 mục) và theo khối.
                    const matchType = this.selectedExamType === 'all' || e.catGroup === this.selectedExamType;
                    const matchLevel = this.selectedExamLevel === 'all' || e.level === this.selectedExamLevel;
                    const matchProvince = this.selectedExamProvince === 'all'
                        || (this.selectedExamProvince === 'unknown' ? !e.province : e.province === this.selectedExamProvince);
                    const matchSearch = !q || e.search.includes(q);
                    return matchScope && matchAssign && matchType && matchLevel && matchProvince && matchSearch;
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

            // Dãy số trang rút gọn, vd 8 trang: 1 2 3 4 5 6 … 8. Đứng gần đầu thì hiện 6 trang đầu,
            // gần cuối thì hiện 6 trang cuối (1 … 3 4 5 6 7 8), ở giữa thì 1 … 4 5 6 … 8. Tối đa 7 trang thì hiện hết.
            pageItems(current, total) {
                const items = [];
                const add = (n) => items.push({ key: 'n' + n, n, gap: false });
                const gap = (k) => items.push({ key: k, n: 0, gap: true });
                if (total <= 7) { for (let i = 1; i <= total; i++) add(i); return items; }
                if (current <= 4) {
                    for (let i = 1; i <= 6; i++) add(i);
                    gap('gr'); add(total);
                } else if (current >= total - 3) {
                    add(1); gap('gl');
                    for (let i = total - 5; i <= total; i++) add(i);
                } else {
                    add(1); gap('gl');
                    for (let i = current - 1; i <= current + 1; i++) add(i);
                    gap('gr'); add(total);
                }
                return items;
            },
            get problemPageItems() { return this.pageItems(this.problemPage, this.problemTotalPages); },
            get examPageItems() { return this.pageItems(this.examPage, this.examTotalPages); },

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
            // SỬA 16/10 — {id: vị trí trong trang đang xem}; dòng không có trong bảng = không hiện.
            get problemPos() {
                const list = this.filteredProblems;
                const page = this.problemPage;
                const size = this.problemPageSize;
                const key = memo.problemKey + '|' + page + '|' + size;
                if (memo.problemPosKey !== key) {
                    const pos = {};
                    list.slice((page - 1) * size, page * size).forEach((row, i) => { pos[row.id] = i; });
                    memo.problemPosKey = key;
                    memo.problemPos = pos;
                }
                return memo.problemPos;
            },
            get examPos() {
                const list = this.filteredExams;
                const page = this.examPage;
                const size = this.examPageSizeNow;
                const key = memo.examKey + '|' + page + '|' + size;
                if (memo.examPosKey !== key) {
                    const pos = {};
                    list.slice((page - 1) * size, page * size).forEach((row, i) => { pos[row.id] = i; });
                    memo.examPosKey = key;
                    memo.examPos = pos;
                }
                return memo.examPos;
            },
        };
    }

    /*
     * SỬA 7/10 — danh sách "Bài đã giao" / "Đề đã giao" của giáo viên + admin
     * (education-main/AssignmentManagement.jsx). Lọc / sắp xếp / phân trang chạy ngay trên trình
     * duyệt vì dữ liệu máy chủ đã trả tối đa 300 lượt giao mới nhất.
     */
    function onthiAssignmentManager(config) {
        // Bộ nhớ đệm cho bản xem trước in: getter của Alpine không tự nhớ kết quả, mỗi x-text gọi
        // lại một lần. Để ngoài đối tượng trả về (không phản ứng) nên ghi vào không gây vẽ lại.
        let reportCache = { key: null, rows: null, data: null };
        let reportOpener = null;
        return {
            rows: config.rows || [],
            type: config.type,
            isAdmin: !!config.isAdmin,
            title: config.title || 'Bài đã giao',
            actor: config.actor || 'Người dùng',
            // Popup "Xem trước & in kết quả" — bộ lọc riêng, chép từ danh sách lúc mở.
            report: { open: false, query: '', status: 'all', sort: 'assigned:desc', dateField: 'assigned', from: '', to: '', busy: false, message: '', createdAt: 0 },
            reportSortLabels: { title: 'Tên bài / đề', student: 'Học sinh', status: 'Trạng thái', score: 'Điểm gần nhất', submitted: 'Thời gian nộp', deadline: 'Hạn nộp', assigned: 'Thời gian giao' },
            reportDateFields: { assigned: 'Ngày giao', submitted: 'Ngày nộp gần nhất', deadline: 'Hạn nộp' },
            query: '',
            status: 'all',
            sort: 'assigned',
            sortKey: 'assigned',
            sortDir: 'desc',
            page: 1,
            pageSize: 10,
            expandedId: null,
            presets: { assigned: ['assigned', 'desc'], deadline: ['deadline', 'asc'], submitted: ['submitted', 'desc'], score: ['score', 'desc'] },
            states: {
                unsubmitted: { label: 'Chưa nộp', tone: 'neutral' },
                pending: { label: 'Chờ chấm', tone: 'amber' },
                completed: { label: 'Đã hoàn thành', tone: 'green' },
                overdue: { label: 'Quá hạn · Chưa nộp', tone: 'red' },
            },

            init() {
                this.$watch('query', () => { this.page = 1; this.expandedId = null; });
                this.$watch('status', () => { this.page = 1; this.expandedId = null; });
                this.$watch('sort', (v) => {
                    if (this.presets[v]) { this.sortKey = this.presets[v][0]; this.sortDir = this.presets[v][1]; }
                    this.page = 1; this.expandedId = null;
                });
            },

            /* Bấm tiêu đề cột: lần đầu sắp xếp theo cột đó, bấm lại thì đảo chiều. */
            toggleSort(key) {
                if (this.sortKey === key) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortKey = key;
                    this.sortDir = (key === 'score' || key === 'submitted') ? 'desc' : 'asc';
                }
                const match = Object.keys(this.presets).find((k) => this.presets[k][0] === this.sortKey && this.presets[k][1] === this.sortDir);
                this.sort = match || 'custom';
                this.page = 1; this.expandedId = null;
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

            /* Lọc dùng chung cho danh sách và bản in. Lọc theo ngày: so theo NGÀY giờ Việt Nam, tính cả
               hai đầu; ô trống ngày thì bỏ qua; lượt chưa có mốc đó (vd chưa nộp) thì loại khi có lọc ngày. */
            filterRows(list, o) {
                if (o.from && o.to && o.from > o.to) { return []; }
                const q = (o.query || '').trim().toLocaleLowerCase('vi');
                const field = { assigned: 'assignedTs', submitted: 'submittedTs', deadline: 'deadlineTs' }[o.dateField || 'assigned'] || 'assignedTs';
                const status = o.status || 'all';
                return list.filter((r) => {
                    const hay = (r.title + ' ' + r.code + ' ' + r.account + ' ' + r.studentName + ' ' + r.teacher).toLocaleLowerCase('vi');
                    const okStatus = status === 'all' || (status === 'late' ? r.late : r.status === status);
                    if (!okStatus || (q && !hay.includes(q))) { return false; }
                    if (!o.from && !o.to) { return true; }
                    const day = onthiVnDay(r[field]);
                    return day !== '' && (!o.from || day >= o.from) && (!o.to || day <= o.to);
                });
            },

            sortRows(list, key, direction) {
                const dir = direction === 'asc' ? 1 : -1;
                const statusOrder = { overdue: 0, unsubmitted: 1, pending: 2, completed: 3 };
                /* Ô trống (chưa có điểm / chưa nộp) luôn nằm cuối, dù sắp tăng hay giảm. */
                const num = (x, y, zeroEmpty) => {
                    const ex = x === null || x === undefined || (zeroEmpty && x === 0), ey = y === null || y === undefined || (zeroEmpty && y === 0);
                    if (ex || ey) { return ex && ey ? 0 : (ex ? 1 : -1); }
                    return (x - y) * dir;
                };
                return [...list].sort((a, b) => {
                    let r = 0;
                    switch (key) {
                        case 'title': r = (a.title + ' ' + a.studentName).localeCompare(b.title + ' ' + b.studentName, 'vi') * dir; break;
                        case 'student': r = (a.studentName + ' ' + a.account).localeCompare(b.studentName + ' ' + b.account, 'vi') * dir; break;
                        case 'status': r = ((statusOrder[a.status] ?? 9) - (statusOrder[b.status] ?? 9)) * dir; break;
                        case 'score': r = num(a.scoreRatio, b.scoreRatio); break;
                        case 'submitted': r = num(a.submittedTs, b.submittedTs, true); break;
                        case 'deadline': r = num(a.deadlineTs, b.deadlineTs, true); break;
                        default: r = (a.assignedTs - b.assignedTs) * dir;
                    }
                    return r || (b.assignedTs - a.assignedTs);
                });
            },

            get filtered() {
                return this.sortRows(this.filterRows(this.rows, { query: this.query, status: this.status }), this.sortKey, this.sortDir);
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

            // ───── Popup "Xem trước & in kết quả" (AssignmentReportDialog.jsx) ─────
            openReport() {
                reportOpener = document.activeElement;
                Object.assign(this.report, {
                    query: this.query, status: this.status, sort: this.sortKey + ':' + this.sortDir,
                    dateField: 'assigned', from: '', to: '', busy: false, message: '', createdAt: Date.now(), open: true,
                });
                this.$nextTick(() => { const d = this.$refs.reportDialog; if (d && !d.open) { d.showModal(); } });
            },
            closeReport() {
                if (this.report.busy) { return; }
                const d = this.$refs.reportDialog;
                if (d && d.open) { d.close(); }
                this.report.open = false;
                if (reportOpener && typeof reportOpener.focus === 'function') { reportOpener.focus(); }
            },
            resetReportFilters() {
                Object.assign(this.report, { query: '', status: 'all', dateField: 'assigned', from: '', to: '', message: '' });
            },
            get reportInvalidDates() { return !!(this.report.from && this.report.to && this.report.from > this.report.to); },
            get reportMatching() {
                const r = this.report;
                const key = [this.rows.length, r.query, r.status, r.sort, r.dateField, r.from, r.to].join('\u0001');
                if (reportCache.key !== key) {
                    const [sortKey, sortDir] = r.sort.split(':');
                    reportCache = { key, rows: this.sortRows(this.filterRows(this.rows, r), sortKey, sortDir), data: null };
                }
                return reportCache.rows;
            },
            get reportStudentCount() { return new Set(this.reportMatching.map((row) => row.account)).size; },
            get reportCannotExport() { return this.report.busy || this.reportInvalidDates || this.reportMatching.length === 0; },
            reportFilterLabel() {
                const r = this.report;
                const day = (v) => v.split('-').reverse().join('/');
                const [sortKey, sortDir] = r.sort.split(':');
                return [
                    r.query.trim() && ('Tìm kiếm: ' + r.query.trim()),
                    r.status !== 'all' && ('Trạng thái: ' + (r.status === 'late' ? 'Nộp muộn' : this.states[r.status].label)),
                    (r.from || r.to) && (this.reportDateFields[r.dateField] + ': ' + (r.from ? day(r.from) : 'Không giới hạn') + ' – ' + (r.to ? day(r.to) : 'Không giới hạn') + ' (giờ Việt Nam)'),
                    'Sắp xếp: ' + (this.reportSortLabels[sortKey] || '') + ' ' + (sortDir === 'asc' ? 'tăng dần' : 'giảm dần') + (sortKey === 'score' ? ' (theo tỷ lệ điểm)' : ''),
                ].filter(Boolean).join(' · ');
            },
            get reportData() {
                const rows = this.reportMatching;
                const createdAt = this.report.createdAt;
                if (reportCache.data && reportCache.data.createdAt === createdAt) { return reportCache.data; }
                const clean = (v) => (v && v !== '—') ? v : '';
                reportCache.data = {
                    createdAt,
                    title: 'Kết quả học sinh · ' + this.title,
                    created: 'Người xuất: ' + this.actor + ' · ' + onthiVnStamp(createdAt),
                    filters: this.reportFilterLabel(),
                    summary: rows.length + ' lượt giao · ' + new Set(rows.map((r) => r.account)).size + ' học sinh · ' + rows.filter((r) => r.submittedAt).length + ' đã nộp',
                    note: 'Mỗi dòng là một lượt giao. Điểm và thời gian nộp lấy từ lần nộp gần nhất sau lúc giao. Tối đa 300 lượt giao mới nhất.',
                    headers: ['STT', 'Học sinh / Tài khoản', this.type === 'exam' ? 'Đề' : 'Bài', 'Trạng thái', 'Điểm', 'Nộp gần nhất', 'Giao / Hạn nộp'],
                    rows: rows.map((r, i) => [
                        String(i + 1),
                        [clean(r.studentName), clean(r.account)].filter(Boolean).join('\n') || '—',
                        r.title + '\n' + r.code,
                        (this.states[r.status] ? this.states[r.status].label : r.status) + (r.late ? '\nNộp muộn' : ''),
                        r.scoreLabel ? (r.scoreLabel + (r.scoreAfterLabel ? '\nSau trừ: ' + r.scoreAfterLabel : '')) : (r.status === 'pending' ? 'Chờ chấm' : '—'),
                        r.submittedAt || 'Chưa nộp',
                        'Giao: ' + (r.assignedAt || '—') + '\nHạn: ' + (r.deadline || '—'),
                    ]),
                };
                return reportCache.data;
            },
            async runReport(kind) {
                if (this.reportCannotExport) { return; }
                const data = this.reportData;
                this.report.busy = true; this.report.message = '';
                try {
                    if (kind === 'print') {
                        await onthiPrintReport(data);
                        this.report.message = 'Trong hộp thoại in, chọn Lưu dưới dạng PDF để lưu tệp.';
                    } else {
                        const pages = await onthiExportReportImages(data);
                        this.report.message = pages > 1 ? 'Đã tạo ảnh. Báo cáo ' + pages + ' trang được tải trong một tệp ZIP chứa các ảnh PNG.' : 'Đã tạo ảnh PNG.';
                    }
                } catch (error) {
                    this.report.message = (error && error.message) || 'Không xuất được kết quả. Vui lòng thử lại.';
                } finally {
                    this.report.busy = false;
                    // Khung in gọi focus() vào iframe; trả tiêu điểm về popup để phím Esc vẫn đóng được.
                    this.$nextTick(() => { const d = this.$refs.reportDialog; if (d && d.open) { d.focus(); } });
                }
            },
        };
    }

    /* ───── Xuất bản in / ảnh cho popup "Xem trước & in kết quả" ─────
       Chép từ education-main/src/utils/assignmentReportExport.js. Khác bản mẫu: tự ghép tệp ZIP
       (kiểu "store", không nén) thay cho thư viện fflate vì máy chủ không chạy được vite. */
    function onthiVnDay(ts) {
        if (!ts) { return ''; }
        return new Date(ts * 1000 + 7 * 3600000).toISOString().slice(0, 10);
    }
    function onthiVnStamp(ms) {
        const d = new Date((ms || Date.now()) + 7 * 3600000);
        const p = (n) => String(n).padStart(2, '0');
        return p(d.getUTCDate()) + '/' + p(d.getUTCMonth() + 1) + '/' + d.getUTCFullYear() + ' ' + p(d.getUTCHours()) + ':' + p(d.getUTCMinutes());
    }
    function onthiEscapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function onthiReportHtml(report) {
        const e = onthiEscapeHtml;
        return '<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>' + e(report.title) + '</title><style>'
            + '@page{size:A4 landscape;margin:12mm}body{font:11px/1.5 Arial,sans-serif;color:#183d5e;margin:0}h1{font-size:22px;margin:0 0 8px}p{margin:6px 0;overflow-wrap:anywhere}'
            + 'table{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:16px}th,td{border:1px solid #cedce5;padding:7px;text-align:left;white-space:pre-wrap;overflow-wrap:anywhere;vertical-align:top}'
            + 'th{background:#edf4f8}th:first-child{width:30px}th:nth-child(3){width:22%}th:nth-child(5){width:7%}thead{display:table-header-group}tr{break-inside:avoid}footer{margin-top:12px;color:#52687b}'
            + '</style></head><body><h1>' + e(report.title) + '</h1><p>' + e(report.created) + '</p><p>' + e(report.filters) + '</p><p><strong>' + e(report.summary) + '</strong></p>'
            + '<table><thead><tr>' + report.headers.map((c) => '<th>' + e(c) + '</th>').join('') + '</tr></thead><tbody>'
            + report.rows.map((row) => '<tr>' + row.map((c) => '<td>' + e(c) + '</td>').join('') + '</tr>').join('')
            + '</tbody></table><footer>' + e(report.note) + '</footer></body></html>';
    }
    async function onthiPrintReport(report) {
        const frame = document.createElement('iframe');
        frame.title = 'Bản in kết quả học sinh';
        frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1120px;height:800px;border:0';
        try {
            await new Promise((resolve, reject) => {
                const timeout = setTimeout(() => reject(new Error('Không tải được bản in. Vui lòng thử lại.')), 15000);
                frame.onload = () => { clearTimeout(timeout); resolve(); };
                frame.srcdoc = onthiReportHtml(report);
                document.body.appendChild(frame);
            });
            if (frame.contentDocument && frame.contentDocument.fonts) { await frame.contentDocument.fonts.ready; }
            frame.contentWindow.focus();
            frame.contentWindow.print();
            // Giữ khung in lại trong lúc trình duyệt còn hiện hộp thoại xem trước bản in.
            setTimeout(() => frame.remove(), 120000);
        } catch (error) { frame.remove(); throw error; }
    }
    function onthiDownloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 30000);
    }
    function onthiWrapText(ctx, text, width) {
        return String(text).split('\n').flatMap((paragraph) => {
            const lines = [];
            let line = '';
            for (const word of paragraph.split(/\s+/)) {
                if (line && ctx.measureText(line + ' ' + word).width > width) { lines.push(line); line = ''; }
                for (const ch of (line ? ' ' : '') + word) {
                    if (ctx.measureText(line + ch).width > width) { lines.push(line); line = ''; }
                    line += ch;
                }
            }
            lines.push(line);
            return lines;
        });
    }
    function onthiCrc32(bytes) {
        let table = onthiCrc32.table;
        if (!table) {
            table = onthiCrc32.table = new Uint32Array(256);
            for (let n = 0; n < 256; n++) {
                let c = n;
                for (let k = 0; k < 8; k++) { c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); }
                table[n] = c >>> 0;
            }
        }
        let crc = 0xFFFFFFFF;
        for (let i = 0; i < bytes.length; i++) { crc = table[(crc ^ bytes[i]) & 0xFF] ^ (crc >>> 8); }
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }
    /* ZIP tối giản, không nén (ảnh PNG vốn đã nén). files: { 'ten.png': Uint8Array }. */
    function onthiZipStore(files) {
        const enc = new TextEncoder();
        const parts = [], central = [];
        let offset = 0, count = 0;
        for (const [name, data] of Object.entries(files)) {
            const nameBytes = enc.encode(name);
            const crc = onthiCrc32(data);
            const local = new DataView(new ArrayBuffer(30));
            local.setUint32(0, 0x04034b50, true); local.setUint16(4, 20, true); local.setUint16(6, 0x0800, true);
            local.setUint16(8, 0, true); local.setUint16(10, 0, true); local.setUint16(12, 0x21, true);
            local.setUint32(14, crc, true); local.setUint32(18, data.length, true); local.setUint32(22, data.length, true);
            local.setUint16(26, nameBytes.length, true); local.setUint16(28, 0, true);
            parts.push(new Uint8Array(local.buffer), nameBytes, data);
            const cen = new DataView(new ArrayBuffer(46));
            cen.setUint32(0, 0x02014b50, true); cen.setUint16(4, 20, true); cen.setUint16(6, 20, true); cen.setUint16(8, 0x0800, true);
            cen.setUint16(10, 0, true); cen.setUint16(12, 0, true); cen.setUint16(14, 0x21, true);
            cen.setUint32(16, crc, true); cen.setUint32(20, data.length, true); cen.setUint32(24, data.length, true);
            cen.setUint16(28, nameBytes.length, true); cen.setUint32(42, offset, true);
            central.push(new Uint8Array(cen.buffer), nameBytes);
            offset += 30 + nameBytes.length + data.length;
            count++;
        }
        const centralSize = central.reduce((sum, part) => sum + part.length, 0);
        const end = new DataView(new ArrayBuffer(22));
        end.setUint32(0, 0x06054b50, true); end.setUint16(8, count, true); end.setUint16(10, count, true);
        end.setUint32(12, centralSize, true); end.setUint32(16, offset, true);
        return new Blob([...parts, ...central, new Uint8Array(end.buffer)], { type: 'application/zip' });
    }
    /* Vẽ bảng kết quả lên canvas, tối đa 15 dòng / trang; 1 trang tải PNG, nhiều trang gói ZIP.
       Trả về số trang đã tạo. */
    async function onthiExportReportImages(report) {
        if (document.fonts) { await document.fonts.ready; }
        const width = 1600, margin = 48, lineHeight = 26;
        const columns = [64, 240, 344, 208, 128, 232, 288];
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) { throw new Error('Trình duyệt không hỗ trợ tạo ảnh. Hãy dùng In / Lưu PDF.'); }
        ctx.font = '18px Arial';
        const metadata = [report.created, report.filters, report.summary].flatMap((t) => onthiWrapText(ctx, t, width - margin * 2));
        const footer = onthiWrapText(ctx, report.note, width - margin * 2);
        const prepared = report.rows.map((row) => {
            const cells = row.map((cell, i) => onthiWrapText(ctx, cell, columns[i] - 24));
            return { cells, height: Math.max(...cells.map((l) => l.length)) * lineHeight + 28 };
        });
        const pages = [];
        let rows = [], height = 0;
        for (const row of prepared) {
            if (rows.length && (height + row.height > 1800 || rows.length === 15)) { pages.push(rows); rows = []; height = 0; }
            rows.push(row); height += row.height;
        }
        if (rows.length) { pages.push(rows); }
        const files = {};
        for (const [index, page] of pages.entries()) {
            canvas.width = width;
            canvas.height = 210 + metadata.length * lineHeight + footer.length * lineHeight + page.reduce((s, r) => s + r.height, 0);
            ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#123b68'; ctx.font = 'bold 30px Arial';
            ctx.fillText(report.title, margin, 54);
            ctx.font = '18px Arial';
            let y = 92;
            for (const line of metadata) { ctx.fillText(line, margin, y); y += lineHeight; }
            y += 18;
            ctx.fillStyle = '#edf4f8'; ctx.fillRect(margin, y, width - margin * 2, 52);
            ctx.fillStyle = '#183d5e'; ctx.font = 'bold 18px Arial';
            let x = margin;
            report.headers.forEach((label, col) => { ctx.fillText(label, x + 12, y + 32); x += columns[col]; });
            y += 52;
            ctx.font = '18px Arial';
            page.forEach((row, rowIndex) => {
                ctx.fillStyle = rowIndex % 2 ? '#f6f9fb' : '#ffffff';
                ctx.fillRect(margin, y, width - margin * 2, row.height);
                x = margin;
                row.cells.forEach((lines, col) => {
                    ctx.strokeStyle = '#cedce5'; ctx.strokeRect(x, y, columns[col], row.height);
                    ctx.fillStyle = '#183d5e';
                    lines.forEach((line, i) => ctx.fillText(line, x + 12, y + 28 + i * lineHeight));
                    x += columns[col];
                });
                y += row.height;
            });
            y += 30; ctx.fillStyle = '#52687b';
            for (const line of footer) { ctx.fillText(line, margin, y); y += lineHeight; }
            ctx.fillText('Trang ' + (index + 1) + '/' + pages.length, margin, y + 8);
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
            if (!blob) { throw new Error('Không tạo được ảnh. Hãy dùng In / Lưu PDF.'); }
            files['ket-qua-' + String(index + 1).padStart(2, '0') + '.png'] = new Uint8Array(await blob.arrayBuffer());
        }
        if (pages.length === 1) {
            onthiDownloadBlob(new Blob([Object.values(files)[0]], { type: 'image/png' }), 'ket-qua-hoc-sinh.png');
        } else {
            onthiDownloadBlob(onthiZipStore(files), 'ket-qua-hoc-sinh-anh.zip');
        }
        return pages.length;
    }
</script>
