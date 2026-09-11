<script>
    function onthiCoursesPage(config) {
        return {
            rows: config.rows,
            pageSize: config.pageSize,
            selectedCategory: config.subject || 'all',
            selectedGrade: 'all',
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
                    const matchSearch = !q || r.search.includes(q);
                    return matchCat && matchGrade && matchSearch;
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
            setGrade(value) { this.selectedGrade = value; this.currentPage = 1; },
            resetFilters() {
                this.selectedCategory = 'all';
                this.selectedGrade = 'all';
                this.searchQuery = '';
                this.currentPage = 1;
            },
        };
    }
</script>
