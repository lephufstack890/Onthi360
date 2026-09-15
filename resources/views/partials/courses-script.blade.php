<script>
    function onthiCoursesPage(config) {
        return {
            rows: config.rows,
            pageSize: config.pageSize,
            selectedCategory: config.subject || 'all',
            selectedGrade: 'all',
            searchQuery: '',
            currentPage: 1,

            /* B7 — lọc theo lộ trình.
               Mỗi lộ trình mang sẵn danh sách id khoá học của nó, nên việc lọc chỉ là kiểm tra
               id khoá có nằm trong danh sách đó không — không phải gọi máy chủ lần nào. */
            learningPaths: config.learningPaths || [],
            // Chọn sẵn theo ?lo-trinh= trên đường dẫn, để link chia sẻ mở ra đúng danh sách đã lọc.
            selectedPath: config.learningPath || 'all',

            init() {
                this.$watch('searchQuery', () => { this.currentPage = 1; });
            },

            get filtered() {
                const q = this.searchQuery.trim().toLowerCase();
                const pathCourseIds = this.selectedPathCourseIds;
                return this.rows.filter((r) => {
                    const matchCat = this.selectedCategory === 'all' || r.subject === this.selectedCategory;
                    const matchGrade = this.selectedGrade === 'all' || r.grade === this.selectedGrade;
                    const matchSearch = !q || r.search.includes(q);
                    const matchPath = pathCourseIds === null || pathCourseIds.indexOf(r.id) !== -1;
                    return matchCat && matchGrade && matchSearch && matchPath;
                });
            },

            get totalPages() {
                return Math.max(1, Math.ceil(this.filtered.length / this.pageSize));
            },

            // Trang hiện tại không bao giờ vượt quá tổng số trang sau khi lọc.
            get page() {
                return Math.min(this.currentPage, this.totalPages);
            },

            get visibleIds() {
                const start = (this.page - 1) * this.pageSize;
                return this.filtered.slice(start, start + this.pageSize).map((r) => r.id);
            },

            /* ═══ SỬA 15/9 · Hai bước chọn: KHỐI LỚP -> LỘ TRÌNH ═══
               Khách chốt luồng: chọn khối lớp thì trang hiện các LỘ TRÌNH của khối đó (không
               phải danh sách khoá), bấm vào một lộ trình là sang trang chi tiết lộ trình.

               Vì sao không bỏ hẳn danh sách khoá: người đi ngược chiều từ trang lộ trình sang
               (đường dẫn ?lo-trinh=) cần thấy đúng các khoá của lộ trình đó; và khối lớp nào
               chưa có lộ trình nào thì vẫn phải có gì đó để xem, không được dẫn vào ngõ cụt. */

            /** Số lớp lấy từ nhãn khối ("Lớp 6" -> 6). Nhãn là chuỗi, lộ trình lưu số. */
            get selectedGradeNumber() {
                if (this.selectedGrade === 'all') return null;
                const m = String(this.selectedGrade).match(/\d+/);
                return m ? Number(m[0]) : null;
            },

            /* ═══ SỬA 15/9 · Trang này chỉ đổ LỘ TRÌNH ═══
               Khách chốt: trang Lớp học không liệt kê khoá học nữa, chỉ liệt kê lộ trình; chọn
               khối lớp thì lọc lộ trình theo khối đó.

               NGOẠI LỆ DUY NHẤT: đi từ trang lộ trình sang bằng ?lo-trinh=<id> (nút "Xem lớp
               của lộ trình này"). Lúc đó người ta đã chốt lộ trình rồi và đang muốn xem các
               khoá/lớp bên trong — bỏ luôn nhánh này thì cái nút kia không dẫn đi đâu cả. */
            get showPaths() {
                return this.selectedPath === 'all';
            },

            /** Lộ trình sau khi lọc theo khối lớp và từ khoá tìm kiếm. */
            get filteredPaths() {
                const g = this.selectedGradeNumber;
                const q = this.searchQuery.trim().toLowerCase();
                return this.learningPaths.filter((p) => {
                    const okGrade = g === null || (g >= p.gradeFrom && g <= p.gradeTo);
                    const okSearch = !q || (p.search || '').includes(q);
                    return okGrade && okSearch;
                });
            },

            /** null = không lọc theo lộ trình; ngược lại là danh sách id khoá của lộ trình đang chọn. */
            get selectedPathCourseIds() {
                if (this.selectedPath === 'all') return null;
                const hit = this.learningPaths.find((p) => p.id === this.selectedPath);
                return hit ? hit.courseIds : null;
            },

            get selectedPathTitle() {
                const hit = this.learningPaths.find((p) => p.id === this.selectedPath);
                return hit ? hit.title : '';
            },

            /** Đường dẫn sang trang chi tiết lộ trình đang lọc. */
            get selectedPathHref() {
                const hit = this.learningPaths.find((p) => p.id === this.selectedPath);
                return hit ? hit.href : null;
            },

            setLearningPath(value) { this.selectedPath = value; this.currentPage = 1; },
            setCategory(value) { this.selectedCategory = value; this.currentPage = 1; },
            setGrade(value) { this.selectedGrade = value; this.currentPage = 1; },
            resetFilters() {
                this.selectedCategory = 'all';
                this.selectedGrade = 'all';
                this.selectedPath = 'all';
                this.searchQuery = '';
                this.currentPage = 1;
            },
        };
    }
</script>
