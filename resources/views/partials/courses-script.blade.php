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
