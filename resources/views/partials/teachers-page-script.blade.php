<script>
    function onthiTeachersPage(config) {
        return {
            rows: config.rows,
            pageSize: config.pageSize,
            coursesHref: config.coursesHref,
            pageIndex: 1,
            selected: null,

            get totalPages() { return Math.max(1, Math.ceil(this.rows.length / this.pageSize)); },

            get page() { return Math.min(this.pageIndex, this.totalPages); },

            get visibleIds() {
                const start = (this.page - 1) * this.pageSize;
                return this.rows.slice(start, start + this.pageSize).map((r) => r.id);
            },

            openProfile(id) {
                this.selected = this.rows.find((r) => r.id === id) || null;
            },
        };
    }
</script>
