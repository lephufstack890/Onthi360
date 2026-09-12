<script>
    function onthiLeaderboardPage(config) {
        return {
            rows: config.rows,
            // SỬA 12/9 — source mới phân trang 5 dòng/trang thay vì đổ hết một lượt.
            pageSize: config.pageSize || 5,
            page: 1,
            query: '',

            init() {
                // Gõ tìm kiếm thì quay về trang 1; đổi bộ lọc mà quá số trang thì kéo về trang cuối.
                this.$watch('query', () => { this.page = 1; });
                this.$watch('totalPages', (value) => { if (this.page > value) this.page = value; });
            },

            get normalized() { return this.query.trim().toLowerCase(); },

            get matchedRanks() {
                if (!this.normalized) return this.rows.map((r) => r.rank);
                return this.rows.filter((r) => r.search.includes(this.normalized)).map((r) => r.rank);
            },

            get visibleCount() { return this.matchedRanks.length; },

            get totalPages() { return Math.max(1, Math.ceil(this.matchedRanks.length / this.pageSize)); },

            get pageRanks() {
                const start = (this.page - 1) * this.pageSize;
                return this.matchedRanks.slice(start, start + this.pageSize);
            },

            matches(rank) { return this.matchedRanks.includes(rank); },

            isVisible(rank) { return this.pageRanks.includes(rank); },
        };
    }
</script>
