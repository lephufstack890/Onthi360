{{-- ═══════════ BỘ LỌC + PHÂN TRANG CỘT BÀI TẬP (dùng chung 2 màn) ═══════════
     SỬA 3/10 (khách: "2 cái phải đồng bộ") — tách khỏi student/materials/read.blade.php để màn
     đọc sản phẩm dùng CHUNG. Bên gọi đặt trong @push('scripts'). --}}
    {{-- SỬA 2/10 — bộ lọc + PHÂN TRANG danh sách bài tập (3 bài/trang) theo bản mẫu mới
         education-main/src/components/MaterialReaderPage.jsx. Trước đây danh sách chỉ lọc rồi
         cuộn; sản phẩm có vài chục bài là cuộn mỏi tay.

         Thẻ bài tập vẫn do máy chủ dựng sẵn (giữ nguyên nút Làm bài dạng form POST có CSRF);
         phần việc ở đây chỉ là quyết định thẻ nào HIỆN và xếp theo thứ tự nào — cùng cách
         partials/practice-page-script đang làm cho kho bài tập. --}}
    <script>
        function onthiMaterialReader(config) {
            return {
                exercises: config.exercises || [],
                perPage: config.perPage || 3,

                focus: false,
                q: '',
                filter: 'all',
                page: 1,
                // SỬA 3/10 — thẻ bài tập đang được chọn (bản mẫu: bấm tên bài thì thẻ sáng viền).
                selectedId: null,

                get filtered() {
                    const needle = this.q.trim().toLowerCase();

                    return this.exercises.filter((item) => {
                        const matchFilter = this.filter === 'all'
                            || (this.filter === 'done' ? item.done : ! item.done);

                        return matchFilter && (! needle || item.search.includes(needle));
                    });
                },

                get pageCount() {
                    return Math.max(1, Math.ceil(this.filtered.length / this.perPage));
                },

                // Trang hiện tại không bao giờ vượt quá tổng số trang sau khi lọc.
                get currentPage() {
                    return Math.min(this.page, this.pageCount);
                },

                get visibleIds() {
                    const start = (this.currentPage - 1) * this.perPage;

                    return this.filtered.slice(start, start + this.perPage).map((item) => item.id);
                },

                init() {
                    // Đổi bộ lọc hay gõ tìm kiếm thì về trang 1, nếu không người dùng đang ở
                    // trang 3 mà lọc còn 2 bài sẽ thấy danh sách trống không hiểu vì sao.
                    this.$watch('q', () => { this.page = 1; });
                    this.$watch('filter', () => { this.page = 1; });
                },
            };
        }
    </script>
