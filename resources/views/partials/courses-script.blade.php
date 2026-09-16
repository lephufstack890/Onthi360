<script>
    function onthiCoursesPage(config) {
        return {
            rows: config.rows,
            courses: config.courses || [],
            pageSize: config.pageSize,
            selectedCategory: config.subject || 'all',
            selectedGrade: 'all',
            // SỬA 16/9 — bộ lọc theo KHOÁ HỌC. Giữ kiểu số vì id khoá là số; 'all' là chưa lọc.
            // config.course: mã khoá chọn sẵn từ ?khoa= trên đường dẫn (nút "Xem lộ trình" ở
            // trang chủ). Không có thì 'all'.
            selectedCourse: config.course || 'all',
            searchQuery: '',
            currentPage: 1,

            init() {
                this.$watch('searchQuery', () => { this.currentPage = 1; });
            },

            get filtered() {
                const q = this.searchQuery.trim().toLowerCase();
                return this.rows.filter((r) => {
                    const matchCat = this.selectedCategory === 'all' || r.subject === this.selectedCategory;
                    const matchGrade = this.selectedGrade === 'all' || r.grade === this.selectedGrade;
                    const matchCourse = this.selectedCourse === 'all' || r.courseId === this.selectedCourse;
                    const matchSearch = !q || r.search.includes(q);
                    return matchCat && matchGrade && matchCourse && matchSearch;
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

            setCategory(value) { this.selectedCategory = value; this.currentPage = 1; },
            /*
             * Đổi KHỐI LỚP thì bỏ luôn khoá đang chọn: khoá đó thường không thuộc khối mới,
             * để nguyên sẽ ra danh sách rỗng và người dùng không hiểu vì sao.
             */
            setGrade(value) {
                this.selectedGrade = value;
                this.selectedCourse = 'all';
                this.currentPage = 1;
            },
            setCourse(value) { this.selectedCourse = value; this.currentPage = 1; },

            /** Các khoá còn hợp lệ với khối lớp đang chọn — dải chip "Khoá học" dựng từ đây. */
            get visibleCourses() {
                if (this.selectedGrade === 'all') return this.courses;
                return this.courses.filter((c) => c.grade === this.selectedGrade);
            },
            resetFilters() {
                this.selectedCategory = 'all';
                this.selectedGrade = 'all';
                this.selectedCourse = 'all';
                this.searchQuery = '';
                this.currentPage = 1;
            },
        };
    }
</script>
