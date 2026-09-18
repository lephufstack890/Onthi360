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

            /*
             * SỬA 18/9 (khách yêu cầu) — POPUP CHI TIẾT LỚP.
             * Ruột popup là MẢNH giao diện do máy chủ dựng (route courses.classDetail), nạp đúng
             * lớp vừa bấm. Làm vậy vì trang đang liệt kê tới 120 lớp: nhồi sẵn lịch học + đánh giá
             * của tất cả vào một lần tải là hàng trăm truy vấn thừa cho một lần xem một lớp.
             */
            detailUrl: config.detailUrl || '',
            detail: { open: false, loading: false, html: '' },

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
            async openClassDetail(id) {
                this.detail.open = true;
                this.detail.loading = true;
                this.detail.html = '';
                // Khoá cuộn nền để cuộn trong popup không kéo theo cả trang phía sau.
                document.body.classList.add('overflow-hidden');

                try {
                    const res = await fetch(this.detailUrl.replace('__ID__', id), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (! res.ok) throw new Error('HTTP ' + res.status);
                    this.detail.html = await res.text();
                } catch (e) {
                    this.detail.html = '<div class="flex flex-1 items-center justify-center p-10 text-center text-sm text-slate-600">'
                        + 'Không tải được chi tiết lớp. Kiểm tra kết nối rồi thử lại.'
                        + '<\/div>';
                } finally {
                    this.detail.loading = false;
                }
            },

            closeClassDetail() {
                this.detail.open = false;
                document.body.classList.remove('overflow-hidden');
            },

            /*
             * Ruột popup được nhét vào bằng x-html, mà Alpine 3 KHÔNG tự khởi tạo cây DOM mới —
             * nên trong mảnh đó không đặt chỉ thị Alpine nào. Mọi thao tác (đóng, đổi tab) bắt ở
             * đây, trên thẻ bao ngoài, bằng data-* thuần.
             */
            onDetailClick(event) {
                if (event.target.closest('[data-class-detail-close]')) {
                    this.closeClassDetail();
                    return;
                }

                const tab = event.target.closest('[data-class-tab]');
                if (tab) this.setDetailTab(tab.getAttribute('data-class-tab'));
            },

            setDetailTab(id) {
                const root = this.$refs.detailPanel;
                if (! root) return;

                root.querySelectorAll('[data-class-tab]').forEach((button) => {
                    const on = button.getAttribute('data-class-tab') === id;
                    button.classList.toggle('bg-[#126F91]', on);
                    button.classList.toggle('text-white', on);
                    button.classList.toggle('shadow-sm', on);
                    button.classList.toggle('text-slate-500', ! on);
                    button.classList.toggle('hover:bg-sky-50', ! on);
                    button.classList.toggle('hover:text-[#126F91]', ! on);
                });

                root.querySelectorAll('[data-class-panel]').forEach((panel) => {
                    panel.hidden = panel.getAttribute('data-class-panel') !== id;
                });
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
