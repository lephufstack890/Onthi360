{{-- Alpine cho màn Tài liệu — chuyển đúng useState/useEffect của
     education-main/src/components/MaterialsPage.jsx: 3 tab đổi ngay tại chỗ, tìm kiếm,
     phân trang 4 thẻ/trang, hộp chi tiết & mua quyền. --}}
<script>
    function onthiMaterialsPage(config) {
        return {
            rows: config.rows,
            pageSize: config.pageSize,
            activateHref: config.activateHref,
            activeTab: config.activeTab,
            searchQuery: '',
            pageIndex: 1,
            selected: null,

            init() {
                // Bản mẫu dùng useEffect để quay về trang 1 mỗi khi đổi tab hoặc từ khoá.
                this.$watch('searchQuery', () => { this.pageIndex = 1; });
            },

            setTab(tab) {
                this.activeTab = tab;
                this.pageIndex = 1;
            },

            get filtered() {
                const q = this.searchQuery.trim().toLowerCase();
                return this.rows.filter((r) => r.tab === this.activeTab && (!q || r.search.includes(q)));
            },

            get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.pageSize)); },

            // Trang hiện tại không bao giờ vượt quá tổng số trang sau khi lọc.
            get page() { return Math.min(this.pageIndex, this.totalPages); },

            get visibleIds() {
                const start = (this.page - 1) * this.pageSize;
                return this.filtered.slice(start, start + this.pageSize).map((r) => r.id);
            },

            openDetail(id) {
                this.selected = this.rows.find((r) => r.id === id) || null;
            },
        };
    }
</script>
